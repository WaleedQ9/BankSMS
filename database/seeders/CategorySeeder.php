<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'القروض والالتزامات', 'icon' => '💳', 'color' => '#EF4444', 'budget' => 0],
            ['name' => 'مصروف أمي', 'icon' => '👩', 'color' => '#EC4899', 'budget' => 0],
            ['name' => 'مصروف الزوجة', 'icon' => '❤️', 'color' => '#F472B6', 'budget' => 0],
            ['name' => 'مقاضي المنزل', 'icon' => '🛒', 'color' => '#10B981', 'budget' => 0],
            ['name' => 'المطاعم والترفيه', 'icon' => '🍽️', 'color' => '#F59E0B', 'budget' => 0],
            ['name' => 'الوقود والسيارة', 'icon' => '⛽', 'color' => '#3B82F6', 'budget' => 0],
            ['name' => 'الصحة', 'icon' => '🏥', 'color' => '#14B8A6', 'budget' => 0],
            ['name' => 'ورد', 'icon' => '👶', 'color' => '#A78BFA', 'budget' => 0],
            ['name' => 'الكهرباء', 'icon' => '⚡', 'color' => '#FBBF24', 'budget' => 0],
            ['name' => 'الاتصالات', 'icon' => '📱', 'color' => '#6366F1', 'budget' => 0],
            ['name' => 'المناسبات', 'icon' => '🎁', 'color' => '#F97316', 'budget' => 0],
            ['name' => 'السفر', 'icon' => '✈️', 'color' => '#0EA5E9', 'budget' => 0],
            ['name' => 'الادخار', 'icon' => '💰', 'color' => '#059669', 'budget' => 0],
            ['name' => 'الطوارئ', 'icon' => '🚨', 'color' => '#DC2626', 'budget' => 0],
            ['name' => 'مصاريف شخصية', 'icon' => '🧑🏻‍🦱', 'color' => '#8B6F4E', 'budget' => 0],
            ['name' => 'صيدلية', 'icon' => '⚕️', 'color' => '#22D3EE', 'budget' => 0],
        ];

        foreach ($categories as $data) {
            $budget = $data['budget'];
            unset($data['budget']);

            $category = Category::firstOrCreate(['name' => $data['name']], $data);

            if ($budget > 0) {
                Budget::firstOrCreate(
                    ['category_id' => $category->id],
                    ['monthly_amount' => $budget]
                );
            }
        }
    }
}
