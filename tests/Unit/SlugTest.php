<?php

namespace Tests\Unit;

use App\Support\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SlugTest extends TestCase
{
    #[DataProvider('titles')]
    public function test_it_builds_unicode_safe_slugs(string $title, string $expected): void
    {
        $this->assertSame($expected, Slug::make($title));
    }

    public static function titles(): array
    {
        return [
            // Bengali is preserved rather than stripped to nothing.
            'bengali title' => ['সীরাত-২৮ হিজরতের পূর্বপ্রস্তুতি', 'সীরাত-২৮-হিজরতের-পূর্বপ্রস্তুতি'],
            'bengali simple' => ['সীরাত ২৬', 'সীরাত-২৬'],
            'english lowercased' => ['Seerat 28: Hijrah Prep', 'seerat-28-hijrah-prep'],
            'english punctuation collapses' => ['Tafsir — Part  1!!', 'tafsir-part-1'],
            'arabic preserved' => ['سورة الفاتحة', 'سورة-الفاتحة'],
            'trims stray hyphens' => ['  -- Hello -- ', 'hello'],
            'all punctuation falls back' => ['!!! ???', 'item'],
        ];
    }

    public function test_bengali_matra_and_nukta_are_preserved(): void
    {
        // য় (with nukta) and vowel signs must survive slugging.
        $slug = Slug::make('বইয়ের তালিকা');
        $this->assertStringContainsString('বইয়ের', $slug);
        $this->assertSame('বইয়ের-তালিকা', $slug);
    }

    public function test_unique_appends_a_numeric_suffix_on_collision(): void
    {
        $taken = ['সীরাত-২৬', 'সীরাত-২৬-2'];

        $first = Slug::unique('সীরাত ২৬', fn ($s) => in_array($s, $taken, true));
        $this->assertSame('সীরাত-২৬-3', $first);

        $free = Slug::unique('তাফসির ১', fn ($s) => in_array($s, $taken, true));
        $this->assertSame('তাফসির-১', $free);
    }

    public function test_custom_fallback_is_used_for_empty_results(): void
    {
        $this->assertSame('quiz', Slug::make('###', 'quiz'));
    }
}
