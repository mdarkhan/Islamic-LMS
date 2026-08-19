<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_drops_scripts_iframes_and_styles(): void
    {
        $out = HtmlSanitizer::clean(
            '<p>Hello</p><script>alert(1)</script><iframe src="x"></iframe><style>body{}</style>'
        );

        $this->assertStringContainsString('<p>Hello</p>', $out);
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringNotContainsString('<style', $out);
    }

    public function test_it_strips_event_handlers_and_disallowed_attributes(): void
    {
        $out = HtmlSanitizer::clean('<strong onclick="x()" class="c" data-x="1" id="y">bold</strong>');

        $this->assertSame('<strong>bold</strong>', $out);
    }

    public function test_it_keeps_allow_listed_style_properties(): void
    {
        $out = HtmlSanitizer::clean('<span style="color: #ff0000; background-color: rgb(0,0,0); font-weight: bold">x</span>');

        $this->assertStringContainsString('color: #ff0000', $out);
        $this->assertStringContainsString('background-color: rgb(0,0,0)', $out);
        $this->assertStringContainsString('font-weight: bold', $out);
    }

    public function test_it_drops_disallowed_or_dangerous_style_declarations(): void
    {
        $out = HtmlSanitizer::clean(
            '<span style="color: red; position: fixed; background: url(javascript:alert(1)); width: 999px">x</span>'
        );

        $this->assertStringContainsString('color: red', $out);
        $this->assertStringNotContainsString('position', $out);
        $this->assertStringNotContainsString('url(', $out);
        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringNotContainsString('width', $out);
    }

    public function test_it_keeps_span_used_for_colour(): void
    {
        $out = HtmlSanitizer::clean('<p>a <span style="color: #123456">b</span> c</p>');

        $this->assertStringContainsString('<span style="color: #123456">b</span>', $out);
    }

    public function test_it_neutralises_unsafe_link_schemes(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,x', 'vbscript:msgbox'] as $bad) {
            $out = HtmlSanitizer::clean('<a href="'.$bad.'">click</a>');
            $this->assertStringNotContainsString('href', $out, "scheme not stripped: {$bad}");
            $this->assertStringContainsString('click', $out);
        }
    }

    public function test_it_keeps_safe_links_and_hardens_them(): void
    {
        $out = HtmlSanitizer::clean('<a href="https://example.test/x">go</a>');

        $this->assertStringContainsString('href="https://example.test/x"', $out);
        $this->assertStringContainsString('rel="noopener nofollow ugc"', $out);
        $this->assertStringContainsString('target="_blank"', $out);
    }

    public function test_it_allows_relative_and_anchor_links(): void
    {
        $this->assertStringContainsString('href="/articles/x"', HtmlSanitizer::clean('<a href="/articles/x">x</a>'));
        $this->assertStringContainsString('href="#s"', HtmlSanitizer::clean('<a href="#s">s</a>'));
    }

    public function test_it_unwraps_disallowed_tags_but_keeps_their_text(): void
    {
        $out = HtmlSanitizer::clean('<div><section>kept</section> text</div>');

        $this->assertStringNotContainsString('<div', $out);
        $this->assertStringNotContainsString('<section', $out);
        $this->assertStringContainsString('kept', $out);
        $this->assertStringContainsString('text', $out);
    }

    public function test_it_preserves_allowed_formatting_and_bengali_text(): void
    {
        $html = '<h2>শিরোনাম</h2><p>একটি <em>জোরালো</em> কথা</p>'
            .'<ul><li>এক</li><li>দুই</li></ul><blockquote>উদ্ধৃতি</blockquote>';

        $out = HtmlSanitizer::clean($html);

        $this->assertStringContainsString('<h2>শিরোনাম</h2>', $out);
        $this->assertStringContainsString('<em>জোরালো</em>', $out);
        $this->assertStringContainsString('<li>এক</li>', $out);
        $this->assertStringContainsString('<blockquote>উদ্ধৃতি</blockquote>', $out);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }
}
