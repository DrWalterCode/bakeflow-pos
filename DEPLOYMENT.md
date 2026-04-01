# BakeFlow POS — Remote Deployment Procedure

## Target Environment

| Item | Value |
|------|-------|
| Hosting | cPanel shared hosting (LiteSpeed) |
| Domain | system.zimbocrumbbakery.co.zw |
| Server IP | 192.250.239.56 |
| cPanel user | zimbocrumbbakery |
| Home dir | /home/zimbocrumbbakery |
| App dir | /home/zimbocrumbbakery/bakeflow |
| Document root | /home/zimbocrumbbakery/bakeflow/site |
| PHP version | 8.2 |
| Database | MySQL (zimbocrumbbakery_bkflow) |
| DB user | zimbocrumbbakery_bfpos |

## Directory Structure on Server

```
/home/zimbocrumbbakery/bakeflow/
├── .env                    ← DB credentials (never in git)
├── app/
│   ├── bootstrap.php
│   ├── routes.php
│   ├── Controllers/
│   │   ├── Admin/          ← 9 controllers
│   │   ├── Api/            ← 6 controllers
│   │   ├── AuthController.php
│   │   ├── BaseController.php
│   │   └── PosController.php
│   ├── Core/               ← 9 core classes
│   ├── Lib/                ← PdfWriter.php
│   └── Services/           ← DayEndReportService.php
├── assets/
│   ├── css/style.css
│   └── js/pos.js
├── database/
│   ├── schema_mysql.sql
│   ├── seed_mysql.sql
│   └── migrations/         ← 0001–0008
├── site/                   ← DOCUMENT ROOT
│   ├── .htaccess
│   ├── index.php
│   └── assets/             ← NobleUI + custom (css, js, images, vendors)
├── storage/
└── views/
    ├── admin/              ← Admin panel views
    ├── auth/login.php
    ├── layouts/app.php
    └── pos/index.php
```

## Deployment Methods

### Method 1: Claude Code with cPanel MCP (Recommended)

Use the `bakeflow-cpanel` MCP server from Claude Code. This is the fastest method for incremental updates.

#### Step-by-step:

**1. Check what changed:**
```bash
git status
git diff --name-only HEAD~1
```

**2. Upload changed files via MCP:**

For small/medium files (under ~500 lines), use `cpanel_write_file` directly:
```
cpanel_write_file(dir="/home/zimbocrumbbakery/bakeflow/app", filename="routes.php", content=<file content>)
```

For large files (pos.js, style.css), use the curl helper method:
1. Upload a temporary PHP upload script to `site/`:
```php
<?php
$key = 'YOUR_DEPLOY_KEY';
if (($_POST['key'] ?? '') !== $key) { http_response_code(403); exit; }
$target = $_POST['target'] ?? '';
if ($target === '' || str_contains($target, '..')) { http_response_code(400); exit; }
$base = dirname(__DIR__);
$path = $base . '/' . $target;
$dir = dirname($path);
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
echo file_put_contents($path, $_POST['content'] ?? '') !== false ? 'OK' : 'FAIL';
```
2. Copy local file to `/tmp/` (no spaces in path) and POST via curl:
```bash
cp "assets/js/pos.js" /tmp/pos.js
curl -s -X POST "https://system.zimbocrumbbakery.co.zw/_upload.php" \
  -F "key=YOUR_DEPLOY_KEY" \
  -F "target=assets/js/pos.js" \
  -F "content=</tmp/pos.js"
```
3. Clean up: overwrite `_upload.php` with `<?php // removed`

**3. Create new directories (if needed):**

`cpanel_create_directory` does NOT work on this host. Use a temp PHP script:
```php
<?php
$dirs = ['/home/zimbocrumbbakery/bakeflow/app/NewDir'];
foreach ($dirs as $d) {
    echo $d . ' => ' . (@mkdir($d, 0755, true) ? 'OK' : (is_dir($d) ? 'EXISTS' : 'FAIL')) . "\n";
}
```
Upload to `site/`, hit via HTTP, then clean up.

**4. Run database migrations (if schema changed):**

The app auto-runs migrations on boot via `Database.php`. If you need to manually run SQL, use a temp PHP script:
```php
<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pdo = \App\Core\Database::getConnection();
$sql = file_get_contents(__DIR__ . '/../database/migrations/XXXX_new_migration.sql');
$pdo->exec($sql);
echo 'Done';
```

**5. Verify deployment:**
```bash
curl -s -o /dev/null -w "%{http_code}" "https://system.zimbocrumbbakery.co.zw/login"
# Should return 200
```

### Method 2: Manual via cPanel File Manager

1. Log in to cPanel at the hosting provider
2. Open File Manager → navigate to `/home/zimbocrumbbakery/bakeflow/`
3. Upload changed files to their respective directories
4. Verify at https://system.zimbocrumbbakery.co.zw/login

### Method 3: Git-based (Future)

If SSH access becomes available:
```bash
ssh zimbocrumbbakery@192.250.239.56
cd /home/zimbocrumbbakery/bakeflow
git pull origin main
```

## Database Setup (First Time Only)

The database was created via cPanel UAPI:
```bash
/usr/bin/uapi Mysql create_database name=zimbocrumbbakery_bkflow
/usr/bin/uapi Mysql create_user name=zimbocrumbbakery_bfpos password=<PASSWORD>
/usr/bin/uapi Mysql set_privileges_on_database user=zimbocrumbbakery_bfpos database=zimbocrumbbakery_bkflow privileges=ALL%20PRIVILEGES
```

The `.env` file must contain:
```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=zimbocrumbbakery_bkflow
DB_USERNAME=zimbocrumbbakery_bfpos
DB_PASSWORD=<PASSWORD>
```

On first page load, `Database.php` automatically creates all tables from `schema_mysql.sql` and seeds from `seed_mysql.sql`.

## .env Configuration

```env
APP_NAME="BakeFlow POS"
APP_ENV=production
APP_DEBUG=0
APP_URL=https://system.zimbocrumbbakery.co.zw
APP_TIMEZONE=Africa/Harare

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=zimbocrumbbakery_bkflow
DB_USERNAME=zimbocrumbbakery_bfpos
DB_PASSWORD=<see memory or cPanel>

REMOTE_DB_HOST=
REMOTE_DB_PORT=3306
REMOTE_DB_DATABASE=
REMOTE_DB_USERNAME=
REMOTE_DB_PASSWORD=

SYNC_API_KEY=
SYNC_INTERVAL=300
```

## LiteSpeed Caching Notes

- LiteSpeed may cache PHP responses; when deploying helper scripts, use unique filenames or add `?t=<timestamp>` query params
- After deploying CSS/JS changes, the app uses `?v=<?= time() ?>` cache-busting in the HTML, so no manual cache purge is needed

## Security Checklist

- [ ] `.env` is NOT committed to git
- [ ] `.htaccess` blocks direct access to `.env`, `.sql`, `.sqlite`, `.md`, `.log`, `.json` files
- [ ] Default admin password changed from `admin` via `/admin/users`
- [ ] Default cashier PINs changed from `1234` via `/admin/users`
- [ ] All temp deploy scripts (`_upload.php`, `_dbsetup.php`, etc.) removed after use
- [ ] `APP_DEBUG=0` in production `.env`

## Quick Deploy Checklist

For routine code pushes:

1. Identify changed files (`git diff --name-only`)
2. Upload each changed file via `cpanel_write_file` (or curl for large files)
3. If new directories needed → temp PHP mkdir script
4. If new migrations → they auto-run on next page load
5. Hit https://system.zimbocrumbbakery.co.zw/login to verify
6. Clean up any temp scripts
