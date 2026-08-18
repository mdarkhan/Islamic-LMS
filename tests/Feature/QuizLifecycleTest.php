<?php

namespace Tests\Feature;

use App\Models\Quiz;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quiz lifecycle semantics (Phase 6, Part 3). Both `scheduled` and `published` are
 * window-governed: a quiz opens at starts_at and closes at ends_at with NO status
 * mutation — nothing depends on a cron flipping a flag. Server clock is authoritative.
 */
class QuizLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-02-01 10:00:00', 'Asia/Dhaka');
    }

    public function test_draft_is_never_open_or_visible(): void
    {
        $quiz = Quiz::factory()->create([
            'status' => Quiz::STATUS_DRAFT,
            'starts_at' => $this->now->subHour(),
            'ends_at' => $this->now->addHour(),
        ]);

        $this->assertFalse($quiz->isOpenAt($this->now));
        $this->assertFalse($quiz->isVisibleToStudents());
        $this->assertSame(Quiz::STATE_DRAFT, $quiz->officialState($this->now));
    }

    public function test_scheduled_quiz_opens_within_its_window_without_a_status_change(): void
    {
        $quiz = Quiz::factory()->create([
            'status' => Quiz::STATUS_SCHEDULED,
            'starts_at' => $this->now->subMinute(),
            'ends_at' => $this->now->addHour(),
        ]);

        // Still literally "scheduled" in the DB, yet open because the clock is inside
        // the window — no cron flipped it to published.
        $this->assertTrue($quiz->isOpenAt($this->now));
        $this->assertSame(Quiz::STATE_OPEN, $quiz->officialState($this->now));
        $this->assertTrue($quiz->isVisibleToStudents());
    }

    public function test_scheduled_quiz_before_start_is_upcoming_and_not_open(): void
    {
        $quiz = Quiz::factory()->create([
            'status' => Quiz::STATUS_SCHEDULED,
            'starts_at' => $this->now->addDay(),
            'ends_at' => $this->now->addDays(2),
        ]);

        $this->assertFalse($quiz->isOpenAt($this->now));
        $this->assertSame(Quiz::STATE_UPCOMING, $quiz->officialState($this->now));
    }

    public function test_published_quiz_past_its_end_is_closed(): void
    {
        $quiz = Quiz::factory()->create([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => $this->now->subDays(2),
            'ends_at' => $this->now->subDay(),
        ]);

        $this->assertFalse($quiz->isOpenAt($this->now));
        $this->assertSame(Quiz::STATE_CLOSED, $quiz->officialState($this->now));
    }

    public function test_published_quiz_without_a_window_is_always_open(): void
    {
        $quiz = Quiz::factory()->create([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->assertTrue($quiz->isOpenAt($this->now));
        $this->assertSame(Quiz::STATE_OPEN, $quiz->officialState($this->now));
    }

    public function test_archived_quiz_is_never_open_but_may_offer_practice(): void
    {
        $quiz = Quiz::factory()->practice()->create([
            'status' => Quiz::STATUS_ARCHIVED,
            'starts_at' => $this->now->subDays(2),
            'ends_at' => $this->now->subHour(),   // window ended → key safe → results released
        ]);

        $this->assertFalse($quiz->isOpenAt($this->now), 'no new official attempt on an archived quiz');
        $this->assertSame(Quiz::STATE_ARCHIVED, $quiz->officialState($this->now));
        $this->assertTrue($quiz->practiceAvailableAt($this->now));
        $this->assertTrue($quiz->isVisibleToStudents());
    }

    public function test_practice_stays_closed_while_the_official_key_is_still_secret(): void
    {
        // Archived, but the window has not ended yet → results not released → the answer
        // key is not safe to reveal, so practice must not open (brief §10).
        $quiz = Quiz::factory()->practice()->create([
            'status' => Quiz::STATUS_ARCHIVED,
            'starts_at' => $this->now->subHour(),
            'ends_at' => $this->now->addHour(),
        ]);

        $this->assertFalse($quiz->practiceAvailableAt($this->now));
    }

    public function test_practice_is_closed_during_a_live_official_window(): void
    {
        // A published quiz mid-window: a student must not be able to open practice and
        // read the answer key for an exam they can still sit.
        $quiz = Quiz::factory()->practice()->create([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => $this->now->subHour(),
            'ends_at' => $this->now->addHour(),
        ]);

        $this->assertTrue($quiz->isOpenAt($this->now));
        $this->assertFalse($quiz->practiceAvailableAt($this->now));
    }

    public function test_archived_without_practice_offers_no_practice(): void
    {
        $quiz = Quiz::factory()->create([
            'status' => Quiz::STATUS_ARCHIVED,
            'practice_enabled' => false,
            'starts_at' => $this->now->subDays(2),
            'ends_at' => $this->now->subHour(),
        ]);

        $this->assertFalse($quiz->practiceAvailableAt($this->now));
    }

    public function test_draft_never_offers_practice_even_if_flagged(): void
    {
        $quiz = Quiz::factory()->practice()->create(['status' => Quiz::STATUS_DRAFT]);

        $this->assertFalse($quiz->practiceAvailableAt($this->now));
    }
}
