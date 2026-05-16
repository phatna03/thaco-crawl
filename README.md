# CMS Crawler — AI Content CMS

Ứng dụng quản trị nội dung: crawl / import từ web, lưu bài viết, phân tích & viết lại bằng AI (OpenAI), duyệt trong panel admin Filament.

## Đang dùng trong codebase

| Thành phần | Cách dùng thực tế |
|------------|-------------------|
| **PHP 8.2+**, **Laravel 10** | Toàn bộ backend, Eloquent, validation, config |
| **Filament 3.2** | Admin (`/admin`): đăng nhập session, resource Bài viết, trang Import nội dung, đổi mật khẩu qua menu user |
| **Laravel Sanctum** | Model `User` dùng `HasApiTokens`; route `GET /api/user` (token).  |
| **Illuminate HTTP Client** (Guzzle) | Gọi API AI trong `AIService` |
| **symfony/http-client** | Tải trang / crawl trong `CrawlService` |
| **symfony/dom-crawler** | Parse HTML trong `ParserService` |

**Services nghiệp vụ** (`app/Services/`): `CrawlService`, `CrawlUrlValidator`, `ParserService`, `SitemapService`, `PageLinkDiscoveryService`, `AIService`.

**Cấu hình crawl**: `config/crawl.php` (timeout, SSL, giới hạn import/ngày), biến `CRAWL_*` trong `.env`.

**Tùy biến UI Filament** (bảng bài viết mobile, CSS): `AdminPanelProvider` + `resources/views/filament/admin/hooks/`.

## Kiến trúc (ngắn)

- Controller mỏng; nghiệp vụ trong `app/Services`.
- Logic AI tập trung `AIService` (driver `AI_DRIVER`: `openai`).
- Giao diện admin tiếng Việt, responsive; chi tiết trong [`_docs/`](_docs/).

## Yêu cầu môi trường

- PHP 8.2+, Composer (Railway Railpack chỉ hỗ trợ PHP từ 8.2)
- Database tương thích Laravel (MySQL, PostgreSQL, …)
- Supabase
- Railway

## Cài đặt nhanh

```bash
cp .env.example .env
php artisan key:generate
composer install
php artisan migrate
php artisan serve
```

Chỉnh database, `APP_URL`, khóa **OpenAI** và tùy chọn crawl trong `.env` — xem [`.env.example`](.env.example).

## Tài liệu trong repo

- [_docs/PROJECT_OVERVIEW.md](_docs/PROJECT_OVERVIEW.md)
- [_docs/TECH_STACK.md](_docs/TECH_STACK.md)
- [_docs/DEPLOYMENT.md](_docs/DEPLOYMENT.md)
- [_docs/AI_INTEGRATION.md](_docs/AI_INTEGRATION.md)

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
