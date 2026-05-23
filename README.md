# Product Management API

## What this is

A **product inventory API** for a small team or shop: users sign up, add products (name, price, stock, images), search and filter the catalog, and get **low-stock alerts** in the app and by email. Each user owns the products they create; a **super admin** (one email in `.env`) can manage everything.

This repo is the **backend** (Laravel 11 + Sanctum). The **Vue 3 UI** lives in **`../product-management-frontend`**.

---

## Requirements

| Required | Notes |
|----------|--------|
| PHP 8.2+ | Extensions: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo` |
| Composer | |
| MySQL / MariaDB | SQLite OK if you change `.env` |
| Frontend | Node.js — only for the Vue app, not this repo |

| Optional (low-stock **email**) | |
|--------------------------------|--|
| SMTP (e.g. Mailtrap) | Set `MAIL_*` in `.env` |
| Queue worker | `php artisan queue:work` — or use `QUEUE_CONNECTION=sync` locally |

---

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

**Database** — create empty DB, then in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=product_management_backend
DB_USERNAME=root
DB_PASSWORD=
```

```bash
php artisan migrate
php artisan storage:link
php artisan serve
```

API base: `http://127.0.0.1:8000/api`

**Frontend** (separate repo):

```env
VITE_API_URL=http://127.0.0.1:8000/api
```

Use the same host for `APP_URL` (prefer `127.0.0.1` over `localhost` if the UI uses `127.0.0.1`).

### Important `.env` keys

| Variable | Purpose |
|----------|---------|
| `SUPER_ADMIN_EMAIL` | Email that gets super-admin rights (edit/delete any product, clear all alerts). Not a password — user must register/login with this email. |
| `LOW_STOCK_NOTIFY_EMAIL` | Inbox for low-stock **emails** |
| `QUEUE_CONNECTION` | `database` + `queue:work`, or `sync` for instant mail in dev |
| `MAIL_*` | SMTP settings |
| `FRONTEND_URL` | Vue app URL for password reset links in email |

After changing `.env`: `php artisan config:clear` (especially if you used `config:cache`).

---

## Run day-to-day

| Terminal | Command |
|----------|---------|
| API | `php artisan serve` |
| Emails (if `QUEUE_CONNECTION=database`) | `php artisan queue:work` |
| Frontend | `cd ../product-management-frontend && npm run dev` |

Protected routes need header: `Authorization: Bearer {token}` from login/register.

---

## API overview

**Auth:** `POST /register`, `POST /login`, `POST /logout`, `GET /user`

**Forgot password (public, no token)**

| Step | API | Notes |
|------|-----|--------|
| 1 | `POST /forgot-password` `{ "email": "..." }` | Email must exist in `users` |
| 2 | User opens link in email | `{FRONTEND_URL}/reset-password?token=...&email=...` |
| 3 | `POST /reset-password` | `{ "email", "token", "password", "password_confirmation" }` |

**`POST /forgot-password` responses**

- **200** — `{ "message": "We sent a password reset link to your email." }` (mail sent via `MAIL_*`)
- **422** — email not registered: `{ "errors": { "email": ["No account found with this email address."] } }`

**`POST /reset-password` responses**

- **200** — `{ "message": "Password updated. You can sign in now." }`
- **422** — invalid/expired token or validation errors

Reset mail uses `App\Notifications\ResetPasswordNotification`. Tokens live in `password_reset_tokens` (60 min expiry). Set `FRONTEND_URL` in `.env` (default `http://localhost:5173`). Password reset mail is sent synchronously (not queued).

**Products:** `GET/POST /products`, `GET/PUT/DELETE /products/{id}`, `PATCH /products/list-action`

List query params: `search`, `min_price`, `max_price`, `low_stock=1`, `mine=1`, `sort`, `order`, `rows`, `page`

**Notifications:** `GET /notifications`, `GET /notifications/unread-count`, `PATCH .../read`, `PATCH /notifications/read-all`, `DELETE /notifications` (super admin only)

**Images:** `multipart/form-data` on create/update; use `POST` + `_method=PUT` for updates. Public URL: `{APP_URL}/storage/products/{id}/...` (needs `storage:link`).

**Roles**

- **Super admin** (`SUPER_ADMIN_EMAIL`): manage any product, delete-all bulk, clear all alerts, mark any alert read.
- **Normal user:** see all products/alerts; edit/delete own products only; mark read own alerts only; filter own list with `mine=1`.

Authorization uses `ProductPolicy` + `App\Support\SuperAdmin` (email match, not a DB column). JSON includes `user.is_super_admin` — re-login after changing `SUPER_ADMIN_EMAIL`.

**Low stock:** `stock_quantity <= low_stock_threshold` → DB notification + queued email to `LOW_STOCK_NOTIFY_EMAIL`.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| **500 / DB errors** | DB exists? `.env` credentials correct? Run `php artisan migrate` |
| **401 on API** | Send `Authorization: Bearer {token}`; login again if token expired |
| **Images 404** | `php artisan storage:link`; `APP_URL` matches how you open the API |
| **Frontend can’t reach API** | `VITE_API_URL` must end with `/api`; API served from `public/` |
| **Emails not sent** | Run `queue:work` OR set `QUEUE_CONNECTION=sync`; `config:clear`; check `mail.default` is `smtp` not `log`; set `LOW_STOCK_NOTIFY_EMAIL` |
| **Reset link wrong host** | Set `FRONTEND_URL` in backend `.env` to your Vue URL (e.g. `http://localhost:5173`) |
| **Reset token invalid** | Link expires in 60 min; request a new link; use the latest email |
| **“No account found” on forgot** | User must register first; email must match `users.email` exactly |
| **No reset email received** | Configure `MAIL_*` (not `log`); check spam; `config:clear` |
| **Duplicate emails/alerts** | `php artisan event:list` — `ProductLowStock` should have **one** listener (no duplicate `Event::listen` in providers) |
| **Super admin UI wrong** | `.env` email matches login email; `config:clear`; log out and log in again on frontend |
| **Failed jobs** | `php artisan queue:failed` · `storage/logs/laravel.log` |
| **PHP 8.5 MySQL warnings** | `composer install` runs vendor patch script; see `scripts/patch-php85-mysql-pdo.php` |

**Useful commands**

```bash
php artisan config:clear
php artisan queue:work
php artisan storage:link
php artisan migrate:fresh   # dev only — wipes data
```

---

## Frontend

`../product-management-frontend/README.md` — `npm install`, `npm run dev`.

---

MIT
