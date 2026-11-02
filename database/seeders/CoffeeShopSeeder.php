<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CoffeeShopSeeder extends Seeder
{
    /**
     * Contoh menu coffee shop. Harga dalam rupiah; sesuaikan dengan biaya dan harga toko.
     */
    public function run(): void
    {
        $menu = [
            'Kopi' => [
                ['Espresso', 7000, 18000, 40],
                ['Americano', 8000, 22000, 40],
                ['Cappuccino', 11000, 28000, 35],
                ['Cafe Latte', 11000, 28000, 35],
                ['Kopi Susu Gula Aren', 12000, 27000, 45],
                ['Mocha', 13000, 32000, 30],
            ],
            'Non-Kopi' => [
                ['Matcha Latte', 13000, 30000, 30],
                ['Chocolate', 11000, 27000, 35],
                ['Red Velvet Latte', 12000, 29000, 30],
                ['Thai Tea', 9000, 23000, 40],
                ['Lemon Tea', 7000, 20000, 40],
                ['Lychee Tea', 9000, 24000, 35],
            ],
            'Makanan' => [
                ['Nasi Goreng Kampung', 16000, 35000, 25],
                ['Nasi Ayam Teriyaki', 19000, 39000, 20],
                ['Mie Goreng', 14000, 30000, 25],
                ['Spaghetti Bolognese', 20000, 42000, 20],
                ['Chicken Katsu Rice', 19000, 39000, 20],
                ['Roti Bakar Cokelat Keju', 11000, 27000, 25],
            ],
            'Snack' => [
                ['Kentang Goreng', 10000, 25000, 30],
                ['Pisang Goreng', 9000, 23000, 25],
                ['Tahu Crispy', 8000, 20000, 30],
                ['Cireng Bumbu Rujak', 9000, 23000, 25],
                ['Onion Ring', 10000, 25000, 25],
                ['Brownies', 11000, 26000, 20],
            ],
        ];

        foreach ($menu as $categoryName => $products) {
            $category = Category::firstOrCreate(['name' => $categoryName]);

            foreach ($products as [$name, $cogs, $price, $stock]) {
                Product::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $name],
                    [
                        'cogs' => $cogs,
                        'price' => $price,
                        'stock' => $stock,
                        'min_stock_alert' => 5,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
