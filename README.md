# Product Management API

Laravel 11 REST API for a small product catalog. Users log in, manage products (with images), filter the list, and get alerts when stock runs low. Built to pair with the Vue frontend in `product-management-frontend` (same parent folder).

Auth uses **Laravel Sanctum** (Bearer token on API routes).

---

## What you need installed

- PHP 8.2+ (extensions: `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`)
- Composer
- MySQL or MariaDB (what we used locally) — SQLite works too if you change `.env`
- Node is only required for the frontend, not this repo

Optional but needed for low-stock **email**:

- A mail account (Mailtrap for testing is fine)
- Queue worker running (see below)

---

## First-time setup

Clone the repo, then from the project root:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env` for your database. Example for XAMPP/MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=product_management_backend
DB_USERNAME=root
DB_PASSWORD=
```

Create the empty database in phpMyAdmin or MySQL before migrating.

Run migrations and link public storage (product images are served from here):

```bash
php artisan migrate
php artisan storage:link
```

Start the API:

```bash
php artisan serve
```

Default: `http://127.0.0.1:8000`. All routes live under `/api` (e.g. `POST /api/login`).

Point the frontend `.env` at this:

```env
VITE_API_URL=http://127.0.0.1:8000/api
```

Set `APP_URL` to the same host you use for the API (`http://127.0.0.1:8000` is better than `http://localhost` if the frontend calls `127.0.0.1`).

---

## Mail and low-stock queue

Low-stock emails are **not** sent inside the HTTP request. A job goes on the queue.

In `.env`:

```env
QUEUE_CONNECTION=database
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=...
LOW_STOCK_NOTIFY_EMAIL=you@example.com
```

`LOW_STOCK_NOTIFY_EMAIL` is the single inbox that gets alert emails (company admin style — not the logged-in user’s email).

After changing mail settings, clear config cache if you ever ran `config:cache`:

```bash
php artisan config:clear
```

Run a worker in a **second terminal** while developing:

```bash
php artisan queue:work
```

For quick local testing without a worker you can set `QUEUE_CONNECTION=sync` — email sends immediately on save.

**Mailtrap:** sandbox catches mail in the Mailtrap website. It won’t land in a real Gmail inbox until you use a real SMTP/sending setup.

---

## Features (backend side)

### Auth

- Register and login return a Sanctum token.
- Logout revokes the current token.
- All product and notification routes need `Authorization: Bearer {token}`.

### Products

- CRUD for products: name, description, price, stock quantity, low stock threshold.
- Each product belongs to the user who created it (`user_id`); list/detail can include `user` for “created by”.
- **List** supports search (name/description), min/max price, low-stock filter, sort (name, price, stock, created_at), pagination (`rows`, `page`). Response also includes `low_stock_count` for the banner on the frontend.
- **Images:** create/update accept `multipart/form-data` with `images[]` files. Files go to `storage/app/public/products/{id}/`. Paths are stored in `product_images`; JSON includes a `url` accessor.
- **Update** can send `remove_images[]` (image IDs) to delete files from disk and DB.
- **Bulk delete:** `PATCH /api/products/list-action` with `action: delete` + `ids`, or `action: delete-all`.

Low stock is when `stock_quantity <= low_stock_threshold`. The model exposes `is_low_stock` on the JSON.

### Low stock notifications and email

When a product is **created or updated** and is low stock:

1. `ProductLowStock` event fires.
2. `CreateStockNotification` listener (auto-registered by Laravel — don’t add a second `Event::listen` for the same class or you’ll get duplicates):
   - Inserts a row in `stock_notifications` (message + `product_id`).
   - Dispatches `SendLowStockEmailJob` to the queue.
3. The job sends `LowStockAlertMail` to `LOW_STOCK_NOTIFY_EMAIL` using the Blade view `resources/views/emails/low-stock.blade.php`.

The frontend bell reads these rows via the notifications API — separate from email.

### In-app notifications API

- `GET /api/notifications` — paginated list
- `GET /api/notifications/unread-count`
- `PATCH /api/notifications/{id}/read`
- `PATCH /api/notifications/read-all`

---

## How the low-stock flow fits together

Rough path a new developer can follow:

```
Save product (create/update)
  → ProductController checks is_low_stock
  → event(ProductLowStock)
  → CreateStockNotification
       → stock_notifications table (for API / bell)
       → SendLowStockEmailJob (queue)
  → queue:work runs job
  → Mail to LOW_STOCK_NOTIFY_EMAIL
```

Frontend only hits the notification endpoints; it never sends mail itself.

---

## Image uploads (for Postman or frontend)

Use `multipart/form-data`, not JSON.

Fields: `name`, `description`, `price`, `stock_quantity`, `low_stock_threshold`, optional `images[]` (repeat for multiple files), optional `remove_images[]` on update.

PHP doesn’t parse multipart bodies on real `PUT` requests well, so the Vue app posts to `/api/products/{id}` with `_method=PUT` in the form.

Images are available at:

`{APP_URL}/storage/products/{product_id}/{filename}`

Requires `php artisan storage:link`.

---

## Useful commands

```bash
php artisan migrate:fresh          # wipe and remigrate (dev only)
php artisan queue:work             # process emails
php artisan queue:failed           # if jobs fail
php artisan config:clear           # after .env mail changes
php artisan event:list             # check ProductLowStock has ONE listener
php artisan storage:link           # once per deploy
```

---

## Project layout (the parts that matter)

```
app/
  Events/ProductLowStock.php
  Listeners/CreateStockNotification.php
  Jobs/SendLowStockEmailJob.php
  Mail/LowStockAlertMail.php
  Http/Controllers/
    AuthController.php
    ProductController.php
    NotificationController.php
  Models/
    Product.php
    ProductImage.php
    StockNotification.php
    User.php
database/migrations/     # users, products, product_images, stock_notifications, jobs
resources/views/emails/low-stock.blade.php
routes/api.php
```

---

## Troubleshooting

**Emails not sending**

- Is `php artisan queue:work` running?
- `php artisan config:show mail` — `default` should be `smtp`, not `log`.
- Run `php artisan config:clear` after editing `.env`.
- Is `LOW_STOCK_NOTIFY_EMAIL` set?
- Check `failed_jobs` and `storage/logs/laravel.log`.

**Two emails / two notifications per save**

- Run `php artisan event:list --event=App\\Events\\ProductLowStock`. You should see **one** listener. Duplicate `Event::listen()` in a service provider plus auto-discovery caused this before.

**Images 404**

- `php artisan storage:link`
- `APP_URL` should match how you open the API.

**CORS / frontend can’t reach API**

- Frontend `VITE_API_URL` must end with `/api`.
- Serve API with `artisan serve` or configure your vhost to the `public` folder.

---

## Frontend repo

See `../product-management-frontend/README.md` for `npm install`, `npm run dev`, and how the UI calls this API.

---

## License

MIT (Laravel framework license applies to framework code).
