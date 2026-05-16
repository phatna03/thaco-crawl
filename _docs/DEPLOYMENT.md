# Triển khai (GitHub + Railway + Supabase)

## Trước khi push GitHub (kiểm tra nhanh)

- `.env` **đã** trong `.gitignore` — không commit file này.
- **Không** để API key, mật khẩu DB, `APP_KEY` trong repo. Chỉ dùng `.env.example` (giá trị rỗng/minh họa).
- Migration seed admin (`FILAMENT_ADMIN_SEED_ON_MIGRATE`) **mặc định tắt** — không còn mật khẩu hardcode trong code.
- Nên **đổi mật khẩu** mọi tài khoản đã từng lộ trong lịch sử git (nếu có).

---

## Railway — các bước gợi ý

### 1. Chuẩn bị repo

- PHP **^8.2** trong `composer.json`; lock pin `config.platform.php` = **8.2.31** (khớp Railway PHP 8.2).
- Branch đã push lên GitHub (`composer.json`, `composer.lock`, `railway.toml`).

### 2. Tạo project trên Railway

1. [railway.app](https://railway.app) → **New Project** → **Deploy from GitHub repo** → chọn repo.
2. Railway dùng **Railpack** build PHP (FrankenPHP) + `composer install` tự động.
3. **Railpack — biến build (Railway → Variables)**

   | Biến | Giá trị | Ghi chú |
   |------|---------|---------|
   | `RAILPACK_PHP_EXTENSIONS` | `intl,zip` | **Nên có** — Filament + OpenSpout |
   | `RAILPACK_PHP_VERSION` | `8.4` | **Tùy chọn** — chỉ khi sau này nâng lock lên gói cần PHP 8.4+ |

   `composer.lock` được pin cho **PHP 8.2** (Railway mặc định ~8.2.31). **Không** dùng `railpack.json` với `packages.php` (lỗi `mise` / `bison`).

   **Lỗi build `Please provide a valid cache path`:** `config/view.php` dùng `storage_path('framework/views')` (không `realpath()`), vì Railpack chạy `config:cache` trước khi thư mục tồn tại trong layer build.

### 3. Database (Supabase Postgres)

1. Supabase → **Project Settings** → **Database** → lấy host, port `5432`, database `postgres`, user, password.
2. Bật **SSL** nếu Supabase yêu cầu (`DB_SSLMODE=require` hoặc tùy cấu hình Laravel `config/database.php`).

Ví dụ:

```env
DB_CONNECTION=pgsql
DB_HOST=db.xxxx.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your_password
DB_SSLMODE=require
```

### 4. Biến môi trường bắt buộc (Railway → Variables)

| Biến | Ghi chú |
|------|--------|
| `APP_KEY` | `php artisan key:generate --show` hoặc chạy `key:generate` một lần trên Railway shell. |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | URL public Railway (vd `https://xxx.up.railway.app`). |
| `DB_*` hoặc `DATABASE_URL` | Supabase |
| `OPENAI_API_KEY` | (hoặc Gemini nếu dùng) |
| `AI_DRIVER` | `openai` hoặc `gemini` |

Tùy chọn:

| Biến | Ghi chú |
|------|--------|
| `TRUSTED_PROXIES` | Để trống = tin mọi proxy (`*`) cho HTTPS sau load balancer. Chạy local không proxy: `TRUSTED_PROXIES=` (rỗng). |
| `FILAMENT_ADMIN_EMAIL` / `FILAMENT_ADMIN_PASSWORD` / `FILAMENT_ADMIN_NAME` | Cho `php artisan db:seed`. |
| `FILAMENT_ADMIN_SEED_ON_MIGRATE` | `true` chỉ khi muốn migration tạo user — **nên tắt** sau lần đầu. Khuyến nghị: `db:seed`. |
| `DAILY_POST_IMPORT_LIMIT` | Ví dụ `100` — giới hạn số bài tạo mới từ **Import** mỗi ngày. Để trống = không giới hạn. |
| `RAILPACK_PHP_EXTENSIONS` | `intl,zip` (build). |

### 5. Lệnh deploy

**Migrate** (mỗi lần release):

```bash
php artisan migrate --force
```

**Tạo user admin lần đầu** (sau khi đã set `FILAMENT_ADMIN_*`):

```bash
php artisan db:seed --force
```

**Tối ưu** (sau khi set đủ env):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Start Command** (ví dụ):

```bash
php artisan serve --host=0.0.0.0 --port=$PORT
```

(`$PORT` do Railway inject.)

### 6. Storage

- `php artisan storage:link` khi dùng file public qua `storage`.

---

## Bảo mật: repo public + Supabase

- **GitHub public:** rủi ro chính là từng commit nhầm secret — quét lịch sử; xoay key nếu lộ.
- **Supabase không RLS:** với stack **chỉ Laravel + Postgres connection**, client không cần anon key; ai có `DB_PASSWORD` mới vào được DB — **đừng lộ `.env` / Railway variables**.
- Nếu sau này gọi Supabase REST/Realtime từ trình duyệt: **bật RLS** + policy; không dùng `service_role` ở frontend.
- Production: `APP_DEBUG=false`, Filament mật khẩu mạnh, cập nhật framework.

---

## Giới hạn 100 bài / ngày

- Không bắt buộc cho security; hữu ích cho **chi phí AI + dung lượng**.
- Đặt `DAILY_POST_IMPORT_LIMIT=100` để giới hạn số bài **import mới** / ngày (theo timezone server).
