<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Response;

/**
 * Dynamic sitemap of public pages + published posts, and a robots.txt that keeps the
 * authenticated areas (/admin, /dashboard, /exams, /results, /practice) out of search
 * indexes (brief §11). No external SEO service.
 */
class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('blog.index'), 'priority' => '0.8'],
            ['loc' => route('zakat.index'), 'priority' => '0.7'],
            ['loc' => route('ask-ustaz.show'), 'priority' => '0.6'],
        ];

        foreach (Post::query()->public()->latest('published_at')->get(['slug', 'updated_at']) as $post) {
            $urls[] = [
                'loc' => route('blog.show', $post),
                'lastmod' => $post->updated_at?->toAtomString(),
                'priority' => '0.6',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>';
            if (! empty($url['lastmod'])) {
                $xml .= '<lastmod>'.e($url['lastmod']).'</lastmod>';
            }
            $xml .= '<priority>'.$url['priority'].'</priority></url>'."\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /exams',
            'Disallow: /exam-attempts',
            'Disallow: /results',
            'Disallow: /practice',
            'Disallow: /practice-attempts',
            'Disallow: /leaderboards',
            'Disallow: /points',
            'Disallow: /profile',
            'Allow: /',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
