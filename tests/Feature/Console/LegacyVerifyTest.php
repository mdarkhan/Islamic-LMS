<?php

namespace Tests\Feature\Console;

use App\Services\Import\LegacyCourseImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_course_import_passes_read_only_verification(): void
    {
        app(LegacyCourseImporter::class)->import(base_path('legacy/extracted/course-data.json'));

        $this->artisan('legacy:verify')
            ->expectsOutputToContain('Legacy result migration tooling: READY')
            ->assertSuccessful();
    }
}
