<?php

namespace Database\Seeders;

use App\Services\Import\LegacyCourseImporter;
use Illuminate\Database\Seeder;

class LegacyCourseSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('legacy/extracted/course-data.json');

        if (! is_file($path)) {
            $this->command?->warn("Skipping: {$path} not found.");

            return;
        }

        $report = app(LegacyCourseImporter::class)->import($path);

        $this->command?->info(sprintf(
            'Imported %d courses, %d lessons, %d resources.',
            $report['courses'], $report['lessons'], $report['resources'],
        ));

        $gaps = $report['gaps'];
        $this->command?->warn('Content gaps carried forward (not invented — see MIGRATION.md §3):');
        $this->command?->line('  placeholder descriptions   : '.count($gaps['placeholder_descriptions']));
        $this->command?->line('  lessons without a real date: '.count($gaps['missing_dates']));
        $this->command?->line('  lessons without resources  : '.count($gaps['lessons_without_resources']));
        $this->command?->line('  lessons without syllabus   : '.$gaps['lessons_without_syllabus']);
        $this->command?->line('  resources with no URL      : '.$gaps['resources_without_url']);
        $this->command?->line('  courses with no lessons    : '.implode(', ', $gaps['empty_courses'] ?: ['none']));
    }
}
