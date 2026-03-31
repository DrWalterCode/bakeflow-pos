# BakeFlow POS Local Setup

This document covers the full local setup for another Windows machine using XAMPP Apache, MySQL, and HTTPS on `bakeflow.local`.

## Recommended local URLs

- HTTPS: `https://bakeflow.local/login`
- HTTPS admin: `https://bakeflow.local/admin`
- HTTP fallback: `http://bakeflow.local/login`
- PHP built-in server fallback: `http://localhost:8080/login`

## Prerequisites

- Windows
- XAMPP installed in `C:\xampp`
- Apache enabled in XAMPP
- MySQL enabled in XAMPP
- PHP available from XAMPP: `C:\xampp\php\php.exe`
- Project checked out locally

## 1. Clone the project

Clone the repository somewhere outside `htdocs`, for example:

```powershell
git clone <repo-url> "C:\Users\YourUser\source\repos\BakeFlow POS"
```

For the rest of this guide, replace:

```text
C:/Users/YourUser/source/repos/BakeFlow POS
```

with your actual project path.

## 2. Create the local database

Open XAMPP MySQL or phpMyAdmin and create:

```sql
CREATE DATABASE bakeflow_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

You do not need to manually import the schema for a fresh local setup. The app boot process creates the schema and seeds the database on first load.

## 3. Create `.env`

Copy `.env.example` to `.env` and adjust values as needed:

```env
APP_NAME="BakeFlow POS"
APP_ENV=local
APP_DEBUG=1
APP_URL=https://bakeflow.local
APP_TIMEZONE=Africa/Harare

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bakeflow_pos
DB_USERNAME=root
DB_PASSWORD=

REMOTE_DB_HOST=
REMOTE_DB_PORT=3306
REMOTE_DB_DATABASE=
REMOTE_DB_USERNAME=
REMOTE_DB_PASSWORD=

SYNC_API_KEY=
SYNC_INTERVAL=300
```

## 4. Add the local host entry

Edit the real Windows hosts file as Administrator:

```text
C:\Windows\System32\drivers\etc\hosts
```

Add:

```text
127.0.0.1 bakeflow.local
```

Do not use `hosts.ics` for this. Use the actual `hosts` file.

## 5. Configure Apache virtual hosts

Edit:

```text
C:\xampp\apache\conf\extra\httpd-vhosts.conf
```

Add or update these vhosts:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/Users/YourUser/source/repos/BakeFlow POS/site"
    ServerName bakeflow.local
    ServerAlias localhost

    <Directory "C:/Users/YourUser/source/repos/BakeFlow POS/site">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/bakeflow-error.log"
    CustomLog "logs/bakeflow-access.log" common
</VirtualHost>

<VirtualHost *:443>
    DocumentRoot "C:/Users/YourUser/source/repos/BakeFlow POS/site"
    ServerName bakeflow.local
    ServerAlias localhost

    SSLEngine on
    SSLCertificateFile "C:/xampp/apache/conf/ssl.crt/bakeflow.local.crt"
    SSLCertificateKeyFile "C:/xampp/apache/conf/ssl.key/bakeflow.local.key"

    <Directory "C:/Users/YourUser/source/repos/BakeFlow POS/site">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "logs/bakeflow-ssl-error.log"
    CustomLog "logs/bakeflow-ssl-access.log" common
</VirtualHost>
```

Make sure these includes are enabled in:

```text
C:\xampp\apache\conf\httpd.conf
```

Required lines:

```apache
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-vhosts.conf
Include conf/extra/httpd-ssl.conf
```

## 6. Generate the local SSL certificate

Run in PowerShell:

```powershell
$env:OPENSSL_CONF='C:\xampp\apache\conf\openssl.cnf'
& 'C:\xampp\apache\bin\openssl.exe' req -x509 -nodes -newkey rsa:2048 -sha256 -days 825 `
  -keyout 'C:\xampp\apache\conf\ssl.key\bakeflow.local.key' `
  -out 'C:\xampp\apache\conf\ssl.crt\bakeflow.local.crt' `
  -subj '/CN=bakeflow.local' `
  -addext 'subjectAltName=DNS:bakeflow.local,DNS:localhost,IP:127.0.0.1' `
  -addext 'basicConstraints=CA:FALSE' `
  -addext 'keyUsage=digitalSignature,keyEncipherment' `
  -addext 'extendedKeyUsage=serverAuth'
```

## 7. Trust the local certificate

If the browser shows a certificate warning, import:

```text
C:\xampp\apache\conf\ssl.crt\bakeflow.local.crt
```

into:

```text
Current User > Trusted Root Certification Authorities
```

You can do that by:

1. Double-clicking the `.crt` file.
2. Clicking `Install Certificate`.
3. Choosing `Current User`.
4. Choosing `Place all certificates in the following store`.
5. Selecting `Trusted Root Certification Authorities`.

If you prefer PowerShell, run it in an elevated session if required by your machine policy.

## 8. Restart Apache

Use XAMPP Control Panel, or restart Apache from PowerShell:

```powershell
Get-Process httpd -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Process -FilePath 'C:\xampp\apache\bin\httpd.exe' -ArgumentList '-d','C:/xampp/apache'
```

## 9. Open the app

Use:

- `https://bakeflow.local/login`
- `https://bakeflow.local/admin`

Default credentials:

- Admin: `admin` / `admin`
- Cashier: `alice` or `bob` with PIN `1234`

## 10. Fallback dev mode without Apache/SSL

If you just need the app running quickly without Apache:

```powershell
C:\xampp\php\php.exe -S localhost:8080 -t site
```

Then open:

- `http://localhost:8080/login`
- `http://localhost:8080/admin`

This mode is HTTP only. It does not provide HTTPS.

## Troubleshooting

### `bakeflow.local` does not resolve

- Recheck `C:\Windows\System32\drivers\etc\hosts`
- Run `ipconfig /flushdns`
- Reopen the browser

### HTTPS opens the XAMPP dashboard instead of BakeFlow

- Recheck `httpd-vhosts.conf`
- Make sure Apache was restarted after the vhost change
- Make sure the `*:443` vhost points to the project `site` directory

### Apache starts but routes like `/login` fail

- Make sure `.htaccess` exists in `site\`
- Make sure `mod_rewrite` is loaded
- Make sure `AllowOverride All` is set for the project directory

### Browser says the certificate is not trusted

- Import `bakeflow.local.crt` into `Current User > Trusted Root Certification Authorities`
- Close and reopen the browser completely

### Database connection fails

- Verify MySQL is running in XAMPP
- Verify `.env` matches your local MySQL username, password, and database name
- Confirm the `bakeflow_pos` database exists
