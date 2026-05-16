<?php

namespace App\Services;

class CrawlUrlValidator
{
    public static function assertHttpUrlAllowed(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL không hợp lệ.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \InvalidArgumentException('Chỉ hỗ trợ http/https.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new \InvalidArgumentException('Không được truy cập host này.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \InvalidArgumentException('Không được truy cập địa chỉ IP nội bộ.');
            }
        }
    }

    /**
     * Chuẩn hoá domain/số URL thành base https URL.
     */
    public static function normalizeBaseInput(string $input): string
    {
        $t = trim($input);
        if ($t === '') {
            throw new \InvalidArgumentException('Vui lòng nhập domain hoặc URL.');
        }

        if (! str_contains($t, '://')) {
            $t = 'https://'.$t;
        }

        $parts = parse_url($t);
        if ($parts === false || empty($parts['host'])) {
            throw new \InvalidArgumentException('Không đọc được host từ đầu vào.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \InvalidArgumentException('Chỉ hỗ trợ http/https.');
        }

        return strtolower($scheme).'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
