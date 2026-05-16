<?php

namespace App\Services;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CrawlService
{
    private HttpClientInterface $client;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? HttpClient::create($this->defaultClientOptions());
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultClientOptions(): array
    {
        $options = [
            'timeout' => (int) config('crawl.timeout', 30),
            'max_duration' => (int) config('crawl.max_duration', 45),
            'headers' => [
                'User-Agent' => 'ThacoContentCrawler/1.0 (+https://laravel.com)',
                'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            ],
        ];

        $cafile = config('crawl.cafile');
        if (is_string($cafile) && $cafile !== '' && is_file($cafile)) {
            $options['cafile'] = $cafile;
        }

        if (! (bool) config('crawl.verify_ssl', true)) {
            $options['verify_peer'] = false;
            $options['verify_host'] = false;
        }

        return $options;
    }

    /**
     * @param  bool  $expectHtml  false = robots.txt, sitemap XML, v.v. (không bắt buộc Content-Type HTML)
     */
    public function fetch(string $url, bool $expectHtml = true): string
    {
        CrawlUrlValidator::assertHttpUrlAllowed($url);

        $response = $this->client->request('GET', $url, [
            'max_redirects' => 5,
        ]);

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new \RuntimeException("Tải trang thất bại (HTTP {$status}).");
        }

        $contentType = strtolower($response->getHeaders(false)['content-type'][0] ?? '');
        if ($expectHtml && $contentType !== '' && ! $this->contentTypeLooksLikeHtml($contentType)) {
            throw new \RuntimeException('URL không trả về HTML hợp lệ.');
        }

        return $response->getContent();
    }

    private function contentTypeLooksLikeHtml(string $contentType): bool
    {
        foreach (['html', 'text/html', 'application/xhtml', 'text/plain'] as $needle) {
            if (str_contains($contentType, $needle)) {
                return true;
            }
        }

        return false;
    }
}
