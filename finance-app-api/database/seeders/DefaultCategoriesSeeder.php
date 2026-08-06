<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $expenseCategories = [
            ['name' => 'Makan & Minum', 'icon' => '🍔', 'sort_order' => 1],
            ['name' => 'Transport', 'icon' => '🚗', 'sort_order' => 2],
            ['name' => 'Belanja', 'icon' => '🛍️', 'sort_order' => 3],
            ['name' => 'Tagihan', 'icon' => '📱', 'sort_order' => 4],
            ['name' => 'Hiburan', 'icon' => '🎬', 'sort_order' => 5],
            ['name' => 'Kesehatan', 'icon' => '🏥', 'sort_order' => 6],
            ['name' => 'Pendidikan', 'icon' => '📚', 'sort_order' => 7],
            ['name' => 'Lainnya', 'icon' => '📦', 'sort_order' => 99],
        ];

        $incomeCategories = [
            ['name' => 'Gaji', 'icon' => '💰', 'sort_order' => 1],
            ['name' => 'Bonus/THR', 'icon' => '🎁', 'sort_order' => 2],
            ['name' => 'Freelance/Sampingan', 'icon' => '💻', 'sort_order' => 3],
            ['name' => 'Lainnya', 'icon' => '📦', 'sort_order' => 99],
        ];

        foreach ($expenseCategories as $category) {
            Category::firstOrCreate(
                [
                    'name' => $category['name'],
                    'type' => 'expense',
                    'user_id' => null,
                ],
                [
                    'icon' => $category['icon'],
                    'is_default' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );
        }

        foreach ($incomeCategories as $category) {
            Category::firstOrCreate(
                [
                    'name' => $category['name'],
                    'type' => 'income',
                    'user_id' => null,
                ],
                [
                    'icon' => $category['icon'],
                    'is_default' => true,
                    'sort_order' => $category['sort_order'],
                ]
            );
        }
    }
}
