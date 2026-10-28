<?php

namespace Tests\Unit;

use App\Models\Front\Checkout\Shipping\Glsstari;
use SoapClient;
use SoapFault;
use Tests\TestCase;

class GlsShipmentTest extends TestCase
{
    private const CAPTURED_RESPONSE = 'Synthetic GLS response: request captured.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gls.client_number' => 123456789,
            'services.gls.username' => 'gls@example.test',
            'services.gls.password' => 'test-password',
            'services.gls.wsdl' => 'https://gls.example.test/ParcelService.svc?singleWsdl',
        ]);
    }

    /** @dataProvider recipientCountries */
    public function test_outgoing_soap_payload_uses_the_recipient_country(string $country, string $isoCode): void
    {
        $request = $this->captureRequest($this->order(['payment_state' => $country]));
        $parcel = $request['prepareLabelsRequest']['ParcelList'][0];

        $this->assertSame($isoCode, $parcel->DeliveryAddress->CountryIsoCode);
        $this->assertSame('HR', $parcel->PickupAddress->CountryIsoCode);
        $this->assertSame('Test Recipient', $parcel->DeliveryAddress->Name);
        $this->assertSame('Recipient Street 7', $parcel->DeliveryAddress->Street);
        $this->assertSame('Test City', $parcel->DeliveryAddress->City);
        $this->assertSame('1000', $parcel->DeliveryAddress->ZipCode);
        $this->assertSame(0, $parcel->CODAmount);
        $this->assertFalse(isset($parcel->ServiceList));
    }

    public function recipientCountries(): array
    {
        return [
            'Croatia' => ['Croatia', 'HR'],
            'Slovenia' => ['Slovenia', 'SI'],
            'Germany' => ['Germany', 'DE'],
            'Austria' => ['Austria', 'AT'],
            'ISO2' => ['SI', 'SI'],
            'ISO3' => ['AUT', 'AT'],
            'name case and whitespace' => ['  sLoVeNiA  ', 'SI'],
            'ISO2 case and whitespace' => [' de ', 'DE'],
            'ISO3 case and whitespace' => [' hrv ', 'HR'],
            'Croatian name for Croatia' => ['Hrvatska', 'HR'],
            'Croatian name for Slovenia' => ['Slovenija', 'SI'],
            'Croatian name for Germany' => ['Njemačka', 'DE'],
            'Croatian ASCII name for Germany' => ['Njemacka', 'DE'],
            'Croatian name for Austria' => ['Austrija', 'AT'],
            'Croatian name for Italy' => ['Italija', 'IT'],
            'Croatian name for Serbia' => ['Srbija', 'RS'],
            'Croatian name for Bosnia and Herzegovina' => ['Bosna i Hercegovina', 'BA'],
            'Croatian name for Montenegro' => ['Crna Gora', 'ME'],
            'Croatian name for Hungary' => ['Mađarska', 'HU'],
            'Croatian ASCII name for Hungary' => ['Madjarska', 'HU'],
            'Croatian name for Czechia' => ['Češka', 'CZ'],
            'Croatian ASCII name for Czechia' => ['Ceska', 'CZ'],
            'Croatian name for Slovakia' => ['Slovačka', 'SK'],
            'Croatian ASCII name for Slovakia' => ['Slovacka', 'SK'],
            'uppercase accented name and whitespace' => ['  NJEMAČKA  ', 'DE'],
            'uppercase accented first letter' => ['ČEŠKA', 'CZ'],
        ];
    }

    public function test_stale_shipping_country_does_not_override_the_payload_address_country(): void
    {
        $request = $this->captureRequest($this->order([
            'payment_state' => 'Slovenia',
            'shipping_state' => 'Croatia',
        ]));

        $this->assertSame('SI', $request['prepareLabelsRequest']['ParcelList'][0]->DeliveryAddress->CountryIsoCode);
    }

    /** @dataProvider invalidRecipientCountries */
    public function test_unknown_or_missing_country_blocks_soap_client_creation($country): void
    {
        $client = $this->getMockBuilder(SoapClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['PrepareLabels'])
            ->getMock();
        $client->expects($this->never())->method('PrepareLabels');
        $shipment = $this->shipment($this->order(['payment_state' => $country]), $client);

        $result = $shipment->resolve();

        $this->assertSame(0, $shipment->soapClientCreations);
        $this->assertArrayHasKey('PrepareLabelsError', $result);
        $this->assertStringContainsString('država primatelja', $result['PrepareLabelsError'][0]['ErrorDescription']);
        $this->assertArrayNotHasKey('ParcelIdList', $result);
    }

    public function invalidRecipientCountries(): array
    {
        return [
            'unknown name' => ['Atlantis'],
            'unknown ISO2' => ['XX'],
            'blank' => ['   '],
            'missing' => [null],
        ];
    }

    public function test_soap_failure_returns_an_error_without_printing_it(): void
    {
        $client = $this->getMockBuilder(SoapClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['PrepareLabels'])
            ->getMock();
        $client->expects($this->once())->method('PrepareLabels')
            ->willThrowException(new SoapFault('Server', 'GLS service is unavailable.'));
        $shipment = $this->shipment($this->order(), $client);

        $this->expectOutputString('');

        $this->assertSame([
            'PrepareLabelsError' => [['ErrorDescription' => 'GLS service is unavailable.']],
        ], $shipment->resolve());
    }

    public function test_domestic_locker_keeps_psd_service_and_locker_identifier(): void
    {
        $request = $this->captureRequest($this->order([
            'shipping_code' => 'gls_paketomat',
            'comment' => 'GLS paketomat_LOCKER-42',
        ]));
        $parcel = $request['prepareLabelsRequest']['ParcelList'][0];

        $this->assertSame('HR', $parcel->DeliveryAddress->CountryIsoCode);
        $this->assertCount(1, $parcel->ServiceList);
        $this->assertSame('PSD', $parcel->ServiceList[0]->Code);
        $this->assertSame('LOCKER-42', $parcel->ServiceList[0]->PSDParameter->StringValue);
    }

    public function test_cash_on_delivery_amount_and_reference_are_preserved(): void
    {
        $request = $this->captureRequest($this->order([
            'payment_code' => 'cod',
            'total' => '31.9900',
        ]));
        $parcel = $request['prepareLabelsRequest']['ParcelList'][0];

        $this->assertSame('31.99', $parcel->CODAmount);
        $this->assertSame(123, $parcel->CODReference);
        $this->assertSame(123, $parcel->ClientReference);
    }

    private function captureRequest(array $order): array
    {
        $captured = null;
        $client = $this->getMockBuilder(SoapClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['PrepareLabels'])
            ->getMock();
        $client->expects($this->once())->method('PrepareLabels')
            ->willReturnCallback(function (array $request) use (&$captured) {
                $captured = $request;

                // No parcel ID means resolve() will not attempt to update an order.
                return (object) ['PrepareLabelsResult' => (object) [
                    'PrepareLabelsErrorList' => [(object) ['ErrorDescription' => self::CAPTURED_RESPONSE]],
                    'ParcelInfoList' => [],
                ]];
            });
        $shipment = $this->shipment($order, $client);

        $result = $shipment->resolve();

        $this->assertSame(1, $shipment->soapClientCreations);
        $this->assertSame(self::CAPTURED_RESPONSE, $result['PrepareLabelsError'][0]['ErrorDescription']);
        $this->assertIsArray($captured);

        return $captured;
    }

    private function shipment(array $order, SoapClient $client): Glsstari
    {
        return new class($order, $client) extends Glsstari {
            public $soapClientCreations = 0;
            private $client;

            public function __construct(array $order, SoapClient $client)
            {
                parent::__construct($order);
                $this->client = $client;
            }

            protected function createSoapClient($wsdl, $soapOptions): SoapClient
            {
                $this->soapClientCreations++;

                return $this->client;
            }
        };
    }

    private function order(array $overrides = []): array
    {
        return array_replace([
            'id' => 123,
            'comment' => '',
            'shipping_code' => 'gls',
            'payment_code' => 'bank',
            'total' => '31.9900',
            'payment_fname' => 'Test',
            'payment_lname' => 'Recipient',
            'payment_address' => 'Recipient Street 7',
            'payment_zip' => '1000',
            'payment_city' => 'Test City',
            'payment_state' => 'Croatia',
            'payment_phone' => '+38510000001',
            'payment_email' => 'recipient@example.test',
        ], $overrides);
    }
}
