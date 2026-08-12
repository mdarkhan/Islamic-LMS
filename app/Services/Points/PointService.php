<?php

namespace App\Services\Points;

use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only class permitted to write users.points_balance.
 *
 * Every movement writes an append-only ledger row and updates the cached balance
 * inside one transaction, having first taken a row lock on the user. That lock is
 * what makes concurrent quiz starts safe: two simultaneous requests cannot both
 * read a balance of 1 and both debit it.
 */
class PointService
{
    /**
     * Credit points. Positive amount required.
     *
     * @throws InsufficientPointsException never — present for signature symmetry
     */
    public function credit(
        User $user,
        int $amount,
        string $type = PointTransaction::TYPE_GRANT,
        ?string $reason = null,
        ?User $performedBy = null,
        ?object $reference = null,
    ): PointTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        return $this->move($user, $amount, $type, $reason, $performedBy, $reference);
    }

    /**
     * Debit points. Pass a positive amount; it is stored negated.
     *
     * @throws InsufficientPointsException when the balance would go below zero
     */
    public function debit(
        User $user,
        int $amount,
        string $type = PointTransaction::TYPE_DEDUCTION,
        ?string $reason = null,
        ?User $performedBy = null,
        ?object $reference = null,
    ): PointTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        return $this->move($user, -$amount, $type, $reason, $performedBy, $reference);
    }

    /**
     * Apply a signed movement atomically.
     *
     * Safe to call inside a surrounding transaction — Laravel nests via savepoints,
     * so a failure in the caller (for example attempt creation) rolls the point
     * movement back with it. That is what guarantees a student never loses a point
     * to a half-created attempt.
     */
    public function move(
        User $user,
        int $signedAmount,
        string $type,
        ?string $reason = null,
        ?User $performedBy = null,
        ?object $reference = null,
    ): PointTransaction {
        if ($signedAmount === 0) {
            throw new \InvalidArgumentException('Point movement cannot be zero.');
        }

        return DB::transaction(function () use ($user, $signedAmount, $type, $reason, $performedBy, $reference) {
            // Lock this user's row for the rest of the transaction.
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $balanceAfter = $locked->points_balance + $signedAmount;

            if ($balanceAfter < 0) {
                throw new InsufficientPointsException(
                    required: abs($signedAmount),
                    available: $locked->points_balance,
                );
            }

            $transaction = PointTransaction::query()->create([
                'user_id' => $locked->getKey(),
                'type' => $type,
                'amount' => $signedAmount,
                'balance_after' => $balanceAfter,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'performed_by' => $performedBy?->getKey(),
                'reason' => $reason,
            ]);

            $locked->forceFill(['points_balance' => $balanceAfter])->save();

            // Keep the caller's instance consistent with what was just written.
            $user->setAttribute('points_balance', $balanceAfter);

            return $transaction;
        });
    }

    /** Authoritative balance, recomputed from the ledger rather than the cache. */
    public function ledgerBalance(User $user): int
    {
        return (int) PointTransaction::query()->where('user_id', $user->getKey())->sum('amount');
    }

    /**
     * Detect drift between the cached balance and the ledger.
     *
     * @return array<int, array{user_id:int, cached:int, ledger:int}>
     */
    public function findBalanceDrift(): array
    {
        return DB::table('users')
            ->leftJoin('point_transactions', 'users.id', '=', 'point_transactions.user_id')
            ->groupBy('users.id', 'users.points_balance')
            ->havingRaw('users.points_balance <> COALESCE(SUM(point_transactions.amount), 0)')
            ->select([
                'users.id as user_id',
                'users.points_balance as cached',
                DB::raw('COALESCE(SUM(point_transactions.amount), 0) as ledger'),
            ])
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'cached' => (int) $row->cached,
                'ledger' => (int) $row->ledger,
            ])
            ->all();
    }
}
