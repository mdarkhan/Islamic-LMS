<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
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
