<?php

namespace Database\Seeders;

use App\Models\PostCategory;
use App\Support\Slug;
use Illuminate\Database\Seeder;

/**
 * Seeds a sensible set of empty categories for admins to publish into. These are just
 * organisational buckets — NO articles, fatwas or rulings are fabricated (brief §60).
 * Admins fully manage categories afterwards.
 */
class PostCategorySeeder extends Seeder
{
    private const CATEGORIES = [
        'ফতোয়া', 'প্রশ্নোত্তর', 'সীরাত', 'তাফসির', 'আকীদাহ', 'ইবাদত', 'পরিবার', 'সাধারণ',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $i => $name) {
            PostCategory::query()->updateOrCreate(
                ['slug' => Slug::make($name, 'category')],
                ['name' => $name, 'is_active' => true, 'sort_order' => $i],
            );
        }
    }
}
