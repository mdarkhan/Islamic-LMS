<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Services\Integrity\IntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class ProductionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_point_ledger_drift_is_reported_without_repairing_it(): void
    {
        $student = $this->makeStudent();
        $student->forceFill(['points_balance' => 7])->save();

        $checks = app(IntegrityService::class)->inspect();

        $this->assertFalse($checks['point_ledger_drift']['ok']);
        $this->assertSame(1, $checks['point_ledger_drift']['count']);
        $this->assertSame(7, $student->fresh()->points_balance, 'verification is read-only');
    }

    public function test_quiz_total_marks_drift_is_reported(): void
    {
        $quiz = Quiz::factory()->create();
        QuizBuilder::for($quiz)->question(['A', 'B'], [1], marks: 3);
        $quiz->forceFill(['total_marks' => 99])->save();

        $checks = app(IntegrityService::class)->inspect();

        $this->assertFalse($checks['quiz_total_marks_drift']['ok']);
        $this->assertSame([['quiz_id' => $quiz->id, 'cached' => 99, 'calculated' => 3]], $checks['quiz_total_marks_drift']['details']);
        $this->assertSame(99, $quiz->fresh()->total_marks, 'verification does not silently repair');
    }
}
