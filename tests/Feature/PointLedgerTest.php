<?php

namespace Tests\Feature;

use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Points\InsufficientPointsException;
use App\Services\Points\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PointLedgerTest extends TestCase
{
    use RefreshDatabase;

    private PointService $points;

    protected function setUp(): void
    {
        parent::setUp();
        $this->points = app(PointService::class);
    }

    public function test_credit_increases_balance_and_writes_a_ledger_row(): void
    {
        $user = User::factory()->create();

        $tx = $this->points->credit($user, 5, reason: 'Admin grant');

        $this->assertSame(5, $user->fresh()->points_balance);
        $this->assertSame(5, $tx->amount);
        $this->assertSame(5, $tx->balance_after);
        $this->assertSame(PointTransaction::TYPE_GRANT, $tx->type);
        $this->assertSame(5, $this->points->ledgerBalance($user));
    }

    public function test_debit_decreases_balance_and_stores_a_negative_amount(): void
    {
        $user = User::factory()->withPoints(4)->create();

        $tx = $this->points->debit($user, 1, reason: 'Quiz: seerat-26');

        $this->assertSame(3, $user->fresh()->points_balance);
        $this->assertSame(-1, $tx->amount);
        $this->assertSame(3, $tx->balance_after);
    }

    public function test_debit_beyond_balance_is_refused_and_changes_nothing(): void
    {
        $user = User::factory()->withPoints(1)->create();

        try {
            $this->points->debit($user, 2);
            $this->fail('Expected InsufficientPointsException.');
        } catch (InsufficientPointsException $e) {
            $this->assertSame(2, $e->required);
            $this->assertSame(1, $e->available);
        }

        $this->assertSame(1, $user->fresh()->points_balance);
        $this->assertSame(1, PointTransaction::query()->where('user_id', $user->id)->count());
    }

    public function test_balance_never_goes_negative_even_at_exactly_zero(): void
    {
        $user = User::factory()->create();

        $this->expectException(InsufficientPointsException::class);
        $this->points->debit($user, 1);
    }

    public function test_cached_balance_always_equals_the_ledger_sum(): void
    {
        $user = User::factory()->create();

        $this->points->credit($user, 10, reason: 'Grant');
        $this->points->debit($user, 3, reason: 'Quiz');
        $this->points->debit($user, 2, reason: 'Quiz');
        $this->points->credit($user, 1, type: PointTransaction::TYPE_REFUND, reason: 'Refund');

        $this->assertSame(6, $user->fresh()->points_balance);
        $this->assertSame(6, $this->points->ledgerBalance($user));
        $this->assertSame([], $this->points->findBalanceDrift());
    }

    public function test_drift_detection_reports_a_tampered_cached_balance(): void
    {
        $user = User::factory()->withPoints(5)->create();

        // Simulate corruption by writing the cache directly, bypassing the service.
        DB::table('users')->where('id', $user->id)->update(['points_balance' => 99]);

        $drift = $this->points->findBalanceDrift();

        $this->assertCount(1, $drift);
        $this->assertSame(['user_id' => $user->id, 'cached' => 99, 'ledger' => 5], $drift[0]);
    }

    public function test_a_failure_after_the_debit_rolls_the_point_movement_back(): void
    {
        $user = User::factory()->withPoints(3)->create();

        try {
            DB::transaction(function () use ($user) {
                $this->points->debit($user, 1, reason: 'Quiz start');
                throw new \RuntimeException('attempt creation failed');
            });
        } catch (\RuntimeException) {
            // expected
        }

        // The student must not lose a point to a half-created attempt.
        $this->assertSame(3, $user->fresh()->points_balance);
        $this->assertSame(1, PointTransaction::query()->where('user_id', $user->id)->count());
    }

    public function test_zero_and_negative_amounts_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->assertThrows(fn () => $this->points->credit($user, 0), \InvalidArgumentException::class);
        $this->assertThrows(fn () => $this->points->debit($user, 0), \InvalidArgumentException::class);
        $this->assertThrows(fn () => $this->points->credit($user, -5), \InvalidArgumentException::class);
    }
}
