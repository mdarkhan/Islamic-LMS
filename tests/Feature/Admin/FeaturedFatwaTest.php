<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedFatwaTest extends TestCase
{
    use RefreshDatabase;

    private PostCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = PostCategory::query()->create(['name' => 'প্রশ্নোত্তর', 'slug' => 'qa', 'is_active' => true, 'sort_order' => 0]);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'একটি প্রশ্নোত্তর', 'post_category_id' => $this->category->id,
            'body' => '<p>উত্তর</p>', 'status' => Post::STATUS_PUBLISHED,
            'published_at' => now()->subMinute()->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    public function test_admin_can_feature_a_post(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.posts.store'), $this->basePayload(['is_featured' => '1']))->assertRedirect();

        $post = Post::query()->where('title', 'একটি প্রশ্নোত্তর')->firstOrFail();
        $this->assertTrue($post->is_featured);
    }

    public function test_unchecking_the_checkbox_unfeatures_a_previously_featured_post(): void
    {
        $admin = $this->makeAdmin();
        $post = Post::query()->create([
            'slug' => 'featured-post', 'title' => 'ফিচার্ড', 'post_category_id' => $this->category->id,
            'body' => 'x', 'author_id' => $admin->id, 'status' => Post::STATUS_PUBLISHED,
            'published_at' => now()->subDay(), 'is_featured' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.posts.update', $post), $this->basePayload([
            'title' => $post->title,
            // is_featured omitted — an unchecked checkbox.
        ]))->assertRedirect();

        $this->assertFalse($post->fresh()->is_featured, 'an unchecked checkbox must clear a previously-true flag');
    }

    public function test_the_homepage_shows_the_featured_post_or_falls_back_to_the_latest_qa_post(): void
    {
        $author = User::factory()->staff()->create();

        // No featured post yet, but a Q&A-shaped post exists (has a `question`) — it's the fallback.
        Post::query()->create([
            'slug' => 'qa-fallback', 'title' => 'সাধারণ লেখা', 'question' => 'এটি কি জায়েজ?',
            'post_category_id' => $this->category->id, 'body' => 'x', 'author_id' => $author->id,
            'status' => Post::STATUS_PUBLISHED, 'published_at' => now()->subDay(),
        ]);

        $this->get(route('home'))->assertOk()->assertSee('এটি কি জায়েজ?');

        // Once a post is explicitly featured, it wins over the fallback.
        Post::query()->create([
            'slug' => 'explicitly-featured', 'title' => 'বিশেষ প্রশ্নোত্তর', 'question' => 'বিশেষ প্রশ্ন',
            'post_category_id' => $this->category->id, 'body' => 'x', 'author_id' => $author->id,
            'status' => Post::STATUS_PUBLISHED, 'published_at' => now()->subHour(), 'is_featured' => true,
        ]);

        $this->get(route('home'))->assertOk()->assertSee('বিশেষ প্রশ্ন')->assertDontSee('এটি কি জায়েজ?');
    }
}
