<?php

namespace App\Services;

use DOMElement;
use Symfony\Component\DomCrawler\Crawler;

class ParserService
{
    /**
     * @return array{title: string, content: string, thumbnail: ?string, category: ?string, metadata: array<string, mixed>}
     */
    public function parse(string $html, string $pageUrl): array
    {
        $crawler = new Crawler($html);
        $title = $this->extractTitle($crawler);
        $content = $this->extractMainText($crawler);
        $thumbnail = $this->extractOgImage($crawler, $pageUrl);
        $category = $this->extractCategory($crawler);
        $metadata = $this->extractMetadata($crawler);

        if ($category !== null && $category !== '') {
            $metadata['category_detected'] = $category;
        }

        if ($title === '' && $content === '') {
            throw new \RuntimeException('Không trích xuất được tiêu đề hoặc nội dung từ trang.');
        }

        if ($title === '') {
            $title = parse_url($pageUrl, PHP_URL_HOST) ?: 'Không tiêu đề';
        }

        return [
            'title' => $this->collapseWhitespace($title),
            'content' => $content,
            'thumbnail' => $thumbnail,
            'category' => $category !== null && $category !== '' ? $this->collapseWhitespace($category) : null,
            'metadata' => $metadata,
        ];
    }

    private function extractTitle(Crawler $crawler): string
    {
        try {
            foreach ($crawler->filter('meta[property="og:title"]') as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $c = $node->getAttribute('content') ?? '';
                if ($c !== '') {
                    return $c;
                }
            }
        } catch (\Throwable) {
        }

        try {
            if ($crawler->filter('title')->count() > 0) {
                return $crawler->filter('title')->first()->text('');
            }
        } catch (\Throwable) {
        }

        return '';
    }

    private function extractMainText(Crawler $crawler): string
    {
        $selectors = ['article', 'main', '[role="main"]', '#content', '.content', 'body'];

        foreach ($selectors as $selector) {
            try {
                if ($crawler->filter($selector)->count() === 0) {
                    continue;
                }

                $root = $crawler->filter($selector)->eq(0);
                $paragraphs = [];
                $root->filter('p')->each(function (Crawler $p) use (&$paragraphs): void {
                    $t = $this->collapseWhitespace($p->text(''));
                    if ($t !== '' && mb_strlen($t) > 40) {
                        $paragraphs[] = $t;
                    }
                });

                if ($paragraphs !== []) {
                    return implode("\n\n", $paragraphs);
                }

                $full = $this->collapseWhitespace($root->text(''));
                if (mb_strlen($full) > 80) {
                    return $full;
                }
            } catch (\Throwable) {
            }
        }

        return '';
    }

    private function extractOgImage(Crawler $crawler, string $pageUrl): ?string
    {
        try {
            foreach ($crawler->filter('meta[property="og:image"]') as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $href = $node->getAttribute('content') ?? '';
                if ($href !== '') {
                    return $this->toAbsoluteUrl($href, $pageUrl);
                }
            }

            if ($crawler->filter('article img')->count() > 0) {
                $src = $crawler->filter('article img')->first()->attr('src') ?? '';
                if ($src !== '') {
                    return $this->toAbsoluteUrl($src, $pageUrl);
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Danh mục / chuyên mục: meta, JSON-LD, breadcrumb, thẻ chuẩn HTML.
     */
    private function extractCategory(Crawler $crawler): ?string
    {
        try {
            foreach ($crawler->filter('meta[property="article:section"], meta[name="article_section"]') as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $c = trim((string) ($node->getAttribute('content') ?? ''));
                if ($c !== '' && mb_strlen($c) < 500) {
                    return $c;
                }
            }
        } catch (\Throwable) {
        }

        $fromLd = $this->extractCategoryFromJsonLd($crawler);
        if ($fromLd !== null) {
            return $fromLd;
        }

        try {
            if ($crawler->filter('a[rel="tag"]')->count() > 0) {
                $t = $this->collapseWhitespace($crawler->filter('a[rel="tag"]')->first()->text(''));
                if ($t !== '' && mb_strlen($t) < 500) {
                    return $t;
                }
            }
        } catch (\Throwable) {
        }

        foreach (['.post-category', '.entry-category', '.category', '[class*="breadcrumb"] a'] as $selector) {
            try {
                if ($crawler->filter($selector)->count() === 0) {
                    continue;
                }
                $t = $this->collapseWhitespace($crawler->filter($selector)->first()->text(''));
                if ($t !== '' && mb_strlen($t) < 500 && ! str_starts_with(mb_strtolower($t), 'http')) {
                    return $t;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function extractCategoryFromJsonLd(Crawler $crawler): ?string
    {
        try {
            foreach ($crawler->filter('script[type="application/ld+json"]') as $scriptEl) {
                if (! $scriptEl instanceof DOMElement) {
                    continue;
                }
                $raw = trim((string) $scriptEl->textContent);
                if ($raw === '') {
                    continue;
                }
                $data = json_decode($raw, true);
                if (! is_array($data)) {
                    continue;
                }
                $cat = $this->categoryFromJsonLdNode($data);
                if ($cat !== null) {
                    return $cat;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function categoryFromJsonLdNode(array $data): ?string
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $item) {
                if (is_array($item)) {
                    $cat = $this->categoryFromJsonLdItem($item);
                    if ($cat !== null) {
                        return $cat;
                    }
                }
            }
        }

        return $this->categoryFromJsonLdItem($data);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function categoryFromJsonLdItem(array $item): ?string
    {
        $typeRaw = $item['@type'] ?? null;
        $types = is_array($typeRaw) ? $typeRaw : [$typeRaw];
        $types = array_filter(array_map(fn ($t) => is_string($t) ? $t : null, $types));

        foreach ($types as $t) {
            if (in_array($t, ['NewsArticle', 'Article', 'BlogPosting'], true)) {
                foreach (['articleSection', 'genre'] as $key) {
                    if (! empty($item[$key]) && is_string($item[$key])) {
                        $v = trim($item[$key]);
                        if ($v !== '' && mb_strlen($v) < 500) {
                            return $v;
                        }
                    }
                }
            }

            if ($t === 'BreadcrumbList' && ! empty($item['itemListElement']) && is_array($item['itemListElement'])) {
                $names = [];
                foreach ($item['itemListElement'] as $el) {
                    if (! is_array($el)) {
                        continue;
                    }
                    $name = null;
                    if (isset($el['name']) && is_string($el['name'])) {
                        $name = trim($el['name']);
                    } elseif (isset($el['item']) && is_array($el['item']) && isset($el['item']['name']) && is_string($el['item']['name'])) {
                        $name = trim($el['item']['name']);
                    }
                    if ($name !== null && $name !== '') {
                        $names[] = $name;
                    }
                }
                if (count($names) >= 2) {
                    $penultimate = $names[count($names) - 2];
                    $homeLike = ['trang chủ', 'home', 'trang chủ'];
                    if (! in_array(mb_strtolower($penultimate), $homeLike, true) && mb_strlen($penultimate) < 500) {
                        return $penultimate;
                    }
                }
                if (count($names) === 1 && mb_strlen($names[0]) < 500) {
                    return $names[0];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMetadata(Crawler $crawler): array
    {
        $meta = [];
        try {
            foreach ($crawler->filter('meta[name="description"], meta[property="og:description"]') as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $content = $node->getAttribute('content') ?? '';
                if ($content !== '') {
                    $meta['description'] = $content;
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return $meta;
    }

    private function toAbsoluteUrl(string $href, string $baseUrl): string
    {
        $href = trim($href);
        if ($href === '') {
            return $href;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        $base = parse_url($baseUrl);
        if ($base === false || empty($base['scheme']) || empty($base['host'])) {
            return $href;
        }

        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $scheme.'://'.$host.$port.$href;
        }

        $path = $base['path'] ?? '/';
        $path = preg_replace('#/[^/]*$#', '/', $path) ?? '/';

        return $scheme.'://'.$host.$port.$path.$href;
    }

    private function collapseWhitespace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
