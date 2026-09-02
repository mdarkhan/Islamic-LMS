<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Book;
use App\Services\Audit\AuditLogger;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BookController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $books = Book::query()
            ->withCount('purchaseLinks')
            ->orderBy('sort_order')
            ->get();

        return view('admin.books.index', compact('books'));
    }

    public function create(): View
    {
        return view('admin.books.create');
    }

    public function store(BookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $book = Book::query()->create([
                'slug' => $this->uniqueSlug($data['slug'] ?? '' ?: $data['title']),
                'title' => $data['title'],
                'author' => $data['author'],
                'publisher' => $data['publisher'] ?? null,
                'page_count' => $data['page_count'] ?? null,
                'details' => $data['details'] ?? null,
                'cover_path' => $request->file('cover')?->store('books', 'public'),
                'is_published' => $request->boolean('is_published'),
                'sort_order' => (int) Book::query()->max('sort_order') + 1,
            ]);

            $this->syncPurchaseLinks($book, $request->input('purchase_links', []));

            return $book;
        });

        $this->audit->log('book.created', $book, after: $book->only(['slug', 'title', 'is_published']));

        return redirect()->route('admin.books.edit', $book)->with('success', 'বই তৈরি করা হয়েছে।');
    }

    public function edit(Book $book): View
    {
        $book->load('purchaseLinks');

        return view('admin.books.edit', compact('book'));
    }

    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $before = $book->only(['slug', 'title', 'author', 'is_published']);
        $oldCoverPath = $book->cover_path;

        DB::transaction(function () use ($request, $book) {
            $data = $request->validated();

            $slugSource = $request->filled('slug') ? $request->input('slug') : $data['title'];

            $book->fill([
                'slug' => $this->uniqueSlug($slugSource, $book->getKey()),
                'title' => $data['title'],
                'author' => $data['author'],
                'publisher' => $data['publisher'] ?? null,
                'page_count' => $data['page_count'] ?? null,
                'details' => $data['details'] ?? null,
                'is_published' => $request->boolean('is_published'),
            ]);

            if ($request->hasFile('cover')) {
                $book->cover_path = $request->file('cover')->store('books', 'public');
            }

            $book->save();

            $this->syncPurchaseLinks($book, $request->input('purchase_links', []));
        });

        // Only remove the old file once the new one is safely saved, and only if it changed.
        if ($oldCoverPath !== null && $oldCoverPath !== $book->cover_path) {
            Storage::disk('public')->delete($oldCoverPath);
        }

        $this->audit->log('book.updated', $book,
            before: $before,
            after: $book->only(['slug', 'title', 'author', 'is_published']),
        );

        return redirect()->route('admin.books.edit', $book)->with('success', 'বই হালনাগাদ করা হয়েছে।');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->audit->log('book.deleted', $book, before: $book->only(['slug', 'title']));

        if ($book->cover_path !== null) {
            Storage::disk('public')->delete($book->cover_path);
        }

        $book->delete();   // purchase links cascade
        $this->resequence();

        return redirect()->route('admin.books.index')->with('success', 'বই মুছে ফেলা হয়েছে।');
    }

    /**
     * Move a book one step up or down by swapping its sort_order with its neighbour.
     * Ordering is 1-based and unique, so the neighbour is always well defined.
     */
    public function move(Book $book, string $direction): RedirectResponse
    {
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $neighbour = Book::query()
            ->where('sort_order', $direction === 'up' ? '<' : '>', $book->sort_order)
            ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if ($neighbour !== null) {
            DB::transaction(function () use ($book, $neighbour) {
                $original = $book->sort_order;
                $book->forceFill(['sort_order' => $neighbour->sort_order])->save();
                $neighbour->forceFill(['sort_order' => $original])->save();
            });
        }

        return back();
    }

    public function togglePublish(Book $book): RedirectResponse
    {
        $book->forceFill(['is_published' => ! $book->is_published])->save();

        $this->audit->log('book.updated', $book, after: ['is_published' => $book->is_published]);

        return back()->with('success', $book->is_published ? 'বই প্রকাশ করা হয়েছে।' : 'বই আড়াল করা হয়েছে।');
    }

    /**
     * Replace the book's purchase links from the submitted rows. A row with no website
     * name is dropped.
     */
    private function syncPurchaseLinks(Book $book, array $rows): void
    {
        $book->purchaseLinks()->delete();

        $order = 0;
        foreach ($rows as $row) {
            $websiteName = trim((string) ($row['website_name'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));

            if ($websiteName === '' || $url === '') {
                continue;
            }

            $book->purchaseLinks()->create([
                'website_name' => $websiteName,
                'url' => $url,
                'sort_order' => $order++,
            ]);
        }
    }

    /** Renumber every book to a clean 1..N sequence in its current order. */
    private function resequence(): void
    {
        $position = 1;

        foreach (Book::query()->orderBy('sort_order')->orderBy('id')->get() as $book) {
            if ($book->sort_order !== $position) {
                $book->forceFill(['sort_order' => $position])->save();
            }
            $position++;
        }
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        return Slug::unique(
            $source,
            fn (string $slug) => Book::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists(),
            'book',
        );
    }
}
