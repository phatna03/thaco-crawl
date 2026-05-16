<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | AI: openai (mặc định) hoặc gemini — đặt AI_DRIVER trong .env
    */
    'ai' => [
        'driver' => env('AI_DRIVER', 'openai'),
        'timeout' => (int) env('AI_HTTP_TIMEOUT', 120),
        /** Giới hạn độ dài nội dung gửi vào prompt (tiết kiệm token). */
        'max_input_chars' => (int) env('AI_MAX_INPUT_CHARS', 12000),
        /** Giới hạn token đầu ra mỗi lần gọi (OpenAI: max_tokens; Gemini: maxOutputTokens). */
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 4096),
        /** Số thẻ tối đa trong chuỗi (thực tế dùng 3–5). */
        'max_tag_count' => (int) env('AI_MAX_TAG_COUNT', 5),
        /** Tóm tắt (AI): tối đa số từ (cách nhau bằng khoảng trắng). */
        'summary_max_words' => (int) env('AI_SUMMARY_MAX_WORDS', 100),
        /** Bài viết lại: tối thiểu số từ. */
        'rewritten_min_words' => (int) env('AI_REWRITTEN_MIN_WORDS', 250),
        /** Văn phong mặc định khi trường trống (Import / bài cũ). */
        'default_writing_style' => env(
            'AI_DEFAULT_WRITING_STYLE',
            'Chuyên nghiệp, súc tích, dễ đọc; tiếng Việt tự nhiên; không phóng đại hay giọng quảng cáo.'
        ),
        /** Số lần thử lại khi lỗi tạm thời (rate limit, timeout, 5xx). */
        'retry_attempts' => max(1, (int) env('AI_RETRY_ATTEMPTS', 3)),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),
    ],

];
