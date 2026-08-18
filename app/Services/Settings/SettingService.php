<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The one coherent way to read and write settings.
 *
 * Typed access with sensible defaults, a single cached read of the whole table, and
 * cache invalidation on write. Decimals (money / rates) are returned as STRINGS so the
 * Zakat service can do BCMath arithmetic without binary-float error — never cast a rate
 * to float here.
 *
 * Secrets (SMTP password, APP_KEY, DB credentials) are NEVER stored here; those live in
 * the environment only. This service is for admin-editable, non-secret configuration.
 */
class SettingService
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, array{value: ?string, type: string, updated_at: ?string}>|null */
    private ?array $memo = null;

    /**
     * Defaults returned when a row is absent, and the source of truth for each key's
     * type. Keeping them here means a fresh install works before the seeder runs.
     *
     * @var array<string, array{default: mixed, type: string, group: string}>
     */
    private const REGISTRY = [
        'site_title' => ['default' => 'মাসউদ আলিমী', 'type' => 'string', 'group' => 'general'],
        'site_tagline' => ['default' => 'কুরআন, তাফসির ও সীরাত শিক্ষার একটি নির্ভরযোগ্য মাধ্যম', 'type' => 'string', 'group' => 'general'],
        'telegram_url' => ['default' => '', 'type' => 'string', 'group' => 'general'],

        'gold_price_per_gram' => ['default' => null, 'type' => 'decimal', 'group' => 'zakat'],
        'silver_price_per_gram' => ['default' => null, 'type' => 'decimal', 'group' => 'zakat'],
        'nisab_basis' => ['default' => 'silver', 'type' => 'string', 'group' => 'zakat'],
        'currency_label' => ['default' => '৳', 'type' => 'string', 'group' => 'zakat'],

        'hijri_offset_days' => ['default' => 0, 'type' => 'integer', 'group' => 'calendar'],
        'calendar_latitude' => ['default' => '23.8103', 'type' => 'decimal', 'group' => 'calendar'],
        'calendar_longitude' => ['default' => '90.4125', 'type' => 'decimal', 'group' => 'calendar'],
        'calendar_timezone' => ['default' => 'Asia/Dhaka', 'type' => 'string', 'group' => 'calendar'],
    ];

    /** Typed value for a key, falling back to the registered default. */
    public function get(string $key, mixed $default = null): mixed
    {
        $rows = $this->rows();

        if (! array_key_exists($key, $rows)) {
            return $default ?? (self::REGISTRY[$key]['default'] ?? null);
        }

        $type = $rows[$key]['type'] ?? (self::REGISTRY[$key]['type'] ?? 'string');
        $value = $rows[$key]['value'];

        if ($value === null) {
            return $default ?? (self::REGISTRY[$key]['default'] ?? null);
        }

        return $this->cast($value, $type);
    }

    /** When a key was last updated — used for the reference-rate "last updated" stamp. */
    public function updatedAt(string $key): ?CarbonImmutable
    {
        $at = $this->rows()[$key]['updated_at'] ?? null;

        return $at ? CarbonImmutable::parse($at) : null;
    }

    /**
     * All settings in a group as key => typed value (missing keys fall back to default).
     *
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $keys = array_keys(array_filter(self::REGISTRY, fn ($m) => $m['group'] === $group));

        return collect($keys)->mapWithKeys(fn ($k) => [$k => $this->get($k)])->all();
    }

    /**
     * Persist a batch of key => value updates in one transaction, then invalidate the
     * cache. Unknown keys are ignored, so a crafted form field cannot create arbitrary
     * settings. updated_at is set per row so each rate carries its own stamp.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values, ?User $by = null): void
    {
        DB::transaction(function () use ($values, $by) {
            foreach ($values as $key => $value) {
                if (! array_key_exists($key, self::REGISTRY)) {
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value === null ? null : (string) $value,
                        'type' => self::REGISTRY[$key]['type'],
                        'group' => self::REGISTRY[$key]['group'],
                        'updated_by' => $by?->getKey(),
                        'updated_at' => now(),
                    ],
                );
            }
        });

        $this->flush();
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The whole settings table, cached. cPanel-friendly: uses the configured cache
     * driver (file/database), never requires Redis.
     *
     * @return array<string, array{value: ?string, type: string, updated_at: ?string}>
     */
    private function rows(): array
    {
        return $this->memo ??= Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::query()->get()->mapWithKeys(fn (Setting $s) => [
                $s->key => [
                    'value' => $s->value,
                    'type' => $s->type,
                    'updated_at' => $s->updated_at?->toIso8601String(),
                ],
            ])->all();
        });
    }

    private function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            // decimals stay STRING for BCMath money-safety; callers decide precision.
            default => $value,
        };
    }
}
