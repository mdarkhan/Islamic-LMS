<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class BookController extends Controller
{
    public function show(Book $book): View
    {
        abort_unless($book->is_published, 404);

        $book->load('purchaseLinks');

        return view('public.books.show', compact('book'));
    }
}
