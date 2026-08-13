<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Services\Import\ImportFileStore;
use App\Services\Import\SpreadsheetReader;
use App\Services\Quiz\QuizCourseMatcher;
use App\Services\Quiz\QuizImporter;
use App\Services\Quiz\QuizImportParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quiz import: Upload → (select worksheet) → Preview → Confirm.
 *
 * The uploaded workbook contains answer keys, so it is treated as sensitive: private
 * storage, randomised name, TTL sweep, deleted on commit (ImportFileStore). The file
 * is re-parsed at confirm time — the answer key is derived server-side from the
 * file, never trusted from the posted preview.
 */
class QuizImportController extends Controller
{
    private const KIND = 'quizzes';

    public function __construct(
        private readonly ImportFileStore $files,
        private readonly SpreadsheetReader $reader,
        private readonly QuizImportParser $parser,
        private readonly QuizCourseMatcher $matcher,
        private readonly QuizImporter $importer,
    ) {}

    public function form(): View
    {
        return view('admin.quizzes.import.form');
    }

    public function upload(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ], [], ['file' => 'ফাইল']);

        $this->files->prune(self::KIND);
        $token = $this->files->put(self::KIND, $request->file('file'));
        $ext = $this->files->extension($token);

        $sheets = $this->reader->sheetNames($this->files->path(self::KIND, $token), $ext);

        // One sheet → straight to preview; several → let the admin choose.
        if (count($sheets) <= 1) {
            return $this->renderPreview($token, $sheets[0] ?? 'CSV');
        }

        return view('admin.quizzes.import.sheet', ['token' => $token, 'sheets' => $sheets]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'sheet' => ['required', 'string'],
        ]);

        return $this->renderPreview($data['token'], $data['sheet']);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'sheet' => ['required', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'point_cost' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        // A chosen lesson must belong to the chosen course.
        if (($data['lesson_id'] ?? null) && ($data['course_id'] ?? null)
            && ! Lesson::query()->whereKey($data['lesson_id'])->where('course_id', $data['course_id'])->exists()) {
            return back()->withErrors(['lesson_id' => 'নির্বাচিত ক্লাসটি নির্বাচিত কোর্সের অন্তর্ভুক্ত নয়।'])->withInput();
        }

        $path = $this->files->path(self::KIND, $data['token']);
        if ($path === null) {
            return redirect()->route('admin.quizzes.import.form')
                ->with('error', 'ইমপোর্ট ফাইলের মেয়াদ শেষ হয়ে গেছে। অনুগ্রহ করে আবার আপলোড করুন।');
        }

        // Re-parse from the file — never trust posted question/answer data.
        $rows = $this->reader->rows($path, $this->files->extension($data['token']), $data['sheet']);
        $parsed = $this->parser->parse($rows, $data['sheet']);

        if ($parsed['has_fatal']) {
            return back()->with('error', 'ইমপোর্টে ত্রুটি আছে — অনুগ্রহ করে সংশোধন করে আবার চেষ্টা করুন।')->withInput();
        }

        try {
            $quiz = $this->importer->import($parsed, [
                'title' => $data['title'],
                'course_id' => $data['course_id'] ?? null,
                'lesson_id' => $data['lesson_id'] ?? null,
                'point_cost' => $data['point_cost'],
            ], $request->user());
        } finally {
            $this->files->delete(self::KIND, $data['token']);
        }

        return redirect()->route('admin.quizzes.edit', $quiz)
            ->with('success', 'কুইজ ইমপোর্ট হয়েছে (খসড়া হিসেবে)। প্রকাশের আগে যাচাই করে নিন।');
    }

    private function renderPreview(string $token, string $sheet): View|RedirectResponse
    {
        $path = $this->files->path(self::KIND, $token);
        if ($path === null) {
            return redirect()->route('admin.quizzes.import.form')
                ->with('error', 'ইমপোর্ট ফাইলের মেয়াদ শেষ হয়ে গেছে। অনুগ্রহ করে আবার আপলোড করুন।');
        }

        try {
            $rows = $this->reader->rows($path, $this->files->extension($token), $sheet);
            $parsed = $this->parser->parse($rows, $sheet);
        } catch (\Throwable $e) {
            $this->files->delete(self::KIND, $token);

            return view('admin.quizzes.import.form', ['parseError' => $e->getMessage()]);
        }

        $suggestion = $this->matcher->suggest($sheet, $parsed['config']['title']);

        // Duplicate detection (Part 21): a same-title quiz already exists.
        $duplicate = Quiz::query()->where('title', $parsed['config']['title'])->first();

        return view('admin.quizzes.import.preview', [
            'token' => $token,
            'sheet' => $sheet,
            'parsed' => $parsed,
            'suggestion' => $suggestion,
            'duplicate' => $duplicate,
            'courses' => Course::query()->orderBy('sort_order')->get(),
            'lessons' => Lesson::query()->orderBy('course_id')->orderBy('sort_order')->get(['id', 'course_id', 'title']),
        ]);
    }
}
