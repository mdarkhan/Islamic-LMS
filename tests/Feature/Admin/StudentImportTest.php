<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Import\StudentImporter;
use App\Services\Import\StudentSpreadsheetParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(): string
    {
        return implode("\n", [
            "Roll No.,Name,Father's/Husband's Name,Password",
            '101,আব্দুল্লাহ,পিতা এক,pass101',
            '১০২,ফাতিমা,পিতা দুই,pass102',
            '102,ডুপ্লিকেট,পিতা,pass',      // duplicate of ১০২ once normalised
            ',নামহীন রোল,পিতা,pass',        // error: no roll
            '200,,পিতা,pass',              // error: no name
        ]);
    }

    public function test_preview_classifies_rows_without_writing(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        $file = UploadedFile::fake()->createWithContent('students.csv', $this->csv());

        $response = $this->actingAs($admin)->post(route('admin.students.import.preview'), ['file' => $file]);

        $response->assertOk();
        $summary = $response->viewData('summary');
        $this->assertSame(2, $summary['import']);      // 101, ১০২
        $this->assertSame(1, $summary['duplicate']);   // second 102
        $this->assertSame(2, $summary['error']);       // missing roll, missing name
        $this->assertSame(0, User::query()->students()->count(), 'preview writes no students');
        $this->assertDatabaseMissing('users', ['roll' => '101']);
    }

    public function test_confirm_imports_students_without_reusing_the_legacy_password(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        $file = UploadedFile::fake()->createWithContent('students.csv', $this->csv());
        $token = $this->actingAs($admin)->post(route('admin.students.import.preview'), ['file' => $file])->viewData('token');

        $response = $this->actingAs($admin)->post(route('admin.students.import.confirm'), ['token' => $token]);
        $response->assertRedirect(route('admin.students.index'));

        $this->assertSame(2, User::query()->students()->count());

        $student = User::query()->where('roll', '101')->first();
        $this->assertNotNull($student);
        // The compromised legacy password must NOT be a usable credential.
        $this->assertFalse(Hash::check('pass101', $student->password), 'legacy password is never reused');
        $this->assertTrue($student->force_password_change);
        $this->assertTrue($student->is_legacy_import);

        // A one-time credential download is offered.
        $response->assertSessionHas('credentials_download');

        // Temp upload removed after import.
        $this->assertCount(0, Storage::disk('local')->allFiles('imports/students'));
    }

    public function test_legacy_password_is_never_saved_or_reused_by_the_service(): void
    {
        $admin = $this->makeAdmin();

        $rows = [['row' => 2, 'roll' => '101', 'name' => 'আব্দুল্লাহ', 'guardian_name' => null, 'password' => 'legacy-secret']];
        $result = app(StudentImporter::class)->import($rows, $admin);

        $student = User::query()->where('roll', '101')->firstOrFail();
        $this->assertFalse(Hash::check('legacy-secret', $student->password), 'legacy password is not the account password');

        // The generated temporary password is returned for one-time delivery and
        // actually works as the login credential.
        $this->assertCount(1, $result['credentials']);
        $temp = $result['credentials'][0]['password'];
        $this->assertNotSame('legacy-secret', $temp);
        $this->assertTrue(Hash::check($temp, $student->password), 'the fresh temp password is the real credential');
    }

    public function test_credential_csv_downloads_once_then_is_deleted(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        $file = UploadedFile::fake()->createWithContent('students.csv', $this->csv());
        $token = $this->actingAs($admin)->post(route('admin.students.import.preview'), ['file' => $file])->viewData('token');
        $url = $this->actingAs($admin)->post(route('admin.students.import.confirm'), ['token' => $token])->getSession()->get('credentials_download');

        $this->assertNotNull($url);
        $this->assertCount(1, Storage::disk('local')->files('credential-exports'));

        $download = $this->actingAs($admin)->get($url);
        $download->assertOk();
        $download->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Delivered once, then gone.
        $this->assertCount(0, Storage::disk('local')->files('credential-exports'));
    }

    public function test_existing_rolls_are_skipped_not_overwritten(): void
    {
        $admin = $this->makeAdmin();
        $existing = $this->makeStudent(['roll' => '101', 'name' => 'পুরাতন', 'password' => Hash::make('original')]);

        $rows = [
            ['row' => 2, 'roll' => '101', 'name' => 'নতুন নাম', 'guardian_name' => null, 'password' => 'newpass'],
        ];
        $result = app(StudentImporter::class)->import($rows, $admin);

        $this->assertSame(0, $result['imported']);
        $this->assertSame('পুরাতন', $existing->fresh()->name, 'existing record untouched');
        $this->assertTrue(Hash::check('original', $existing->fresh()->password));
    }

    public function test_repeating_the_same_import_is_idempotent(): void
    {
        $admin = $this->makeAdmin();
        $rows = [['row' => 2, 'roll' => '১০১', 'name' => 'আব্দুল্লাহ', 'guardian_name' => null, 'password' => 'legacy']];

        $first = app(StudentImporter::class)->import($rows, $admin);
        $second = app(StudentImporter::class)->import($rows, $admin);

        $this->assertSame(1, $first['imported']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(1, User::query()->where('roll', '101')->count());
        $this->assertNotNull(User::query()->where('roll', '101')->value('legacy_source_key'));
    }

    public function test_import_summary_is_audited_without_row_detail(): void
    {
        $admin = $this->makeAdmin();
        $rows = [['row' => 2, 'roll' => '101', 'name' => 'ক', 'guardian_name' => null, 'password' => 'secret']];

        app(StudentImporter::class)->import($rows, $admin);

        $log = AuditLog::query()->where('action', 'students.imported')->first();
        $this->assertNotNull($log);
        $this->assertArrayHasKey('imported', $log->after);
        // No password, roll list, or name anywhere in the audit payload.
        $this->assertStringNotContainsString('secret', json_encode($log->after));
    }

    public function test_parser_reads_bengali_and_maps_legacy_headers(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('t.csv');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $this->csv());

        $rows = app(StudentSpreadsheetParser::class)->parse($path, 'csv');

        $this->assertSame('101', $rows[0]['roll']);
        $this->assertSame('আব্দুল্লাহ', $rows[0]['name']);
        $this->assertSame('পিতা এক', $rows[0]['guardian_name']);
    }
}
