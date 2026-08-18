<?php

namespace Tests\Feature;

use App\Models\LegacyImportBatch;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Import\LegacyQuizMapping;
use App\Services\Import\LegacyResultImporter;
use App\Services\Import\LegacyResultParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyResultImportTest extends TestCase
{
    use RefreshDatabase;

    private function batch(): LegacyImportBatch
    {
        return LegacyImportBatch::create([
            'type' => LegacyImportBatch::TYPE_RESULTS,
            'source_name' => 'quiz_submissions.csv',
            'source_sha256' => str_repeat('a', 64),
            'status' => LegacyImportBatch::STATUS_RUNNING,
        ]);
    }

    private function row(array $overrides = []): array
    {
        $row = array_merge([
            'row' => 2,
            'legacy_quiz_id' => 'seerat-24',
            'roll' => '101',
            'name' => 'Student',
            'guardian_name' => 'Guardian',
            'score' => 8,
            'total_questions' => 10,
            'time_taken' => 125,
            'created_at' => '2026-01-10 10:30:00',
        ], $overrides);
        $row['source_key'] = app(LegacyResultParser::class)->sourceKey($row);

        return $row;
    }

    public function test_exact_slug_mapping_is_high_confidence_but_still_requires_operator_approval(): void
    {
        $quiz = Quiz::factory()->create(['slug' => 'seerat-24', 'title' => 'সীরাত ২৪']);

        $mapping = app(LegacyQuizMapping::class)->build(['seerat-24']);

        $this->assertSame('REVIEW', $mapping[0]['action']);
        $this->assertSame('1.00', $mapping[0]['confidence']);
        $this->assertSame($quiz->id, $mapping[0]['matched_quiz_id']);
    }

    public function test_ambiguous_exact_title_requires_review(): void
    {
        Quiz::factory()->create(['slug' => 'legacy-a', 'title' => 'একই পরীক্ষা']);
        Quiz::factory()->create(['slug' => 'legacy-b', 'title' => 'একই পরীক্ষা']);

        $mapping = app(LegacyQuizMapping::class)->build(['একই পরীক্ষা']);

        $this->assertSame('REVIEW', $mapping[0]['action']);
        $this->assertNull($mapping[0]['matched_quiz_id']);
    }

    public function test_missing_user_is_reported_and_not_importable(): void
    {
        Quiz::factory()->create(['slug' => 'seerat-24']);
        $mapping = $this->approvedMapping(['seerat-24']);

        $preview = app(LegacyResultImporter::class)->preview([$this->row()], $mapping);

        $this->assertSame('missing_user', $preview['rows'][0]['status']);
        $this->assertSame(1, $preview['summary']['missing_user']);
    }

    public function test_natural_language_timestamp_is_rejected_instead_of_guessed(): void
    {
        $this->makeStudent(['roll' => '101']);
        Quiz::factory()->create(['slug' => 'seerat-24']);
        $mapping = app(LegacyQuizMapping::class)->keyByLegacyId(
            app(LegacyQuizMapping::class)->build(['seerat-24'])
        );

        $preview = app(LegacyResultImporter::class)->preview([$this->row(['created_at' => 'tomorrow'])], $mapping);

        $this->assertSame('error', $preview['rows'][0]['status']);
        $this->assertStringContainsString('created_at', implode(' ', $preview['rows'][0]['errors']));
    }

    public function test_import_preserves_legacy_result_without_answers_or_points_and_is_idempotent(): void
    {
        $student = $this->makeStudent(['roll' => '101']);
        $quiz = Quiz::factory()->create(['slug' => 'seerat-24', 'title' => 'সীরাত ২৪']);
        $mapping = $this->approvedMapping(['seerat-24']);
        $batch = $this->batch();
        $row = $this->row();

        $first = app(LegacyResultImporter::class)->import([$row], $mapping, $batch);
        $second = app(LegacyResultImporter::class)->import([$row], $mapping, $batch);

        $this->assertSame(1, $first['imported']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(1, QuizAttempt::query()->count(), 'repeat import creates no duplicate');

        $attempt = QuizAttempt::query()->firstOrFail();
        $this->assertSame($student->id, $attempt->user_id);
        $this->assertSame($quiz->id, $attempt->quiz_id);
        $this->assertTrue($attempt->is_legacy_import);
        $this->assertFalse($attempt->answer_details_available);
        $this->assertSame('seerat-24', $attempt->legacy_quiz_id);
        $this->assertSame(8, $attempt->final_score);
        $this->assertSame(10, $attempt->total_marks_snapshot);
        $this->assertSame(125, $attempt->time_taken_seconds);
        $this->assertSame(0, $attempt->answers()->count());
        $this->assertNull($attempt->point_transaction_id);
        $this->assertSame(0, PointTransaction::query()->count(), 'historical import never creates point movements');
    }

    public function test_stale_auto_match_action_is_not_importable(): void
    {
        $this->makeStudent(['roll' => '101']);
        Quiz::factory()->create(['slug' => 'seerat-24']);
        $mappingRows = app(LegacyQuizMapping::class)->build(['seerat-24']);
        $mappingRows[0]['action'] = 'AUTO_MATCH';

        $preview = app(LegacyResultImporter::class)->preview(
            [$this->row()],
            app(LegacyQuizMapping::class)->keyByLegacyId($mappingRows),
        );

        $this->assertSame('review', $preview['rows'][0]['status']);
        $this->assertSame(1, $preview['summary']['review']);
    }

    public function test_owner_skip_actions_are_explicit_skips_not_blocking_review(): void
    {
        $this->makeStudent(['roll' => '101']);
        Quiz::factory()->create(['slug' => 'seerat-24']);

        // Every owner "do not import" action must classify as skip, never as blocking review.
        foreach (['SKIP', 'SKIP_FOR_NOW', 'OWNER_INPUT_REQUIRED'] as $action) {
            $rows = app(LegacyQuizMapping::class)->build(['seerat-24']);
            $rows[0]['action'] = $action;

            $preview = app(LegacyResultImporter::class)->preview(
                [$this->row()],
                app(LegacyQuizMapping::class)->keyByLegacyId($rows),
            );

            $this->assertSame('skip', $preview['rows'][0]['status'], "{$action} should be an explicit skip");
            $this->assertSame(0, $preview['summary']['review'], "{$action} must not block as review");
            $this->assertSame(1, $preview['summary']['skip']);
        }
    }

    public function test_reviewed_partial_import_imports_approved_and_skips_owner_skips(): void
    {
        $student = $this->makeStudent(['roll' => '101']);
        $approvedQuiz = Quiz::factory()->create(['slug' => 'seerat-24', 'title' => 'সীরাত ২৪']);

        // seerat-24 APPROVED (imports); seerat-01 SKIP_FOR_NOW (no source, explicitly deferred).
        $rows = app(LegacyQuizMapping::class)->build(['seerat-24', 'seerat-01']);
        foreach ($rows as &$r) {
            $r['action'] = $r['legacy_quiz_id'] === 'seerat-24' ? 'APPROVED' : 'SKIP_FOR_NOW';
        }
        unset($r);
        $mapping = app(LegacyQuizMapping::class)->keyByLegacyId($rows);

        $input = [
            $this->row(['legacy_quiz_id' => 'seerat-24']),
            $this->row(['row' => 3, 'legacy_quiz_id' => 'seerat-01', 'score' => 5]),
        ];

        $result = app(LegacyResultImporter::class)->import($input, $mapping, $this->batch());

        // A partial migration proceeds: the approved row imports, the deferred one is skipped,
        // and nothing blocks. No source data was edited to achieve this.
        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, QuizAttempt::query()->count());
        $this->assertSame($approvedQuiz->id, QuizAttempt::query()->firstOrFail()->quiz_id);
    }

    /** @param array<int, string> $legacyIds */
    private function approvedMapping(array $legacyIds): array
    {
        $rows = app(LegacyQuizMapping::class)->build($legacyIds);
        foreach ($rows as &$row) {
            $row['action'] = 'APPROVED';
        }
        unset($row);

        return app(LegacyQuizMapping::class)->keyByLegacyId($rows);
    }
}
