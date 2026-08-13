<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Import\CredentialExport;
use App\Services\Import\ImportFileStore;
use App\Services\Import\StudentImporter;
use App\Services\Import\StudentSpreadsheetParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Upload → Parse → Preview → Confirm.
 *
 * The uploaded file is kept in a private temp location between preview and confirm
 * (not the session, which would carry data), deleted the moment the import commits,
 * and swept by a TTL if the admin abandons the preview. On success the admin gets a
 * one-time credential CSV (fresh temporary passwords) that is deleted after download.
 */
class StudentImportController extends Controller
{
    private const KIND = 'students';

    public function __construct(
        private readonly StudentSpreadsheetParser $parser,
        private readonly StudentImporter $importer,
        private readonly ImportFileStore $files,
        private readonly CredentialExport $credentials,
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

        // Opportunistic sweep of anything abandoned by an earlier preview.
        $this->files->prune(self::KIND);

        $token = $this->files->put(self::KIND, $request->file('file'));

        try {
            $rows = $this->parser->parse($this->files->path(self::KIND, $token), $this->files->extension($token));
        } catch (\Throwable $e) {
            $this->files->delete(self::KIND, $token);

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
        $data = $request->validate(['token' => ['required', 'string']]);

        $path = $this->files->path(self::KIND, $data['token']);

        if ($path === null) {
            return redirect()->route('admin.students.import.form')
                ->with('error', 'ইমপোর্ট ফাইলের মেয়াদ শেষ হয়ে গেছে। অনুগ্রহ করে আবার আপলোড করুন।');
        }

        try {
            $rows = $this->parser->parse($path, $this->files->extension($data['token']));
            $result = $this->importer->import($rows, $request->user());
        } finally {
            $this->files->delete(self::KIND, $data['token']);
        }

        $flash = redirect()->route('admin.students.index')->with(
            'success',
            "{$result['imported']} জন শিক্ষার্থী ইমপোর্ট করা হয়েছে, {$result['skipped']} টি বাদ দেওয়া হয়েছে।",
        );

        // Hand the admin a one-time credential CSV to deliver the temporary passwords.
        if ($result['credentials'] !== []) {
            $credentialToken = $this->credentials->store($result['credentials']);
            $flash->with('credentials_download', route('admin.students.import.credentials', $credentialToken));
        }

        return $flash;
    }

    /**
     * Serve the one-time credential CSV, then delete it immediately (downloadable
     * once). Deletion is explicit — not reliant on deleteFileAfterSend — so the
     * plaintext never lingers even if the client aborts mid-stream.
     */
    public function downloadCredentials(string $token): Response
    {
        $this->credentials->cleanup();   // opportunistic TTL sweep

        $path = $this->credentials->path($token);
        abort_if($path === null, 404);

        $content = file_get_contents($path);
        $this->credentials->delete($token);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="temporary-credentials.csv"',
        ]);
    }
}
