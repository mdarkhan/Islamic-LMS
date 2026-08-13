<?php

namespace Tests\Feature\Admin;

use App\Services\Import\CredentialExport;
use App\Services\Import\ImportFileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_deletes_stale_files_and_keeps_fresh_ones(): void
    {
        Storage::fake('local');
        $store = app(ImportFileStore::class);

        $stale = $store->put('students', UploadedFile::fake()->createWithContent('a.csv', 'x'));
        $fresh = $store->put('students', UploadedFile::fake()->createWithContent('b.csv', 'y'));

        // Age the first file beyond the 60-minute TTL.
        touch(Storage::disk('local')->path('imports/students/'.$stale), now()->subHours(2)->getTimestamp());

        $deleted = $store->prune('students');

        $this->assertSame(1, $deleted);
        $this->assertNull($store->path('students', $stale), 'stale file removed');
        $this->assertNotNull($store->path('students', $fresh), 'fresh file kept');
    }

    public function test_cleanup_command_sweeps_imports_and_credential_exports(): void
    {
        Storage::fake('local');
        $store = app(ImportFileStore::class);
        $credentials = app(CredentialExport::class);

        $token = $store->put('quizzes', UploadedFile::fake()->createWithContent('q.csv', 'x'));
        touch(Storage::disk('local')->path('imports/quizzes/'.$token), now()->subHours(2)->getTimestamp());

        $credToken = $credentials->store([['roll' => '1', 'name' => 'ক', 'password' => 'pw']]);
        touch(Storage::disk('local')->path('credential-exports/'.$credToken.'.csv'), now()->subHours(2)->getTimestamp());

        $this->artisan('imports:cleanup')->assertSuccessful();

        $this->assertNull($store->path('quizzes', $token));
        $this->assertNull($credentials->path($credToken));
    }

    public function test_preview_opportunistically_prunes_stale_files(): void
    {
        Storage::fake('local');
        $store = app(ImportFileStore::class);
        $admin = $this->makeAdmin();

        // A stale abandoned upload from a previous preview.
        $stale = $store->put('students', UploadedFile::fake()->createWithContent('old.csv', 'Roll No.,Name\n1,ক'));
        touch(Storage::disk('local')->path('imports/students/'.$stale), now()->subHours(2)->getTimestamp());

        $csv = "Roll No.,Name\n101,আব্দুল্লাহ";
        $this->actingAs($admin)->post(route('admin.students.import.preview'), [
            'file' => UploadedFile::fake()->createWithContent('new.csv', $csv),
        ])->assertOk();

        // The stale one is gone; only the just-uploaded file remains.
        $this->assertNull($store->path('students', $stale));
        $this->assertCount(1, Storage::disk('local')->files('imports/students'));
    }
}
