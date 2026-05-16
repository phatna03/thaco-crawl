<?php

namespace App\Services;

use DOMElement;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Thu thập URL từ HTML của một trang (cùng host), khi site không có sitemap XML.
 */
class PageLinkDiscoveryService
{
    private const SKIP_EXTENSIONS = [
        'css', 'js', 'mjs', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'eot', 'otf', 'mp4', 'webm', 'mp3', 'wav', 'zip', 'rar', '7z',
    ];

    public function __construct(
        private readonly CrawlService $crawlService,
    ) {}

    /**
     * Tải trang chủ (/) của domain đã chuẩn hoá và trích link cùng site.
     *
     * @return list<string>
     */
    public function discoverFromDomainHomepage(string $domainInput, int $maxUrls = 250): array
    {
        $base = CrawlUrlValidator::normalizeBaseInput($domainInput);
        $seed = rtrim($base, '/').'/';

        return $this->discoverUrlsFromPage($seed, $maxUrls);
    }

    /**
     * @return list<string>
     */
    public function discoverUrlsFromPage(string $pageUrl, int $maxUrls = 250): array
    {
        CrawlUrlValidator::assertHttpUrlAllowed($pageUrl);
        $html = $this->crawlService->fetch($pageUrl, expectHtml: true);

        return $this->extractSameSiteLinks($html, $pageUrl, $maxUrls);
    }

    /**
     * @return list<string>
     */
    private function extractSameSiteLinks(string $html, string $seedUrl, int $maxUrls): array
    {
        $seedParts = parse_url($seedUrl);
        if ($seedParts === false || empty($seedParts['host'])) {
            return [];
        }
        $seedHost = strtolower((string) $seedParts['host']);

        $crawler = new Crawler($html);
        $found = [];

        try {
            foreach ($crawler->filter('a[href]') as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }
                $href = trim((string) $node->getAttribute('href'));
                if ($href === '' || str_starts_with(strtolower($href), 'javascript:')) {
                    continue;
                }
                $absolute = $this->toAbsoluteUrl($href, $seedUrl);
                if (! filter_var($absolute, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $parts = parse_url($absolute);
                if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
                    continue;
                }
                $scheme = strtolower((string) $parts['scheme']);
                if ($scheme !== 'http' && $scheme !== 'https') {
                    continue;
                }
                $host = strtolower((string) $parts['host']);
                if (! $this->sameSiteHost($host, $seedHost)) {
                    continue;
                }
                $path = (string) ($parts['path'] ?? '/');
                if ($this->pathLooksLikeStaticAsset($path)) {
                    continue;
                }
                $normalized = $this->normalizeUrlForList($absolute);
                if ($normalized === '') {
                    continue;
                }
                $found[$normalized] = true;
                if (count($found) >= $maxUrls) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        $urls = array_keys($found);
        sort($urls);

        return array_values($urls);
    }

    private function sameSiteHost(string $linkHost, string $seedHost): bool
    {
        if (strcasecmp($linkHost, $seedHost) === 0) {
            return true;
        }
        $strip = fn (string $h): string => preg_replace('/^www\./i', '', $h) ?? $h;

        return strcasecmp($strip($linkHost), $strip($seedHost)) === 0;
    }

    private function pathLooksLikeStaticAsset(string $path): bool
    {
        $path = strtolower($path);
        if (preg_match('/\.([a-z0-9]+)(?:\?.*)?$/i', $path, $m)) {
            return in_array(strtolower($m[1]), self::SKIP_EXTENSIONS, true);
        }

        return false;
    }

    private function normalizeUrlForList(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        if ($path === '') {
            $path = '/';
        }
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$port.$path.$query;
    }

    private function toAbsoluteUrl(string $href, string $baseUrl): string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return '';
        }
        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return strtolower((string) $baseScheme).':'.$href;
        }

        $base = parse_url($baseUrl);
        if ($base === false || empty($base['scheme']) || empty($base['host'])) {
            return $href;
        }
        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($href, '/')) {
            return $scheme.'://'.$host.$port.$href;
        }

        $path = $base['path'] ?? '/';
        $path = preg_replace('#/[^/]*$#', '/', $path) ?? '/';

        return $scheme.'://'.$host.$port.$path.$href;
    }
}
