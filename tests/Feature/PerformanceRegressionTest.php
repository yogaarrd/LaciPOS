<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Services\ProductThumbnail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'performance_test', 'database.connections.performance_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        foreach (glob(database_path('migrations/*.php')) as $file) {
            (require $file)->up();
        }
    }

    public function test_dashboard_totals_and_empty_days_survive_query_consolidation(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(12, 0));
        $user = \App\Models\User::factory()->create();
        foreach ([['2026-11-02 09:00:00', 'completed', 120, 10, 5],
            ['2026-11-01 23:59:59', 'completed', 80, 0, 0],
            ['2026-11-02 10:00:00', 'cancelled', 500, 0, 0]] as $i => $row) {
            DB::table('orders')->insert(['id' => 'order-'.$i, 'user_id' => $user->id,
                'order_number' => 'test-'.$i, 'created_at' => $row[0], 'status' => $row[1],
                'payment_method' => 'cash', 'total_amount' => $row[2], 'tax_amount' => $row[3],
                'service_charge_amount' => $row[4]]);
        }
        DB::enableQueryLog();
        $response = app(DashboardController::class)->index()->toResponse(Request::create('/dashboard'));
        $props = $response->getOriginalContent()['page']['props'];
        $this->assertEquals(105, $props['stats']['totalRevenue']);
        $this->assertSame(1, $props['stats']['totalTransactions']);
        $this->assertCount(7, $props['salesData']);
        $this->assertEquals(0, $props['salesData'][0]['revenue']);
        $this->assertEquals(80, $props['salesData'][5]['revenue']);
        $this->assertEquals(105, $props['salesData'][6]['revenue']);
        $grouped = collect(DB::getQueryLog())->filter(fn ($query) => str_contains(strtolower($query['query']), 'group by'));
        $this->assertCount(1, $grouped);
    }

    public function test_product_list_loads_discounts_once_and_keeps_discount_amount(): void
    {
        $category = Category::create(['name' => 'Kopi']);
        $discount = Discount::create(['name' => 'Promo', 'discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true]);
        for ($i = 0; $i < 20; $i++) {
            Product::create(['category_id' => $category->id, 'discount_id' => $discount->id,
                'name' => 'Kopi '.$i, 'price' => 20000, 'cogs' => 8000, 'stock' => 20]);
        }
        DB::enableQueryLog();
        $response = app(ProductController::class)->index(Request::create('/products'))->toResponse(Request::create('/products'));
        $products = $response->getOriginalContent()['page']['props']['products']['data'];
        $this->assertCount(20, $products);
        $this->assertTrue($products[0]['is_discount_active']);
        $this->assertEquals(2000, $products[0]['calculated_discount_amount']);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "discounts"'));
        $this->assertCount(1, $queries);
    }

    public function test_thumbnail_is_cached_and_original_is_preserved(): void
    {
        if (!function_exists('imagecreatetruecolor')) { $this->markTestSkipped('GD unavailable'); }
        $originalStorage = storage_path();
        $directory = sys_get_temp_dir().'/laci-thumb-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $original = $directory.'/original.png';
        $image = imagecreatetruecolor(1200, 600);
        imagepng($image, $original);
        imagedestroy($image);
        $hash = hash_file('sha256', $original);
        $this->app->useStoragePath($directory);
        try {
            $service = new ProductThumbnail();
            $thumbnail = $service->path($original);
            $info = getimagesize($thumbnail);
            $this->assertSame([320, 160], [$info[0], $info[1]]);
            $this->assertSame($thumbnail, $service->path($original));
            $this->assertSame($hash, hash_file('sha256', $original));
            $this->assertSame($directory.'/missing.png', $service->path($directory.'/missing.png'));
        } finally {
            $this->app->useStoragePath($originalStorage);
            \Illuminate\Support\Facades\File::deleteDirectory($directory);
        }
    }

    public function test_profile_cache_is_request_scoped_and_invalidated_after_save(): void
    {
        $mode = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $previous = $mode->getValue($this->app);
        $mode->setValue($this->app, false);
        try {
            $this->app->instance('request', Request::create('/settings'));
            $store = \App\Models\StoreProfile::create(['store_name' => 'Toko Awal']);
            DB::enableQueryLog();
            $this->assertSame('Toko Awal', \App\Models\StoreProfile::getProfile()->store_name);
            $this->assertTrue(\App\Models\StoreProfile::isOnboardingCompleted());
            $this->assertCount(1, DB::getQueryLog());
            $store->update(['store_name' => 'Toko Baru']);
            $this->assertSame('Toko Baru', \App\Models\StoreProfile::getProfile()->store_name);
            $this->app->instance('request', Request::create('/pos'));
            DB::flushQueryLog();
            $this->assertSame('Toko Baru', \App\Models\StoreProfile::getProfile()->store_name);
            $this->assertCount(1, DB::getQueryLog());
        } finally {
            $mode->setValue($this->app, $previous);
        }
    }
}
