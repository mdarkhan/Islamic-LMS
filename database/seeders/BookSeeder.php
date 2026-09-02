<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Support\Slug;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Placeholder catalogue so the homepage isn't empty before the admin uploads real
     * covers and purchase links. Idempotent (updateOrCreate by slug) like the other
     * content seeders — safe to re-run.
     */
    private const BOOKS = [
        'তাফসীরে ইবনে কাসীর (সংক্ষিপ্ত)',
        'সীরাতুন নবী ﷺ',
        'রিয়াদুস সালেহীন',
        'বুলুগুল মারাম',
        'ফিকহুস সুন্নাহ',
        'তাযকিয়াতুন নাফস',
        'আকীদাতুত তহাবী',
        'কুরআনের আলোকে জীবন গঠন',
        'হাদীসের গল্প শিক্ষার্থীদের জন্য',
        'যাকাত ও সাদাকার বিধান',
    ];

    public function run(): void
    {
        foreach (self::BOOKS as $order => $title) {
            Book::query()->updateOrCreate(
                ['slug' => Slug::make($title, 'book')],
                [
                    'title' => $title,
                    'author' => 'মাসউদ আলিমী',
                    'sort_order' => $order + 1,
                    'is_published' => true,
                ],
            );
        }
    }
}
