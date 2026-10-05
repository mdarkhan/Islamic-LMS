<?php

namespace Tests\Feature\Public;

use App\Models\Book;
use App\Models\Course;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_query_shows_no_results_and_no_error(): void
    {
        $this->get(route('search.index'))->assertOk();
    }

    public function test_it_finds_matching_published_content_across_all_three_types(): void
    {
        Course::factory()->create(['title' => 'সীরাত পরিচিতি কোর্স']);
        Book::factory()->create(['title' => 'সীরাতুন নবী', 'author' => 'মাসউদ আলিমী']);
        $category = PostCategory::query()->create(['name' => 'ফতোয়া', 'slug' => 'fatwa', 'is_active' => true, 'sort_order' => 0]);
        Post::query()->create([
            'title' => 'সীরাতের শিক্ষা', 'slug' => 'seerah-lesson', 'post_category_id' => $category->id,
            'author_id' => User::factory()->staff()->create()->id, 'body' => 'x',
            'status' => Post::STATUS_PUBLISHED, 'published_at' => now()->subDay(),
        ]);

        $response = $this->get(route('search.index', ['q' => 'সীরাত']))->assertOk();

        $response->assertSee('সীরাত পরিচিতি কোর্স')
            ->assertSee('সীরাতুন নবী')
            ->assertSee('সীরাতের শিক্ষা');
    }

    public function test_unpublished_content_never_appears_in_results(): void
    {
        Course::factory()->create(['title' => 'গোপন কোর্স', 'is_published' => false]);
        Book::factory()->create(['title' => 'গোপন বই', 'is_published' => false]);
        $category = PostCategory::query()->create(['name' => 'ফতোয়া', 'slug' => 'fatwa2', 'is_active' => true, 'sort_order' => 0]);
        Post::query()->create([
            'title' => 'গোপন লেখা', 'slug' => 'hidden-post', 'post_category_id' => $category->id,
            'author_id' => User::factory()->staff()->create()->id, 'body' => 'x',
            'status' => Post::STATUS_DRAFT,
        ]);

        $response = $this->get(route('search.index', ['q' => 'গোপন']))->assertOk();

        $response->assertDontSee('গোপন কোর্স')
            ->assertDontSee('গোপন বই')
            ->assertDontSee('গোপন লেখা')
            ->assertSee(__('public.search_no_results'));
    }

    /**
     * য়/ড়/ঢ় has two byte sequences (precomposed vs base + nukta). MySQL LIKE treats them as
     * different strings, so text typed on one keyboard must still be found by a search typed
     * on the other, in either direction.
     */
    public function test_search_matches_both_spellings_of_bengali_nukta_letters(): void
    {
        $precomposed = "জানু\u{09DF}ারি";            // য় as one code point
        $decomposed = "জানু\u{09AF}\u{09BC}ারি";     // য + nukta

        Course::factory()->create(['title' => "কোর্স {$precomposed}", 'is_published' => true]);
        Book::factory()->create(['title' => "বই {$decomposed}", 'is_published' => true]);

        foreach ([$precomposed, $decomposed] as $typed) {
            // The exact stored titles — not bare words like "বই", which the nav also contains.
            $this->get(route('search.index', ['q' => $typed]))->assertOk()
                ->assertSee("কোর্স {$precomposed}", false)
                ->assertSee("বই {$decomposed}", false);
        }
    }

    public function test_like_wildcards_in_the_query_are_searched_literally(): void
    {
        Course::factory()->create(['title' => 'সাধারণ কোর্স', 'is_published' => true]);

        // A bare "%" or "_" must not behave as "match everything".
        foreach (['%', '_'] as $wildcard) {
            $this->get(route('search.index', ['q' => $wildcard]))->assertOk()->assertDontSee('সাধারণ কোর্স');
        }
    }

    public function test_no_results_state_is_shown(): void
    {
        $this->get(route('search.index', ['q' => 'zzz-nonexistent-zzz']))
            ->assertOk()
            ->assertSee(__('public.search_no_results'));
    }
}
