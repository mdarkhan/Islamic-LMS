<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_book_with_a_cover_and_purchase_links(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.books.store'), [
            'title' => 'সীরাতুন নবী',
            'author' => 'মাসউদ আলিমী',
            'publisher' => 'ইসলামিক ফাউন্ডেশন',
            'page_count' => 240,
            'is_published' => '1',
            'cover' => UploadedFile::fake()->image('cover.jpg'),
            'purchase_links' => [
                ['website_name' => 'Rokomari', 'url' => 'https://rokomari.com/book/1'],
                ['website_name' => 'Wafilife', 'url' => 'https://wafilife.com/book/1'],
                ['website_name' => '', 'url' => 'https://example.com/skip'],   // no name → dropped
            ],
        ])->assertRedirect();

        $book = Book::query()->where('title', 'সীরাতুন নবী')->firstOrFail();
        $this->assertTrue($book->is_published);
        $this->assertNotNull($book->cover_path);
        Storage::disk('public')->assertExists($book->cover_path);
        $this->assertSame(2, $book->purchaseLinks()->count(), 'labelless row dropped');
        $this->assertDatabaseHas('audit_logs', ['action' => 'book.created']);
    }

    public function test_replacing_a_cover_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $old = UploadedFile::fake()->image('old.jpg')->store('books', 'public');
        $book = Book::query()->create([
            'slug' => 'old-book', 'title' => 'পুরনো বই', 'author' => 'লেখক', 'cover_path' => $old,
        ]);

        $this->actingAs($admin)->put(route('admin.books.update', $book), [
            'title' => 'পুরনো বই', 'author' => 'লেখক',
            'cover' => UploadedFile::fake()->image('new.jpg'),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($book->fresh()->cover_path);
    }

    public function test_deleting_a_book_removes_its_cover_file(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $path = UploadedFile::fake()->image('cover.jpg')->store('books', 'public');
        $book = Book::query()->create([
            'slug' => 'to-delete', 'title' => 'মুছে ফেলা বই', 'author' => 'লেখক', 'cover_path' => $path,
        ]);

        $this->actingAs($admin)->delete(route('admin.books.destroy', $book))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_only_published_books_are_publicly_visible(): void
    {
        $published = Book::factory()->create(['is_published' => true]);
        $draft = Book::factory()->create(['is_published' => false]);

        $this->get(route('books.show', $published))->assertOk();
        $this->get(route('books.show', $draft))->assertNotFound();
    }

    public function test_books_are_ordered_1_based_and_reorder_with_arrows(): void
    {
        $admin = $this->makeAdmin();
        foreach (['ক বই', 'খ বই', 'গ বই'] as $title) {
            $this->actingAs($admin)->post(route('admin.books.store'), ['title' => $title, 'author' => 'লেখক']);
        }
        $first = Book::query()->where('title', 'ক বই')->first();
        $third = Book::query()->where('title', 'গ বই')->first();

        $this->assertSame(1, $first->sort_order);
        $this->assertSame(3, $third->sort_order);

        $this->actingAs($admin)->put(route('admin.books.move', [$third, 'up']))->assertRedirect();
        $this->assertSame(2, $third->fresh()->sort_order);
    }

    public function test_admin_book_pages_render(): void
    {
        $admin = $this->makeAdmin();
        $book = Book::factory()->create();

        $this->actingAs($admin)->get(route('admin.books.index'))->assertOk()->assertSee($book->title);
        $this->actingAs($admin)->get(route('admin.books.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.books.edit', $book))->assertOk()->assertSee($book->title);
    }

    public function test_a_student_cannot_manage_books(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.books.index'))->assertForbidden();
        $this->actingAs($this->makeStudent())->post(route('admin.books.store'), ['title' => 'x', 'author' => 'y'])->assertForbidden();
    }
}
