<?php

namespace App\Models\Front\Checkout\Shipping;

use App\Models\Back\Orders\Order;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class HP
{

    /**
     * @var string
     */
    private $env;

    /**
     * @var string[]
     */
    private $url_authorization = [
        'local'      => 'https://dxwebapit.posta.hr:9000',
        'production' => 'https://dxwebapi.posta.hr:9000',
    ];

    /**
     * @var string[]
     */
    private $url_api = [
        'local'      => 'https://dxwebapit.posta.hr:9020',
        'production' => 'https://dxwebapi.posta.hr:9020',
    ];

    /**
     * @var string[]
     */
    private $username;

    /**
     * @var string[]
     */
    private $password;

    /**
     * @var string[]
     */
    private $client_id;

    /**
     * @var int
     */
    private $order;

    /**
     * @var array
     */
    private $response = [];


    /**
     * HP constructor.
     *
     * @param $order
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->env = config('app.env');
        $this->username = [
            'local'      => env('HP_USERNAME_TEST'),
            'production' => env('HP_USERNAME_PRODUCTION')
        ];
        $this->password = [
            'local'      => env('HP_PASSWORD_TEST'),
            'production' => env('HP_PASSWORD_PRODUCTION')
        ];
        $this->client_id = [
            'local'      => env('HP_CLIENT_ID_TEST'),
            'production' => env('HP_CLIENT_ID_PRODUCTION')
        ];
    }


    /**
     * @return $this
     */
    public function createShipmentOrder(): HP
    {
        try {
            $url   = $this->url_api[$this->env] . '/api/shipment/create_shipment_orders';
            $token = $this->getAccessToken();

            $post = [
                'parcels'              => $this->getParcel(),
                'return_address_label' => true
            ];

            Log::info(json_encode($post));

            $this->response = Http::withToken($token)->post($url, $post)->json();

            Log::info($this->response);

        } catch (Exception $response) {
            Log::info($response->getMessage());

            echo $response->getMessage();
        }

        return $this;
    }


    /**
     * @return $this
     */
    public function pingHP(): HP
    {
        $url = $this->url_api[$this->env] . '/api/ping';

        try {
            $response = Http::get($url);

            Log::info($response);

        } catch (Exception $response) {
            Log::info($response->getMessage());

            echo $response->getMessage();
        }

        return $this;
    }


    /**
     * @return bool
     */
    public function setOrderLabelAsPrinted(): bool
    {
        return $this->order->update([
            'printed' => 1
        ]);
    }


    /**
     * @return bool
     */
    public function isSuccessfulResponse(): bool
    {
        if ( ! empty($this->response)) {
            $shipment = $this->getShipmentOrdersList();

            if ($shipment['ResponseStatus'] == 0) {
                return true;
            }
        }

        return false;
    }


    /**
     * @return array
     */
    public function getShipmentOrdersList(): array
    {
        return collect($this->response['ShipmentOrdersList'])->first();
    }


    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        $shipment = $this->getShipmentOrdersList();

        if ($shipment['ResponseStatus'] == 1) {
            return $shipment['ErrorCode'] . ': ' . $shipment['ErrorMessage'];
        }

        return '';
    }


    /**
     * @return string
     */
    public function getPackageBarcode(): string
    {
        $shipment = $this->getShipmentOrdersList();

        if (isset($shipment['Packages'][0]['barcode'])) {
            return $shipment['Packages'][0]['barcode'];
        }

        return '';
    }


    /**
     * @return false|string
     */
    public function getPdfLabel(): false|string
    {
        if ( ! empty($this->response['ShipmentsLabel'])) {
            return base64_decode($this->response['ShipmentsLabel']);
        }

        return false;
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @return string|null
     */
    private function getAccessToken(): string|null
    {
        try {
            $response = Http::post($this->url_authorization[$this->env] . '/api/authentication/client_auth', [
                'username' => $this->username[$this->env],
                'password' => $this->password[$this->env],
            ]);

            Log::info($response);

            if ($response->successful()) {
                return $response->json('accessToken');
            }

        } catch (Exception $response) {
            Log::info($response->getMessage());

            echo $response->getMessage();
        }

        return null;
    }


    /**
     * @return array
     */
    private function getParcel(): array
    {
        $parcel = [
            'client_reference_number' => $this->order->id,
            'service'                 => '26',
            'payed_by'                => 1,
            'delivery_type'           => 3,
            'value'                   => null,
            'payment_value'           => null,
            'pickup_type'             => 3,
            'parcel_size'             => 'X', // X, S, M, L
            'sender'                  => $this->getSender(),
            'recipient'               => $this->getRecipient(),
            'additional_services'     => $this->getAdditionalServices(),
            'packages'                => $this->getPackages(),
        ];

        return [
            $parcel
        ];
    }


    /**
     * @return array
     */
    private function getSender(): array
    {
        return [
            'sender_name'          => 'Mirjana Vulić',
            'sender_phone'         => '0916047126',
            'sender_email'         => 'info@zuzi.hr',
            'sender_street'        => 'Antuna Šoljana',
            'sender_hnum'          => '33',
            'sender_hnum_suffix'   => '.',
            'sender_zip'           => 10000,
            'sender_city'          => 'Zagreb',
            'sender_pickup_center' => ($this->env == 'local') ? '61004' : substr($this->order->comment, 0, 5)
        ];
    }


    /**
     * @return array
     */
    private function getRecipient(): array
    {
        return [
            'recipient_name'            => $this->order->shipping_fname . ' ' . $this->order->shipping_lname,
            'recipient_phone'           => $this->order->shipping_phone,
            'recipient_email'           => $this->order->shipping_email,
            'recipient_street'          => $this->order->shipping_address,
            'recipient_hnum'            => '.',
            'recipient_hnum_suffix'     => '.',
            'recipient_zip'             => $this->order->shipping_zip,
            'recipient_city'            => $this->order->shipping_city,
            'recipient_delivery_center' => ($this->env == 'local') ? '61004' : substr($this->order->comment, 0, 5)
        ];
    }


    /**
     * @return array
     */
    private function getAdditionalServices(): array
    {
        return [];
    }


    /**
     * @return array
     */
    private function getPackages(): array
    {
        $package = [
            'barcode'        => '',
            'barcode_type'   => 1,
            'barcode_client' => $this->client_id[$this->env],
            'weight'         => 1,
        ];

        return [
            $package
        ];
    }

}
