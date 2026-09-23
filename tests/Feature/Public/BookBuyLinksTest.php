<?php

namespace Tests\Feature\Public;

use App\Models\Book;
use Database\Seeders\BookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookBuyLinksTest extends TestCase
{
    use RefreshDatabase;

    private function bookWithLinks(): Book
    {
        $book = Book::factory()->create(['is_published' => true]);
        $book->purchaseLinks()->create(['website_name' => 'Rokomari', 'url' => 'https://www.rokomari.com/book/1/x', 'sort_order' => 0]);
        $book->purchaseLinks()->create(['website_name' => 'Wafilife', 'url' => 'https://www.wafilife.com/x/pd/1', 'sort_order' => 1]);

        return $book;
    }

    public function test_the_homepage_shows_each_stores_logo_and_link_on_the_book_card(): void
    {
        $this->bookWithLinks();

        $this->get(route('home'))->assertOk()
            ->assertSee('images/brands/rokomari.png', false)
            ->assertSee('images/brands/wafilife.svg', false)
            ->assertSee('https://www.rokomari.com/book/1/x', false)
            ->assertSee('https://www.wafilife.com/x/pd/1', false);
    }

    public function test_the_book_page_shows_the_buy_links_up_front(): void
    {
        $book = $this->bookWithLinks();

        $this->get(route('books.show', $book))->assertOk()
            ->assertSee('images/brands/rokomari.png', false)
            ->assertSee('https://www.wafilife.com/x/pd/1', false);
    }

    public function test_an_unknown_store_falls_back_to_its_name_without_a_logo(): void
    {
        $book = Book::factory()->create(['is_published' => true]);
        $book->purchaseLinks()->create(['website_name' => 'Boi Bazar', 'url' => 'https://example.test/b', 'sort_order' => 0]);

        $this->get(route('books.show', $book))->assertOk()->assertSee('Boi Bazar');
    }

    public function test_the_seeded_catalogue_has_both_store_links_for_every_book(): void
    {
        Storage::fake('public');
        $this->seed(BookSeeder::class);

        $books = Book::query()->with('purchaseLinks')->get();
        $this->assertCount(10, $books);

        foreach ($books as $book) {
            $stores = $book->purchaseLinks->pluck('website_name')->all();
            $this->assertSame(['Rokomari', 'Wafilife'], $stores, $book->title);
        }
    }
}
