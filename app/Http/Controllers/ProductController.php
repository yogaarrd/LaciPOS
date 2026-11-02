<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Discount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'discount'])
            ->withCount('variants');

        // Search by name
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filter by category
        if ($categoryId = $request->input('category')) {
            $query->where('category_id', $categoryId);
        }

        // Filter by status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', ''),
            ],
        ]);
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        $discounts = Discount::where('is_active', true)->orderBy('name')->get();
        $profile = \App\Models\StoreProfile::getProfile();
        return Inertia::render('Products/Create', [
            'categories' => $categories,
            'discounts' => $discounts,
            'defaultMinStock' => $profile?->default_min_stock ?? 5,
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('products', 'public');
        }
        unset($data['photo']);

        $product = Product::create($data);

        // Handle variants
        if ($request->has('variants')) {
            foreach ($request->input('variants', []) as $variantData) {
                if (empty($variantData['name'])) continue;
                $variant = $product->variants()->create(['name' => $variantData['name']]);
                foreach ($variantData['options'] ?? [] as $optionData) {
                    if (empty($optionData['label'])) continue;
                    $variant->options()->create([
                        'label' => $optionData['label'],
                        'price_modifier' => $optionData['price_modifier'] ?? 0,
                        'cogs_modifier' => $optionData['cogs_modifier'] ?? 0,
                    ]);
                }
            }
        }

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        $discounts = Discount::where('is_active', true)->orderBy('name')->get();
        return Inertia::render('Products/Edit', [
            'product' => array_merge($product->load('variants.options')->toArray(), [
                'photo_url' => $product->photo_url,
            ]),
            'categories' => Category::orderBy('name')->get(),
            'discounts' => $discounts,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        // Handle photo
        if ($request->hasFile('photo')) {
            // Hapus foto lama jika ada
            if ($product->photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($product->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('products', 'public');
        } elseif ($request->boolean('remove_photo') && $product->photo_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->photo_path);
            $data['photo_path'] = null;
        }
        unset($data['photo'], $data['remove_photo']);

        $product->update($data);

        // Handle variants (purge and re-create)
        // Karena FormData tidak mengirim key array yang kosong, kita selalu menghapus varian lama
        // dan membuat ulang sesuai isi form (jika kosong, tidak ada yang dibuat)
        $product->variants()->delete();
        foreach ($request->input('variants', []) as $variantData) {
            if (empty($variantData['name'])) continue;
            $variant = $product->variants()->create(['name' => $variantData['name']]);
            foreach ($variantData['options'] ?? [] as $optionData) {
                if (empty($optionData['label'])) continue;
                $variant->options()->create([
                    'label' => $optionData['label'],
                    'price_modifier' => $optionData['price_modifier'] ?? 0,
                    'cogs_modifier' => $optionData['cogs_modifier'] ?? 0,
                ]);
            }
        }

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);
        $product->delete(); // Soft delete

        return redirect()->route('products.index')
            ->with('success', 'Produk "' . $product->name . '" berhasil dihapus.');
    }
}
