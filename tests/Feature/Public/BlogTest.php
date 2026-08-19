<?php

namespace Tests\Feature\Public;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(array $o = []): PostCategory
    {
        static $n = 0;
        $n++;

        return PostCategory::query()->create(array_merge([
            'name' => 'ফতোয়া', 'slug' => 'cat-'.$n, 'is_active' => true, 'sort_order' => $n,
        ], $o));
    }

    private function makePost(array $o = []): Post
    {
        static $n = 0;
        $n++;

        return Post::query()->create(array_merge([
            'title' => "Article {$n}", 'slug' => "article-{$n}",
            'post_category_id' => ($o['post_category_id'] ?? $this->makeCategory()->id),
            'author_id' => User::factory()->staff()->create()->id,
            'body' => 'Body text.', 'status' => Post::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $o));
    }

    // ── Public visibility ─────────────────────────────────────────────────────────

    public function test_published_posts_are_public(): void
    {
        $post = $this->makePost(['title' => 'Visible ruling']);

        $this->get(route('blog.index'))->assertOk()->assertSee('Visible ruling');
        $this->get(route('blog.show', $post))->assertOk()->assertSee('Visible ruling');
    }

    public function test_draft_archived_and_future_posts_are_not_public(): void
    {
        $draft = $this->makePost(['title' => 'Draft one', 'status' => Post::STATUS_DRAFT]);
        $archived = $this->makePost(['title' => 'Archived one', 'status' => Post::STATUS_ARCHIVED]);
        $future = $this->makePost(['title' => 'Scheduled one', 'published_at' => now()->addWeek()]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertDontSee('Draft one')
            ->assertDontSee('Archived one')
            ->assertDontSee('Scheduled one');

        $this->get(route('blog.show', $draft))->assertNotFound();
        $this->get(route('blog.show', $archived))->assertNotFound();
        $this->get(route('blog.show', $future))->assertNotFound();
    }

    // ── Slugs ─────────────────────────────────────────────────────────────────────

    public function test_admin_creates_a_post_with_a_real_bengali_slug(): void
    {
        $category = $this->makeCategory();

        $this->actingAs($this->makeAdmin())->post(route('admin.posts.store'), [
            'title' => 'ব্যবসার সম্পদের যাকাত',
            'post_category_id' => $category->id,
            'body' => 'মূল লেখা এখানে।',
            'status' => Post::STATUS_PUBLISHED,
        ])->assertRedirect();

        $post = Post::query()->latest('id')->first();
        $this->assertSame('ব্যবসার-সম্পদের-যাকাত', $post->slug);
        $this->assertNotSame('post', $post->slug);
    }

    public function test_duplicate_titles_get_distinct_slugs(): void
    {
        $category = $this->makeCategory();
        $admin = $this->makeAdmin();

        foreach (range(1, 2) as $i) {
            $this->actingAs($admin)->post(route('admin.posts.store'), [
                'title' => 'একই শিরোনাম', 'post_category_id' => $category->id,
                'body' => 'text', 'status' => Post::STATUS_DRAFT,
            ])->assertRedirect();
        }

        $slugs = Post::query()->pluck('slug');
        $this->assertSame($slugs->count(), $slugs->unique()->count());
    }

    // ── Inline category creation ──────────────────────────────────────────────────

    public function test_admin_can_create_a_category_inline_while_writing(): void
    {
        $this->actingAs($this->makeAdmin())->post(route('admin.posts.store'), [
            'title' => 'নতুন বিষয়ের লেখা',
            'new_category' => 'নতুন বিভাগ',   // no post_category_id — created inline
            'body' => '<p>মূল লেখা</p>',
            'status' => Post::STATUS_DRAFT,
        ])->assertRedirect();

        $category = PostCategory::query()->where('name', 'নতুন বিভাগ')->first();
        $this->assertNotNull($category);
        $this->assertTrue($category->is_active);
        $this->assertSame('নতুন-বিভাগ', $category->slug);   // real Bengali slug
        $this->assertSame($category->id, Post::query()->latest('id')->first()->post_category_id);
    }

    public function test_inline_category_reuses_an_existing_name_and_does_not_duplicate(): void
    {
        $existing = $this->makeCategory(['name' => 'সীরাত', 'slug' => 'seerah-x']);

        $this->actingAs($this->makeAdmin())->post(route('admin.posts.store'), [
            'title' => 'সীরাতের লেখা',
            'new_category' => 'সীরাত',   // same name as an existing category
            'body' => '<p>লেখা</p>',
            'status' => Post::STATUS_DRAFT,
        ])->assertRedirect();

        $this->assertSame(1, PostCategory::query()->where('name', 'সীরাত')->count());
        $this->assertSame($existing->id, Post::query()->latest('id')->first()->post_category_id);
    }

    public function test_a_post_still_requires_a_category(): void
    {
        $this->actingAs($this->makeAdmin())->from(route('admin.posts.create'))->post(route('admin.posts.store'), [
            'title' => 'বিভাগহীন',
            'body' => '<p>লেখা</p>',
            'status' => Post::STATUS_DRAFT,
        ])->assertSessionHasErrors('post_category_id');
    }

    // ── Filters & search ──────────────────────────────────────────────────────────

    public function test_category_filter_and_search_work(): void
    {
        $fiqh = $this->makeCategory(['name' => 'ফিকহ', 'slug' => 'fiqh']);
        $seerah = $this->makeCategory(['name' => 'সীরাত', 'slug' => 'seerah']);
        $this->makePost(['title' => 'Fiqh piece', 'post_category_id' => $fiqh->id]);
        $this->makePost(['title' => 'Seerah piece', 'post_category_id' => $seerah->id]);

        $this->get(route('blog.index', ['category' => 'fiqh']))
            ->assertOk()->assertSee('Fiqh piece')->assertDontSee('Seerah piece');

        $this->get(route('blog.index', ['q' => 'Seerah']))
            ->assertOk()->assertSee('Seerah piece')->assertDontSee('Fiqh piece');
    }

    // ── XSS / sanitisation ────────────────────────────────────────────────────────

    public function test_rich_text_content_is_sanitised(): void
    {
        $post = $this->makePost([
            'title' => 'Safe render',
            'body' => '<p>Normal <strong onclick="steal()">bold</strong> text.</p>'
                .'<script>alert(\'xss\')</script>'
                .'<iframe src="evil"></iframe>'
                .'<a href="javascript:alert(1)">bad</a>'
                .'<a href="https://good.test">good</a>',
        ]);

        $html = $this->get(route('blog.show', $post))->assertOk()->getContent();

        // Executable markup, event handlers and unsafe schemes are stripped…
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringNotContainsString('steal()', $html);          // the injected handler is gone
        // …while allow-listed formatting and safe links survive.
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('href="https://good.test"', $html);
    }

    // ── Question / answer-summary layout ──────────────────────────────────────────

    public function test_question_and_summary_render_with_the_answer_heading(): void
    {
        $post = $this->makePost([
            'title' => 'QA layout piece',
            'question' => 'নামাযে সাহু সিজদা কখন দিতে হবে?',
            'excerpt' => 'সাহু সিজদার সংক্ষিপ্ত উত্তর।',
        ]);

        $this->get(route('blog.show', $post))->assertOk()
            ->assertSee('নামাযে সাহু সিজদা কখন দিতে হবে?')     // the question text
            ->assertSee('সাহু সিজদার সংক্ষিপ্ত উত্তর।')          // the summary text
            ->assertSee(__('posts.summary'))                     // "সারসংক্ষেপ" box label
            ->assertSee('</h2>', false);
        // The standalone "Answer" heading appears only when there is a question.
        $this->assertStringContainsString('উত্তর</h2>', $this->get(route('blog.show', $post))->getContent());
    }

    public function test_question_and_summary_are_optional(): void
    {
        // A plain article with neither shows no question box and no "Answer" heading.
        $post = $this->makePost(['title' => 'Plain article', 'excerpt' => '']);
        $html = $this->get(route('blog.show', $post))->assertOk()->getContent();

        $this->assertStringNotContainsString('উত্তর</h2>', $html);
        $this->assertStringNotContainsString('text-amber-700', $html);   // no question callout
    }

    // ── View counter ──────────────────────────────────────────────────────────────

    public function test_viewing_an_article_counts_once_per_session(): void
    {
        $post = $this->makePost(['title' => 'Counted piece']);
        $this->assertSame(0, $post->fresh()->views_count);

        $this->get(route('blog.show', $post))->assertOk();
        $this->assertSame(1, $post->fresh()->views_count);

        // A refresh within the same session must not inflate the count.
        $this->withSession(['viewed_posts' => [$post->id]])->get(route('blog.show', $post))->assertOk();
        $this->assertSame(1, $post->fresh()->views_count);
    }

    // ── Tags ──────────────────────────────────────────────────────────────────────

    public function test_admin_can_tag_an_article_and_readers_can_filter_by_tag(): void
    {
        $category = $this->makeCategory();

        $this->actingAs($this->makeAdmin())->post(route('admin.posts.store'), [
            'title' => 'Tagged article', 'post_category_id' => $category->id,
            'body' => '<p>body</p>', 'status' => Post::STATUS_PUBLISHED, 'tags' => 'ফিকহ, রোযা, ফিকহ',
        ])->assertRedirect();

        $post = Post::query()->latest('id')->first();
        $this->assertSame(2, $post->tags()->count());   // duplicate "ফিকহ" collapsed

        // Tag pills render on the article page…
        $this->get(route('blog.show', $post))->assertOk()->assertSee('ফিকহ')->assertSee('রোযা');

        // …and filtering by a tag lists the article.
        $tag = \App\Models\Tag::query()->where('name', 'ফিকহ')->first();
        $this->assertSame('ফিকহ', $tag->slug);   // real Bengali slug
        $this->get(route('blog.index', ['tag' => $tag->slug]))->assertOk()->assertSee('Tagged article');
    }

    public function test_article_page_has_share_and_print_controls(): void
    {
        $post = $this->makePost(['title' => 'Shareable']);

        $this->get(route('blog.show', $post))->assertOk()
            ->assertSee(__('posts.share'))
            ->assertSee('facebook.com/sharer', false)
            ->assertSee('wa.me', false)
            ->assertSee(__('posts.print'));
    }

    // ── SEO ───────────────────────────────────────────────────────────────────────

    public function test_post_detail_emits_seo_metadata(): void
    {
        $post = $this->makePost([
            'title' => 'SEO article', 'seo_description' => 'A concise summary for search engines.',
        ]);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('<meta name="description" content="A concise summary for search engines.">', false)
            ->assertSee('<meta property="og:title" content="SEO article">', false)
            ->assertSee('rel="canonical"', false);
    }

    public function test_admin_area_is_noindex(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('admin.posts.index'))
            ->assertOk()->assertSee('noindex', false);
    }

    // ── Authorization ─────────────────────────────────────────────────────────────

    public function test_a_student_cannot_reach_the_cms(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.posts.index'))->assertForbidden();
        $this->actingAs($this->makeStudent())->post(route('admin.posts.store'), [])->assertForbidden();
    }

    public function test_an_admin_without_posts_permission_is_blocked(): void
    {
        $admin = $this->makeAdmin();
        $admin->roles()->first()->permissions()->detach(
            \App\Models\Permission::query()->where('name', 'posts.manage')->pluck('id')
        );

        $this->actingAs($admin)->get(route('admin.posts.index'))->assertForbidden();
    }
}
