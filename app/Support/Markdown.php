<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The content format for posts is **Markdown**, chosen as the simplest maintainable and
 * safe option (no heavy proprietary editor dependency). It is rendered server-side with
 * raw HTML STRIPPED and unsafe link schemes disallowed, so admin- or paste-authored
 * content can never execute a script, an event handler, a `javascript:` URL or an
 * embedded iframe (SECURITY.md, brief §7–8). Bengali and Arabic text pass through
 * untouched.
 *
 * Supported: headings, paragraphs, bold, italic, lists, blockquotes and links. Anything
 * that looks like raw HTML in the source is removed rather than rendered.
 */
class Markdown
{
    /** Render trusted-but-still-sanitised Markdown to safe HTML. */
    public static function render(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',        // drop any raw HTML tags entirely
            'allow_unsafe_links' => false,  // strip javascript:/data: link schemes
            'max_nesting_level' => 20,
        ]);
    }

    /** A plain-text preview (tags stripped) for excerpts / meta descriptions. */
    public static function toText(?string $markdown, int $limit = 160): string
    {
        return Str::limit(trim(strip_tags(self::render($markdown))), $limit);
    }
}
