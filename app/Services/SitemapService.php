<?php

namespace App\Services;

class SitemapService
{
    public function __construct(
        private readonly CrawlService $crawlService,
    ) {}

    /**
     * @return array{urls: list<string>, via: string}
     */
    public function discoverUrlsFromDomain(string $domainInput, int $maxUrls = 500): array
    {
        $base = CrawlUrlValidator::normalizeBaseInput($domainInput);
        $seen = [];
        $queue = [];

        $robotsUrls = $this->extractSitemapsFromRobots($base);
        foreach ($robotsUrls as $u) {
            $queue[] = $u;
        }

        if ($queue === []) {
            foreach ($this->defaultSitemapCandidates($base) as $u) {
                $queue[] = $u;
            }
        }

        $out = [];

        while ($queue !== [] && count($out) < $maxUrls) {
            $sm = array_shift($queue);
            if (isset($seen[$sm])) {
                continue;
            }
            $seen[$sm] = true;

            try {
                CrawlUrlValidator::assertHttpUrlAllowed($sm);
                $xml = $this->crawlService->fetch($sm, expectHtml: false);
            } catch (\Throwable) {
                continue;
            }

            $parsed = $this->parseSitemapXml($xml);
            foreach ($parsed['nested'] as $nested) {
                if (! isset($seen[$nested])) {
                    $queue[] = $nested;
                }
            }
            foreach ($parsed['urls'] as $url) {
                if (count($out) >= $maxUrls) {
                    break;
                }
                $out[$url] = true;
            }
        }

        $urls = array_keys($out);
        sort($urls);

        $via = $robotsUrls !== [] ? 'robots.txt + sitemap' : 'dự đoán đường dẫn sitemap';

        return [
            'urls' => $urls,
            'via' => $via,
        ];
    }

    /**
     * @return list<string>
     */
    private function extractSitemapsFromRobots(string $base): array
    {
        try {
            $body = $this->crawlService->fetch(rtrim($base, '/').'/robots.txt', expectHtml: false);
        } catch (\Throwable) {
            return [];
        }

        $found = [];
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if (stripos($line, 'sitemap:') === 0) {
                $href = trim(substr($line, strlen('sitemap:')));
                if ($href !== '') {
                    $found[] = $href;
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * @return list<string>
     */
    private function defaultSitemapCandidates(string $base): array
    {
        $root = rtrim($base, '/');

        return [
            $root.'/sitemap.xml',
            $root.'/sitemap_index.xml',
            $root.'/sitemap-index.xml',
        ];
    }

    /**
     * @return array{nested: list<string>, urls: list<string>}
     */
    private function parseSitemapXml(string $xml): array
    {
        $nested = [];
        $urls = [];

        libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        if ($sx === false) {
            return ['nested' => [], 'urls' => []];
        }

        $sx->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($sx->xpath('//sm:sitemap/sm:loc') ?: [] as $loc) {
            $u = trim((string) $loc);
            if ($u !== '') {
                $nested[] = $u;
            }
        }

        foreach ($sx->xpath('//sm:url/sm:loc') ?: [] as $loc) {
            $u = trim((string) $loc);
            if ($u !== '') {
                $urls[] = $u;
            }
        }

        if ($nested === [] && $urls === []) {
            foreach ($sx->sitemap ?? [] as $s) {
                $u = trim((string) ($s->loc ?? ''));
                if ($u !== '') {
                    $nested[] = $u;
                }
            }
            foreach ($sx->url ?? [] as $item) {
                $u = trim((string) ($item->loc ?? ''));
                if ($u !== '') {
                    $urls[] = $u;
                }
            }
        }

        return [
            'nested' => array_values(array_unique($nested)),
            'urls' => array_values(array_unique($urls)),
        ];
    }
}
