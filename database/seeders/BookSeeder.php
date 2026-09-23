<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class BookSeeder extends Seeder
{
    /**
     * The real catalogue (title, author byline, cover image). Source cover files live in
     * `database/seeders/assets/books/` (committed) — `storage/app/public/` is gitignored,
     * so seeding copies each one in rather than referencing it in place; a fresh
     * `migrate:fresh --seed` on any machine reproduces the real covers, not placeholders.
     */
    private const BOOKS = [
        ['title' => 'সালাফদের জীবনকথা', 'author' => 'শাইখ আব্দুল আযীয | শাইখ বাহাউদ্দীন উকাইল', 'cover' => 'salafder-jibonkotha.png'],
        ['title' => 'একটি মজার তাফসীর বলি', 'author' => 'মাসউদ আলিমী', 'cover' => 'ekti-mojar-tafsir-boli.png'],
        ['title' => 'ঈমান ভঙ্গের কারণ', 'author' => 'শাইখ সুলায়মান ইবনু নাসির আল উলওয়ান', 'cover' => 'iman-vonger-karon.png'],
        ['title' => 'ফিতনার দিনে নির্জনবাস', 'author' => 'ইমাম ইবনু আবিদ দুনিয়া | মাসউদ আলিমী', 'cover' => 'fitnar-dine-nirjonbash.png'],
        ['title' => 'সালাফদের ক্ষুধা', 'author' => 'ইমাম ইবনু আবিদ দুনিয়া | মাসউদ আলিমী', 'cover' => 'salafder-khuda.png'],
        ['title' => 'ধনসম্পদ ও পদমর্যাদার লোভ', 'author' => 'ইবনু রজব হাম্বলি | মাসউদ আলিমী', 'cover' => 'dhonsampad-o-podmoryadar-lov.png'],
        ['title' => 'আদ দুনিয়া- মুমিনের কারাগার, কাফিরের জান্নাত', 'author' => 'শাইখ মুহাম্মাদ আব্দুর রহমান ইওয়াদ | মাসউদ আলিমী', 'cover' => 'ad-duniya.png'],
        ['title' => 'কেন আমরা নামাজ পড়ি?', 'author' => 'ইসমাইল আল মুকাদ্দাম | মাসউদ আলিমী', 'cover' => 'keno-amra-namaz-pori.png'],
        ['title' => 'খুশুখুজু', 'author' => 'মাওলানা মুহাম্মাদ নুমান | রিফাত হাসান', 'cover' => 'khushukhuju.png'],
        ['title' => 'নবীজির পদাঙ্ক অনুসরণ', 'author' => 'ইবনু রজব হাম্বলি (রহিমাহুল্লাহ)', 'cover' => 'nobijir-podank-onushoron.png'],
    ];

    public function run(): void
    {
        $keptSlugs = [];

        foreach (self::BOOKS as $order => $row) {
            $slug = Slug::make($row['title'], 'book');
            $keptSlugs[] = $slug;

            $coverPath = 'books/'.$row['cover'];
            $sourcePath = __DIR__.'/assets/books/'.$row['cover'];
            if (! Storage::disk('public')->exists($coverPath) && is_file($sourcePath)) {
                Storage::disk('public')->put($coverPath, file_get_contents($sourcePath));
            }

            Book::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $row['title'],
                    'author' => $row['author'],
                    'cover_path' => $coverPath,
                    'sort_order' => $order + 1,
                    'is_published' => true,
                ],
            );
        }

        // Drop anything left over from an older catalogue (e.g. the original placeholder
        // titles) so re-running this seeder converges on exactly the list above.
        Book::query()->whereNotIn('slug', $keptSlugs)->get()->each(function (Book $book) {
            if ($book->cover_path) {
                Storage::disk('public')->delete($book->cover_path);
            }
            $book->delete();
        });
    }
}
