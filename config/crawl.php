<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTP client (Symfony HttpClient) dùng cho crawl / sitemap
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('CRAWL_HTTP_TIMEOUT', 30),

    'max_duration' => (int) env('CRAWL_HTTP_MAX_DURATION', 45),

    /**
     * Kiểm tra chứng chỉ TLS (nên giữ true trên production).
     * Trên Windows nếu thiếu CA trong php.ini, nên dùng CRAWL_CAFILE thay vì tắt hoàn toàn.
     */
    'verify_ssl' => filter_var(env('CRAWL_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),

    /**
     * Đường dẫn tới file CA bundle (ví dụ cacert.pem từ https://curl.se/ca/cacert.pem ).
     * Giúp tránh lỗi "unable to get local issuer certificate" khi PHP chưa cấu hình curl.cainfo.
     */
    'cafile' => env('CRAWL_CAFILE'),

    /**
     * Giới hạn số bài import (tạo mới) mỗi ngày; null = không giới hạn.
     * (Theo múi APP_TIMEZONE / server.)
     */
    'daily_post_import_limit' => env('DAILY_POST_IMPORT_LIMIT') !== null && env('DAILY_POST_IMPORT_LIMIT') !== ''
        ? max(0, (int) env('DAILY_POST_IMPORT_LIMIT'))
        : null,

];
