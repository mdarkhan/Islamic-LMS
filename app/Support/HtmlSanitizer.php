<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * The safety contract for rich-text article bodies. The admin editor emits HTML; this
 * reduces it to a strict allow-list so admin- or paste-authored content can never carry
 * a script, an event handler, an iframe/embed, a dangerous CSS value, or a
 * `javascript:`/`data:` link (SECURITY.md, brief §7–8). Anything not on the list is
 * unwrapped (its safe text kept) or dropped. Bengali and Arabic text pass through
 * untouched.
 *
 * A limited `style` attribute survives — but only an allow-list of presentational CSS
 * properties (colour, background, bold/italic/underline) with values screened for
 * `url()`, `expression`, scripts and angle brackets. That is what lets the editor's
 * colour controls and pasted formatting keep working without opening a CSS-injection hole.
 *
 * Applied at RENDER time, so a body is safe no matter how it reached the database
 * (editor, seeder, import, or a hand-made model in a test).
 */
class HtmlSanitizer
{
    /** Allowed elements → the (non-style) attributes each may keep. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [],
        'u' => [], 's' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'code' => [], 'pre' => [],
        'span' => [], 'a' => ['href'],
    ];

    /** Presentational CSS properties permitted inside a `style` attribute. */
    private const STYLE_PROPS = [
        'color', 'background-color', 'font-weight', 'font-style',
        'text-decoration', 'text-decoration-line',
    ];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML encoding hint makes DOMDocument treat the input as UTF-8 (keeps Bengali).
        // NOIMPLIED/NODEFDTD stop it adding <html><body> and a doctype around our fragment.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div data-root="1">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return '';
        }

        self::sanitizeChildren($root, $doc);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function sanitizeChildren(DOMNode $node, DOMDocument $doc): void
    {
        // Copy the list first — the loop mutates the child collection.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;   // plain text is safe (serialisation escapes it)
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);   // comments, processing instructions, etc.
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (! isset(self::ALLOWED[$tag])) {
                // Disallowed element: sanitise its children, then unwrap (drop the tag,
                // keep the safe contents). A <script>'s text content is thereby discarded
                // as inert text, never executed.
                self::sanitizeChildren($child, $doc);
                if ($tag === 'script' || $tag === 'style') {
                    $node->removeChild($child);   // drop code payloads entirely
                    continue;
                }
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Allowed element: keep a filtered `style`, drop every other attribute not on
            // the element's own allow-list.
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);

                if ($name === 'style') {
                    $style = self::filterStyle($attr->nodeValue);
                    $style === '' ? $child->removeAttribute('style') : $child->setAttribute('style', $style);

                    continue;
                }

                if (! in_array($name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attr->nodeName);
                }
            }

            if ($tag === 'a') {
                self::sanitizeLink($child);
            }

            self::sanitizeChildren($child, $doc);
        }
    }

    /**
     * Keep only allow-listed presentational declarations whose values are free of
     * `url()`, `expression`, scripts and angle brackets — everything else is dropped.
     */
    private static function filterStyle(?string $style): string
    {
        $kept = [];

        foreach (explode(';', (string) $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$prop, $value] = explode(':', $declaration, 2);
            $prop = strtolower(trim($prop));
            $value = trim($value);
            $lower = strtolower($value);

            if ($value === '' || ! in_array($prop, self::STYLE_PROPS, true)) {
                continue;
            }
            if (str_contains($lower, 'url(') || str_contains($lower, 'expression')
                || str_contains($lower, 'javascript:') || str_contains($value, '/*')
                || str_contains($value, '<') || str_contains($value, '>')) {
                continue;
            }
            // A conservative charset: hex/rgb/hsl/named colours and simple keywords only.
            if (preg_match('/^[#0-9a-z.,%()\- ]+$/i', $value) !== 1) {
                continue;
            }

            $kept[] = $prop.': '.$value;
        }

        return implode('; ', $kept);
    }

    private static function sanitizeLink(DOMElement $anchor): void
    {
        $href = trim($anchor->getAttribute('href'));

        $safe = $href !== '' && (
            str_starts_with($href, '/')
            || str_starts_with($href, '#')
            || preg_match('#^(https?:|mailto:)#i', $href) === 1
        );

        if (! $safe) {
            $anchor->removeAttribute('href');   // drops javascript:/data:/vbscript: etc.

            return;
        }

        // Outbound links open safely and are not endorsed for ranking.
        $anchor->setAttribute('rel', 'noopener nofollow ugc');
        $anchor->setAttribute('target', '_blank');
    }
}
