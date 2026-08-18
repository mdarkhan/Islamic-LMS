<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_reach_the_public_pages(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('blog.index'))->assertOk();
        $this->get(route('zakat.index'))->assertOk();
        $this->get(route('ask-ustaz.show'))->assertOk();
        $this->get(route('sitemap'))->assertOk();
        $this->get(route('robots'))->assertOk();
    }

    public function test_guests_are_redirected_from_protected_areas(): void
    {
        foreach (['/dashboard', '/exams', '/results', '/practice', '/leaderboards', '/points', '/profile', '/admin'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_robots_blocks_the_private_areas(): void
    {
        $body = $this->get(route('robots'))->assertOk()->getContent();

        foreach (['Disallow: /admin', 'Disallow: /dashboard', 'Disallow: /exams', 'Disallow: /results'] as $line) {
            $this->assertStringContainsString($line, $body);
        }
        $this->assertStringContainsString('Sitemap:', $body);
    }

    public function test_sitemap_lists_public_pages_and_published_posts(): void
    {
        $category = PostCategory::query()->create(['name' => 'ফতোয়া', 'slug' => 'fatwa', 'is_active' => true, 'sort_order' => 0]);
        Post::query()->create([
            'title' => 'Indexed article', 'slug' => 'indexed-article', 'post_category_id' => $category->id,
            'author_id' => User::factory()->staff()->create()->id, 'body' => 'x',
            'status' => Post::STATUS_PUBLISHED, 'published_at' => now()->subDay(),
        ]);
        Post::query()->create([
            'title' => 'Hidden draft', 'slug' => 'hidden-draft', 'post_category_id' => $category->id,
            'author_id' => User::factory()->staff()->create()->id, 'body' => 'x',
            'status' => Post::STATUS_DRAFT,
        ]);

        $xml = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml')->getContent();

        $this->assertStringContainsString(route('home'), $xml);
        $this->assertStringContainsString('indexed-article', $xml);
        $this->assertStringNotContainsString('hidden-draft', $xml);
    }
}
