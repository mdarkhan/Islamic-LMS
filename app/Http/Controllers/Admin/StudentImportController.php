<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Import\StudentImporter;
use App\Services\Import\StudentSpreadsheetParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Upload → Parse → Preview → Confirm.
 *
 * The uploaded file is kept in a private temp location between preview and confirm
 * (not the session, which would carry plaintext passwords), and is deleted the
 * moment the import commits.
 */
class StudentImportController extends Controller
{
    private const DISK = 'local';
    private const DIR = 'imports/students';

    public function __construct(
        private readonly StudentSpreadsheetParser $parser,
        private readonly StudentImporter $importer,
    ) {}

    public function form(): View
    {
        return view('admin.students.import');
    }

    public function preview(Request $request): View
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ], [], ['file' => 'ফাইল']);

        $ext = strtolower($request->file('file')->getClientOriginalExtension() ?: 'csv');
        $token = Str::uuid()->toString().'.'.$ext;
        $request->file('file')->storeAs(self::DIR, $token, self::DISK);

        try {
            $rows = $this->parser->parse(Storage::disk(self::DISK)->path(self::DIR.'/'.$token), $ext);
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete(self::DIR.'/'.$token);

            return view('admin.students.import', ['parseError' => $e->getMessage()]);
        }

        $preview = $this->importer->preview($rows);

        return view('admin.students.import-preview', [
            'token' => $token,
            'rows' => $preview['rows'],
            'summary' => $preview['summary'],
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        // Constrain the token to a stored file we created; never trust a path.
        $relative = self::DIR.'/'.basename($data['token']);

        if (! Storage::disk(self::DISK)->exists($relative)) {
            return redirect()->route('admin.students.import.form')
                ->with('error', 'ইমপোর্ট ফাইলের মেয়াদ শেষ হয়ে গেছে। অনুগ্রহ করে আবার আপলোড করুন।');
        }

        $ext = pathinfo($relative, PATHINFO_EXTENSION);

        try {
            $rows = $this->parser->parse(Storage::disk(self::DISK)->path($relative), $ext);
            $result = $this->importer->import($rows, $request->user());
        } finally {
            Storage::disk(self::DISK)->delete($relative);   // plaintext leaves disk immediately
        }

        return redirect()->route('admin.students.index')->with(
            'success',
            "{$result['imported']} জন শিক্ষার্থী ইমপোর্ট করা হয়েছে, {$result['skipped']} টি বাদ দেওয়া হয়েছে।",
        );
    }
}
