# BakeFlow POS — Project Instructions

## Architecture

This project follows the same custom PHP MVC pattern as Ronga (`/Users/walterngwenya/Desktop/Source/Ronga`).

- **Webroot**: `site/` — only `site/` is exposed to the web server
- **Entry point**: `site/index.php` → `app/bootstrap.php` → `app/routes.php` → `Router::dispatch()`
- **Database**: SQLite (local, offline-first) via `app/Core/Database.php`
- **Remote sync**: MySQL via `app/Core/RemoteDatabase.php` (configure in `.env`)
- **Auth**: Session-based — `app/Core/Auth.php`; cashiers use PIN, admins use password
- **Views**: `views/` — POS/login use `View::renderNoLayout()`, admin uses `View::render()` with NobleUI layout
- **POS frontend**: Pure HTML5 + CSS3 + vanilla JS — NO jQuery, NO Bootstrap, NO frameworks

## File Structure

```
bakeflow-pos/
├── app/
│   ├── bootstrap.php          ← Loads all classes, starts session, initialises SQLite DB
│   ├── routes.php             ← All Router::get/post() registrations
│   ├── Core/                  ← Database, Session, Auth, Router, View, Env, RemoteDatabase
│   └── Controllers/
│       ├── AuthController.php
│       ├── PosController.php
│       ├── Admin/             ← Dashboard, Product, Category, User, Settings, Reports
│       └── Api/               ← Products, Sale, Receipt, Sync (all return JSON)
├── views/
│   ├── auth/login.php         ← Standalone PIN pad (no layout)
│   ├── pos/index.php          ← Standalone POS screen (no layout)
│   ├── admin/                 ← NobleUI-styled admin views
│   └── layouts/app.php        ← NobleUI admin layout
├── assets/
│   ├── css/style.css          ← POS + print + admin utility styles
│   └── js/pos.js              ← Full POS logic (vanilla JS)
├── database/
│   ├── schema.sql             ← SQLite DDL (auto-run on first boot)
│   ├── seed.sql               ← Default products, users, settings
│   └── bakeflow.sqlite        ← Created at runtime (gitignored)
├── site/                      ← WEBROOT — point your server here
│   ├── index.php
│   ├── .htaccess
│   └── assets/                ← NobleUI + custom assets served here
├── .env                       ← Config (never commit)
└── sync/                      ← Push/pull/cron scripts
```

## Development

```bash
# Start dev server (webroot is site/)
php -S localhost:8080 -t "site/"
```

Visit: http://localhost:8080/login

**Default credentials:**
- Admin: username `admin`, password `admin`
- Cashier: username `alice` or `bob`, PIN `1234`

Change these immediately via `/admin/users`.

## Database

SQLite file is at the path in `.env` → `DB_SQLITE_PATH`.

On first run, `app/Core/Database.php` auto-creates and seeds the database from:
- `database/schema.sql`
- `database/seed.sql`

To reset: delete `database/bakeflow.sqlite` and restart.

## Rules

- `declare(strict_types=1)` in every PHP file
- All DB queries via PDO prepared statements — no string interpolation
- CSRF token in every POST form: `<input type="hidden" name="_csrf_token" value="...">`
- POS screen: pure vanilla JS only — no jQuery, no Bootstrap, no frameworks
- Admin panel: NobleUI (Bootstrap 5) — use Ronga's assets in `site/assets/`
- Security: PIN stored as bcrypt hash; admin passwords as bcrypt hash
- Transactions are immutable — no edits, only voiding (future feature)

## Deployment

- Point webserver to `site/` as document root
- `app/`, `views/`, `database/`, `assets/` must be **above** webroot
- SQLite file (`bakeflow.sqlite`) must be writable by the web server process
- Copy `.env.example` to `.env` and configure

## Adding Products / Customising

All branding, products, prices, and settings are managed via `/admin` — no code changes needed for deployment to a new bakery client.

## Remote Sync (Phase 2)

Configure in `.env`:
```
REMOTE_DB_HOST=your-mysql-host
REMOTE_DB_DATABASE=bakeflow_remote
REMOTE_DB_USERNAME=...
REMOTE_DB_PASSWORD=...
SYNC_API_KEY=...
SYNC_INTERVAL=300
```

The sync status badge on the POS screen polls `/api/sync/status` every 30 seconds.

## NobleUI Assets

NobleUI assets are in `site/assets/` (copied from Ronga's `site/assets/`). If Ronga's assets are updated, re-copy to keep in sync. Admin layout is `views/layouts/app.php`.
