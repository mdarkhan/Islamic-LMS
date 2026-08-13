<?php

namespace Tests\Feature\Admin;

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

    public function test_confirm_imports_new_rows_and_hashes_passwords(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();

        $file = UploadedFile::fake()->createWithContent('students.csv', $this->csv());
        $token = $this->actingAs($admin)->post(route('admin.students.import.preview'), ['file' => $file])->viewData('token');

        $this->actingAs($admin)->post(route('admin.students.import.confirm'), ['token' => $token])
            ->assertRedirect(route('admin.students.index'));

        $this->assertSame(2, User::query()->students()->count());

        $student = User::query()->where('roll', '101')->first();
        $this->assertNotNull($student);
        $this->assertTrue(Hash::check('pass101', $student->password), 'plaintext hashed on import');
        $this->assertTrue($student->force_password_change, 'legacy passwords are treated as compromised');
        $this->assertTrue($student->is_legacy_import);

        // Temp file removed after import.
        $this->assertCount(0, Storage::disk('local')->allFiles('imports/students'));
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

    public function test_import_summary_is_audited_without_row_detail(): void
    {
        $admin = $this->makeAdmin();
        $rows = [['row' => 2, 'roll' => '101', 'name' => 'ক', 'guardian_name' => null, 'password' => 'secret']];

        app(StudentImporter::class)->import($rows, $admin);

        $log = \App\Models\AuditLog::query()->where('action', 'students.imported')->first();
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
