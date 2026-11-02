<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\PaymentMethod;
use Inertia\Inertia;

class POSController extends Controller
{
    public function index()
    {
        $products = Product::with(['variants.options', 'discount'])
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'cogs' => $p->cogs,
                'stock' => $p->stock,
                'photo_url' => $p->photo_path ? route('app.img', ['path' => $p->photo_path, 'thumbnail' => 1]) : null,
                'category_id' => $p->category_id,
                'is_discount_active' => $p->is_discount_active,
                'discount_type' => $p->discount ? $p->discount->discount_type : null,
                'discount_value' => $p->discount ? $p->discount->discount_value : null,
                'discount_quota_remaining' => $p->discount && $p->discount->quota !== null ? max(0, $p->discount->quota - $p->discount->quota_used) : null,
                'calculated_discount_amount' => $p->calculated_discount_amount,
                'has_variants' => $p->variants->isNotEmpty(),
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'options' => $v->options->map(fn ($o) => [
                        'id' => $o->id,
                        'label' => $o->label,
                        'price_modifier' => $o->price_modifier,
                        'cogs_modifier' => $o->cogs_modifier,
                    ]),
                ]),
            ]);

        $categories = Category::orderBy('name')->get(['id', 'name']);
        $nextOrderNumber = Order::generateOrderNumber();
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        return Inertia::render('POS/Index', [
            'products' => $products,
            'categories' => $categories,
            'next_order_number' => $nextOrderNumber,
            'payment_methods' => $paymentMethods,
        ]);
    }
}
