# Kế hoạch triển khai — AI Content Crawler CMS (Laravel 10 + Filament)

**Mục đích tài liệu:** Mô tả hướng triển khai, cấu trúc file và thay đổi database để bạn rà soát trước khi viết code. Đối chiếu với `_docs/*` và `.cursor/rules/*`.

---

## 1. Nguyên tắc (ưu tiên)

1. **Workflow ổn định:** luồng sitemap → chọn URL / nhập URL thủ công → crawl → AI → chỉnh sửa → lưu; lỗi rõ ràng, không lưu dữ liệu không hợp lệ.
2. **Trải nghiệm reviewer (admin):** giao diện Filament tiếng Việt, form rõ ràng, loading/notifications, bảng có tìm kiếm/lọc, xem trước/modal khi cần.
3. **Không over-engineer:** một `Post` (hoặc tên entity tương đương) làm trung tâm; service tách bạch trách nhiệm; không repository pattern trừ khi thật sự cần.
4. **Kỹ thuật:** Laravel 10, PHP 8.1, DI, Form Request, Eloquent; toàn bộ gọi AI nằm trong `AIService`.

---

## 2. Kế hoạch triển khai (theo giai đoạn)

### Giai đoạn A — Nền tảng Laravel + Filament

- Khởi tạo Laravel 10, cấu hình `.env` cho database.
- **Database dev:** có thể dùng **Supabase (PostgreSQL)** cho cả môi trường local và production (xem mục *Supabase cho dev local* bên dưới). Hoặc MySQL local nếu bạn muốn — Laravel hỗ trợ cả hai; chỉ cần `DB_CONNECTION` khớp.
- Cài Filament v3 (tương thích Laravel 10), bật panel admin, ngôn ngữ UI tiếng Việt (label, section, notification).
- Auth admin: dùng user mặc định Filament / bảng `users` chuẩn.

### Giai đoạn B — Database & model

- Migration bảng **`posts`** (đã chốt tên).
- Model `Post` + cast JSON (`ai_tags`, `metadata`), slug unique, index `source_url` unique — tránh import trùng theo `BUSINESS_RULES`.
- Cột **`status`** và **`last_ai_error`** đã chốt đưa vào migration (xem §4).

### Giai đoạn C — Services (business logic)

Triển khai các service đã phác trong `_docs/SERVICES_ARCHITECTURE.md` (có thể gom nhẹ nếu trùng):

| Service | Trách nhiệm chính |
|--------|-------------------|
| `SitemapService` | Phát hiện sitemap (robots.txt, `sitemap.xml`, `sitemap_index.xml`), parse XML, trả về danh sách URL. |
| `LinkDiscoveryService` | (Tùy chọn pha 1) Fallback khi không có sitemap — **pha đầu có thể bỏ**, chỉ manual URL. |
| `CrawlService` | HTTP fetch (Symfony HttpClient), timeout, user-agent hợp lý. |
| `ParserService` | DomCrawler: title, main content, ảnh đại diện, metadata; loại header/footer/nav/script. |
| `AIService` | `generateSummary`, `generateTags`, `rewriteContent`; retry; xử lý lỗi API; giữ nguyên `original_content`. |

**Controller mỏng:** ví dụ action Filament (custom page / livewire action) hoặc vài route API nội bộ gọi service, không nhồi logic parse/AI vào Resource.

### Giai đoạn D — Luồng Filament (crawler + duyệt)

- **Trang “Import nội dung” (wizard hoặc multi-step):**
  1. Nhập domain → gọi `SitemapService` → hiển thị danh sách URL (checkbox/table).
  2. Nếu không có sitemap → chuyển bước nhập URL thủ công (một hoặc nhiều URL — pha 1: một URL).
  3. Nút “Thu thập” → `CrawlService` + `ParserService` → lưu tạm vào form (hoặc tạo bản ghi `Post` ở trạng thái **draft**/`pending_review`).
  4. Gọi `AIService` → đổ vào các trường AI; nút “Phân tích lại” gọi lại service.
  5. Admin chỉnh sửa → “Lưu” → cập nhật slug/title nếu cần, chuyển **published** hoặc giữ draft tùy quy tắc đơn giản.

- **Resource quản lý:** CRUD Filament cho `Post`: listing responsive, filters, preview (modal hoặc slideOver), actions sửa/xóa.

### Giai đoạn E — Xử lý crawl / AI: đồng bộ trước, queue sau

- **Pha 1 (đã chốt):** chạy **đồng bộ** — khi admin bấm “Thu thập” / “Phân tích”, PHP xử lý xong trong **cùng một request** rồi mới trả về màn hình. Ưu điểm: luồng đơn giản, dễ debug, reviewer thấy lỗi ngay (notification). Nhược điểm: nếu trang nặng hoặc OpenAI chậm, trình duyệt có thể chờ lâu → cần **timeout hợp lý** và loading rõ trên UI.
- **Queue (job hàng đợi):** Laravel đưa việc crawl/AI vào **job chạy nền** (worker riêng), request trả về nhanh; admin hỏi trạng thái hoặc refresh. Phù hợp khi thao tác thường vượt giới hạn thời gian HTTP hoặc cần retry tự động nhiều lần.
- **Kết luận kỹ thuật:** bắt đầu **đồng bộ**; khi triển khai thật hoặc gặp timeout, thêm queue **mà không đổi service** (chỉ đổi chỗ gọi: `dispatch` job thay vì gọi trực tiếp).

### Giai đoạn F — An toàn & chất lượng

- Validate URL (scheme http/https, chặn SSRF cơ bản: IP nội bộ/metalink tùy mức độ).
- Logging lỗi crawl/AI; thông báo Filament khi thất bại.
- Test thủ công theo checklist trong `_docs/NOTES.md` (phần Test Checklist).

---

## 3. Cấu trúc thư mục / file (đề xuất)

Phù hợp Laravel 10 + Filament, logic tập trung `app/Services`:

```
app/
├── Filament/
│   ├── Pages/              # Trang Import / Wizard (tiếng Việt)
│   └── Resources/
│       └── PostResource.php
├── Http/
│   └── Requests/           # Form Request validate domain, URL, payload lưu bài
├── Models/
│   └── Post.php
├── Services/
│   ├── AIService.php
│   ├── CrawlService.php
│   ├── ParserService.php
│   ├── SitemapService.php
│   └── LinkDiscoveryService.php   # (optional, có thể thêm sau)
database/
├── migrations/
│   └── xxxx_create_posts_table.php
config/
├── services.php             # openai key / cấu hình provider
resources/lang/vi/           # (nếu cần) chuỗi dùng chung
```

- **Không** tách thêm layer Repository trừ khi số query/luồng phức tạp tăng.
- Prompt AI có thể đặt trong `config` hoặc class constants trong `AIService` (ngắn gọn, dễ sửa).

---

## 4. Thay đổi database

### 4.1 Bảng bắt buộc — `posts` (theo thiết kế hiện tại)

| Cột | Kiểu (MySQL/Postgres) | Ghi chú |
|-----|------------------------|---------|
| `id` | bigint PK | |
| `title` | string | Có thể lấy từ HTML hoặc chỉnh sau. |
| `slug` | string, unique | Sinh từ title khi lưu; xử lý trùng (suffix). |
| `source_url` | text | Nên unique index để **tránh import trùng** (theo business rules). |
| `original_content` | longText | Luôn giữ bản gốc sau crawl. |
| `ai_summary` | text, nullable | Tiếng Việt. |
| `ai_tags` | json, nullable | Mảng string 3–5 tag. |
| `ai_rewritten_content` | longText, nullable | |
| `thumbnail` | string, nullable | URL hoặc path storage. |
| `metadata` | json, nullable | OpenGraph, excerpt, v.v. |
| `status` | string hoặc enum | **Đã chốt.** Ví dụ: `draft`, `ready`, `published` (có thể rút gọn `draft` / `published` nếu muốn ít trạng thái hơn). |
| `last_ai_error` | text, nullable | **Đã chốt.** Lỗi API AI lần cuối; hiển thị cho admin, không ghi đè `original_content`. |
| `created_at`, `updated_at` | timestamp | |

### 4.2 Bảng tùy chọn — `crawl_logs` (tương lai)

Giữ như `_docs/DATABASE_DESIGN.md`: log URL, status, `error_message` — **không triển khai ngay** nếu muốn giảm phạm vi; ưu tiên log file + notification trước.

### 4.3 Bảng `users`

Giữ chuẩn Laravel / Filament.

---

## 5. Supabase cho dev local — có ổn không?

**Có — hoàn toàn hợp lý** nếu bạn chấp nhận vài điểm sau:

| Khía cạnh | Ghi chú |
|-----------|---------|
| **Tương thích Laravel** | Supabase là PostgreSQL chuẩn; đặt `DB_CONNECTION=pgsql`, thông tin host/port/database/user/password từ Supabase. |
| **Giống production** | Dev và prod cùng engine (Postgres) → ít khác biệt kiểu dữ liệu (JSON, `text`, migration) so với MySQL local. |
| **Mạng & độ trễ** | Mỗi query đi qua internet; dev **cần mạng ổn định**. Không có mạng = không chạy DB local như file SQLite/MySQL — đổi lại không phải cài server DB trên máy. |
| **Bảo mật** | Không commit chuỗi kết nối vào git; chỉ dùng `.env`. Có thể tạo project Supabase riêng cho dev hoặc dùng branch/database riêng tùy thói quen team. |
| **Conn pooling** | Supabase thường khuyên connection string “pooler” cho serverless; Laravel app cổ điển (PHP-FPM) thường dùng port/session trực tiếp — làm theo tài liệu Supabase cho “direct” khi cần. |

**Kết luận:** dùng Supabase cho dev local là **một lựa chọn tốt** để đồng bộ với `DEPLOYMENT.md` (Railway + Supabase); chỉ cần lưu ý phụ thuộc mạng và cấu hình `.env` đúng.

---

## 6. Rủi ro & giảm phạm vi (MVP)

- **Sitemap index lớn:** giới hạn số URL hiển thị/phân trang; hoặc chỉ lấy N URL đầu trong MVP.
- **Trang generic HTML:** Parser dùng heuristic (article/main, readability đơn giản); chấp nhận một số trang kém chất lượng, bổ sung case sau.
- **OpenAI:** rate limit / lỗi mạng → thông báo + nút thử lại; không ghi đè `original_content`.

---

## 7. Checklist trước khi code

- [x] **Tên bảng:** `posts`.
- [x] **Cột bổ sung:** `status` + `last_ai_error` (kèm các cột gốc trong `_docs/DATABASE_DESIGN.md`).
- [x] **Pha 1 crawl/AI:** **chạy đồng bộ** trong request (giải thích ở §2 — Giai đoạn E); queue để sau khi cần.
- [ ] Xác nhận `.env`: `OPENAI_API_KEY`, và chuỗi kết nối DB (Supabase Postgres cho dev hoặc MySQL local nếu đổi ý).

---

*Tài liệu này là bản kế hoạch cho bước review; chưa bao gồm mã nguồn.*
