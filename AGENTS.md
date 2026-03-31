# BakeFlow POS - Project Instructions

## Architecture

This project follows the same custom PHP MVC pattern as Ronga (`/Users/walterngwenya/Desktop/Source/Ronga`).

- **Webroot**: `site/` - only `site/` is exposed to the web server
- **Entry point**: `site/index.php` -> `app/bootstrap.php` -> `app/routes.php` -> `Router::dispatch()`
- **Database**: MySQL via `app/Core/Database.php`
- **Remote sync**: Optional secondary MySQL connection via `app/Core/RemoteDatabase.php` (configure in `.env`)
- **Auth**: Session-based - `app/Core/Auth.php`; cashiers use PIN, admins use password
- **Views**: `views/` - POS/login use `View::renderNoLayout()`, admin uses `View::render()` with NobleUI layout
- **POS frontend**: Pure HTML5 + CSS3 + vanilla JS - no jQuery, no Bootstrap, no frameworks

## File Structure

```text
bakeflow-pos/
|-- app/
|   |-- bootstrap.php          <- Loads all classes, starts session, initialises MySQL
|   |-- routes.php             <- All Router::get/post() registrations
|   |-- Core/                  <- Database, Session, Auth, Router, View, Env, RemoteDatabase
|   `-- Controllers/
|       |-- AuthController.php
|       |-- PosController.php
|       |-- Admin/             <- Dashboard, Product, Category, User, Settings, Reports
|       `-- Api/               <- Products, Sale, Receipt, Sync (all return JSON)
|-- views/
|   |-- auth/login.php         <- Standalone PIN pad (no layout)
|   |-- pos/index.php          <- Standalone POS screen (no layout)
|   |-- admin/                 <- NobleUI-styled admin views
|   `-- layouts/app.php        <- NobleUI admin layout
|-- assets/
|   |-- css/style.css          <- POS + print + admin utility styles
|   `-- js/pos.js              <- Full POS logic (vanilla JS)
|-- database/
|   |-- schema_mysql.sql       <- Base MySQL schema
|   |-- seed_mysql.sql         <- Default products, users, settings
|   `-- migrations/            <- Incremental MySQL migrations
|-- site/                      <- WEBROOT - point your server here
|   |-- index.php
|   |-- .htaccess
|   `-- assets/                <- NobleUI + custom assets served here
|-- .env                       <- Config (never commit)
`-- sync/                      <- Push/pull/cron scripts
```

## Development

Local stack uses XAMPP.

```bash
# Start dev server (webroot is site/)
C:\xampp\php\php.exe -S localhost:8080 -t "site/"
```

Visit: http://localhost:8080/login

**Default credentials:**
- Admin: username `admin`, password `admin`
- Cashier: username `alice` or `bob`, PIN `1234`

Change these immediately via `/admin/users`.

## Database

Configure MySQL in `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bakeflow_pos
DB_USERNAME=root
DB_PASSWORD=
```

On first run, `app/Core/Database.php` creates and seeds the database from:
- `database/schema_mysql.sql`
- `database/seed_mysql.sql`

To reset, drop and recreate the MySQL database, then reload the app.

## Rules

- `declare(strict_types=1)` in every PHP file
- All DB queries via PDO prepared statements - no string interpolation
- CSRF token in every POST form: `<input type="hidden" name="_csrf_token" value="...">`
- POS screen: pure vanilla JS only - no jQuery, no Bootstrap, no frameworks
- Admin panel: NobleUI (Bootstrap 5) - use Ronga's assets in `site/assets/`
- Security: PIN stored as bcrypt hash; admin passwords as bcrypt hash
- Transactions are immutable - no edits, only voiding (future feature)

## Deployment

- Point webserver to `site/` as document root
- `app/`, `views/`, `database/`, `assets/` must be above webroot
- Ensure the configured MySQL database exists and is reachable by the web server
- Copy `.env.example` to `.env` and configure

## Adding Products / Customising

All branding, products, prices, and settings are managed via `/admin` - no code changes needed for deployment to a new bakery client.

## Remote Sync (Phase 2)

Configure in `.env`:

```env
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
