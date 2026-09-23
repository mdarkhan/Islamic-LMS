<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_security_headers_are_present_on_every_response(): void
    {
        $response = $this->get(route('home'));

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_is_only_sent_over_a_secure_request(): void
    {
        $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');

        $response = $this->get('https://localhost/');   // simulates an HTTPS request
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_csv_exports_neutralise_formula_injection_in_free_text_fields(): void
    {
        // A student's own "phone" field is free text (ProfileController::update) and
        // could carry a spreadsheet-formula payload; the admin export must not let it
        // execute as a formula when the CSV is opened in Excel/Sheets/LibreOffice.
        $this->makeStudent([
            'name' => "=cmd|' /C calc'!A0",
            'phone' => '+8801700000000',   // a legitimate leading "+" must ALSO be escaped
        ]);

        $csv = $this->actingAs($this->makeAdmin())
            ->get(route('admin.students.export'))
            ->streamedContent();

        $this->assertStringNotContainsString(",=cmd|", $csv);
        $this->assertStringContainsString("'=cmd|", $csv);
        $this->assertStringContainsString("'+8801700000000", $csv);
    }
    public function test_runtime_code_has_no_legacy_sheet_or_api_dependency(): void
    {
        $files = [];
        foreach (['app', 'routes', 'resources/js'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($directory)));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('docs.google.com/spreadsheets', $content, $file);
            $this->assertStringNotContainsString('STUDENT_SHEET_ID', $content, $file);
            $this->assertStringNotContainsString('QUIZ_SHEET_ID', $content, $file);
            $this->assertStringNotContainsString('/quiz-api.php', $content, $file);
        }
    }

    public function test_sensitive_migration_artifacts_are_private_and_ignored(): void
    {
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $ignore = file_get_contents(base_path('.gitignore'));
        $this->assertStringContainsString('/legacy/db/', $ignore);
        $this->assertStringContainsString('/quiz-api.php', $ignore);
        $this->assertStringContainsString('/storage/app/private/credential-exports/', $ignore);
    }
}
