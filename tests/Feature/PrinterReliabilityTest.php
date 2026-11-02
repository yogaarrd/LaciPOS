<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\PrintService;
use App\Services\ReceiptService;
use App\Services\ReceiptLogo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrinterReliabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Explicitly isolated connection; never migrate the user's database.
        config(['database.default' => 'printer_test', 'database.connections.printer_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'nativephp-internal.running' => true, 'nativephp-internal.api_url' => 'http://printer.test/api/']);
        Schema::create('store_profiles', function (Blueprint $table) {
            $table->string('id');
            $table->string('printer_name')->nullable();
            $table->string('paper_size');
        });
        DB::table('store_profiles')->insert(['id' => 'test', 'printer_name' => 'POS-58', 'paper_size' => '58mm']);
        Http::preventStrayRequests();
        $this->mock(ReceiptService::class, function ($mock) {
            $mock->shouldReceive('renderReceiptHtml')->andReturn("<html>50% #1\n</html>");
        });
    }

    public function test_success_keeps_existing_thermal_settings_and_manual_name(): void
    {
        Http::fake(['*/system/print' => Http::response('', 200)]);
        $result = app(PrintService::class)->printReceipt(new Order());
        $this->assertTrue($result['success']);
        Http::assertSent(fn ($request) => $request['printer'] === 'POS-58'
            && $request['settings']['pageSize'] === ['width' => 58000, 'height' => 3276000]
            && $request['settings']['silent'] === true
            && $request['settings']['printBackground'] === false
            && $request['settings']['margins'] === ['marginType' => 'none']
            && $request['html'] === '<html>50%25 %231</html>');
        Http::assertSentCount(1); // No discovery prerequisite, no duplicate dispatch.
    }

    public function test_http_failure_is_not_reported_as_success(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->assertFalse(app(PrintService::class)->printReceipt(new Order())['success']);
        Http::assertSentCount(1);
    }

    public function test_connection_failure_does_not_retry(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $result = app(PrintService::class)->printReceipt(new Order());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('belum dapat dipastikan', $result['message']);
    }

    public function test_web_mode_gives_actionable_failure_without_sending(): void
    {
        config(['nativephp-internal.running' => false]);
        Http::fake();
        $this->assertFalse(app(PrintService::class)->printReceipt(new Order())['success']);
        Http::assertNothingSent();
    }

    public function test_default_printer_and_existing_80mm_setting_are_preserved(): void
    {
        DB::table('store_profiles')->update(['printer_name' => null, 'paper_size' => '80mm']);
        Http::fake(['*' => Http::response('', 200)]);
        app(PrintService::class)->printReceipt(new Order());
        Http::assertSent(fn ($request) => $request['printer'] === '' && $request['settings']['pageSize']['width'] === 80000);
    }

    public function test_manual_print_controller_propagates_failure(): void
    {
        $service = $this->mock(PrintService::class);
        $service->shouldReceive('printReceipt')->once()->andReturn(['success' => false, 'message' => 'Printer gagal']);
        $response = app(\App\Http\Controllers\PrintController::class)->print(new Order(), $service);
        $this->assertSame('Printer gagal', $response->getSession()->get('warning'));
        $this->assertNull($response->getSession()->get('print_success'));
    }

    public function test_corrupt_and_missing_logo_fall_back_to_text(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('broken.png', 'not an image');
        $logo = new ReceiptLogo();
        $this->assertNull($logo->dataUrl('broken.png'));
        $this->assertNull($logo->dataUrl('missing.png'));
        $this->assertNull($logo->dataUrl(null));
    }

    public function test_large_logo_is_bounded_without_modifying_original(): void
    {
        if (!function_exists('imagecreatetruecolor')) { $this->markTestSkipped('GD unavailable'); }
        Storage::fake('public');
        $source = imagecreatetruecolor(1200, 600);
        ob_start(); imagepng($source); $original = ob_get_clean(); imagedestroy($source);
        Storage::disk('public')->put('logo.png', $original);
        $url = (new ReceiptLogo())->dataUrl('logo.png');
        $bytes = base64_decode(explode(',', $url, 2)[1]);
        $info = getimagesizefromstring($bytes);
        $this->assertSame([256, 128], [$info[0], $info[1]]);
        $this->assertLessThanOrEqual(49152, strlen($bytes));
        $this->assertSame($original, Storage::disk('public')->get('logo.png'));
    }
    public function test_discovery_failure_is_visible_in_settings(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->withoutMiddleware()->get('/settings/printer')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Printer')
                ->where('printerStatus', 'unavailable')->has('printers', 0));
    }

    public function test_discovery_exposes_system_default_without_claiming_connection(): void
    {
        Http::fake(['*' => Http::response(['printers' => [
            ['name' => 'POS-58', 'displayName' => 'Thermal', 'isDefault' => true],
        ]])]);
        $this->withoutMiddleware()->get('/settings/printer')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Printer')
                ->where('printerStatus', 'available')
                ->where('printers.0.name', 'POS-58')->where('printers.0.isDefault', true));
    }

    public function test_without_logo_option_is_forwarded_to_renderer(): void
    {
        $renderer = \Mockery::mock(ReceiptService::class);
        $renderer->shouldReceive('renderReceiptHtml')->withArgs(fn ($order, $paper, $withoutLogo) => $paper === '58mm' && $withoutLogo)
            ->once()->andReturn('<html>Text only</html>');
        Http::fake(['*' => Http::response('', 200)]);
        $this->assertTrue((new PrintService($renderer))->printReceipt(new Order(), true)['success']);
    }

    public function test_oversized_receipt_is_reduced_before_sending_once(): void
    {
        $renderer = \Mockery::mock(ReceiptService::class);
        $renderer->shouldReceive('renderReceiptHtml')->with(\Mockery::type(Order::class), '58mm', false)->once()->andReturn(str_repeat('a', 110000));
        $renderer->shouldReceive('renderReceiptHtml')->with(\Mockery::type(Order::class), '58mm', true)->once()->andReturn('<html>Text only</html>');
        Http::fake(['*' => Http::response('', 200)]);
        $result = (new PrintService($renderer))->printReceipt(new Order());
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('tanpa logo', $result['message']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['html'] === '<html>Text only</html>');
    }

    public function test_oversized_text_receipt_is_not_sent(): void
    {
        $renderer = \Mockery::mock(ReceiptService::class);
        $renderer->shouldReceive('renderReceiptHtml')->andReturn(str_repeat('a', 110000));
        Http::fake();
        $this->assertFalse((new PrintService($renderer))->printReceipt(new Order())['success']);
        Http::assertNothingSent();
    }

}
