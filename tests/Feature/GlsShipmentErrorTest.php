<?php

namespace Tests\Feature;

use App\Http\Controllers\Back\OrderController;
use App\Models\Back\Orders\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GlsShipmentErrorTest extends TestCase
{
    private $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'gls_errors_testing',
            'database.connections.gls_errors_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'cache.default' => 'array',
        ]);
        DB::purge('gls_errors_testing');
        Cache::clear();

        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('shipping_code');
            $table->string('shipping_carrier')->nullable();
            $table->string('shipping_parcel_id')->nullable();
            $table->string('tracking_code')->nullable();
            $table->boolean('printed')->default(false);
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('product_id');
        });

        DB::table('orders')->insert(['id' => 1, 'shipping_code' => 'gls_world']);
    }

    protected function tearDown(): void
    {
        DB::purge('gls_errors_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    /** @dataProvider apiErrorProvider */
    public function test_dispatch_exposes_only_clean_api_error_descriptions(array $errors, string $expected): void
    {
        $controller = new ErrorResponseGlsOrderController([
            'PrepareLabelsError' => $errors,
            'GetPrintedLabelsRequest' => ['Password' => 'private-password-marker'],
        ]);

        $response = $controller->api_send_gls(new Request(['order_id' => 1]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('GLS nije kreirao pošiljku. ' . $expected, $response->getData(true)['error']);
        $this->assertStringNotContainsString('private-password-marker', $response->getContent());
        $this->assertStringNotContainsString('<', $response->getData(true)['error']);
        $this->assertSame(0, (int) DB::table('orders')->where('id', 1)->value('printed'));
        $this->assertNull(DB::table('orders')->where('id', 1)->value('shipping_parcel_id'));
    }

    public function apiErrorProvider(): array
    {
        return [
            'single error' => [
                ['ErrorCode' => 1, 'ErrorDescription' => '  <b>Invalid recipient country.</b>  ', 'Password' => 'private-password-marker'],
                'Invalid recipient country.',
            ],
            'error list' => [
                [
                    ['ErrorDescription' => 'Country is invalid.'],
                    ['ErrorDescription' => 'Postcode is invalid.'],
                    ['ErrorDescription' => 'Country is invalid.'],
                    ['ErrorDescription' => ' <br> '],
                ],
                'Country is invalid. Postcode is invalid.',
            ],
            'SOAP Error wrapper' => [
                ['Error' => ['ErrorDescription' => 'Service is unavailable for this country.']],
                'Service is unavailable for this country.',
            ],
            'SOAP nested error list' => [
                ['PrepareLabelsError' => ['Error' => [
                    ['ErrorDescription' => 'Country is invalid.'],
                    ['ErrorDescription' => '<span>Postcode is invalid.</span>'],
                ]]],
                'Country is invalid. Postcode is invalid.',
            ],
        ];
    }

    /** @dataProvider missingDescriptionProvider */
    public function test_dispatch_preserves_generic_error_when_api_has_no_description(array $label): void
    {
        $response = (new ErrorResponseGlsOrderController($label))
            ->api_send_gls(new Request(['order_id' => 1]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'Greška..! Molimo pokušajte ponovo ili kontaktirajte administratora..',
            $response->getData(true)['error']
        );
        $this->assertStringNotContainsString('private-password-marker', $response->getContent());
    }

    public function missingDescriptionProvider(): array
    {
        return [
            'no errors' => [[]],
            'empty descriptions and unrelated data' => [[
                'PrepareLabelsError' => [
                    ['ErrorDescription' => ' <br> '],
                    ['ErrorCode' => 1, 'Password' => 'private-password-marker'],
                ],
            ]],
        ];
    }

    public function test_legacy_dispatch_endpoint_reports_gls_error_descriptions(): void
    {
        $controller = new ErrorResponseGlsOrderController([
            'PrepareLabelsError' => [['ErrorDescription' => 'Country is invalid.']],
        ]);

        $response = $controller->api_send_glsstari(new Request(['order_id' => 1]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('GLS nije kreirao pošiljku. Country is invalid.', $response->getData(true)['error']);
        $this->assertSame(0, (int) DB::table('orders')->where('id', 1)->value('printed'));
    }
}

class ErrorResponseGlsOrderController extends OrderController
{
    private $label;

    public function __construct(array $label)
    {
        $this->label = $label;
    }

    protected function resolveGlsShipment(Order $order): array
    {
        return $this->label;
    }
}
