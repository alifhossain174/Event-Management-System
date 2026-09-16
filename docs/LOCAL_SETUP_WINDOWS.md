# Local Setup on Windows and XAMPP

## Prerequisites

This project targets Laravel 12 on PHP 8.2. The verified local tools are XAMPP PHP 8.2, Composer 2, Node.js and npm for asset compilation, Git, and the XAMPP MySQL service (MariaDB-compatible).

## Start XAMPP and create the database

1. Open the XAMPP Control Panel.
2. Start Apache when using the Apache URL option below.
3. Start MySQL before migrations or application requests that use the database.
4. Confirm the database service:

~~~powershell
& 'E:\xampp8.2\mysql\bin\mysqladmin.exe' --protocol=tcp --host=127.0.0.1 --port=3306 --user=root ping
~~~

5. Create the local database if it does not exist:

~~~powershell
& 'E:\xampp8.2\mysql\bin\mysql.exe' --protocol=tcp --host=127.0.0.1 --port=3306 --user=root --execute="CREATE DATABASE IF NOT EXISTS event_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
~~~

Create the separate PHPUnit database as well. Feature tests use `RefreshDatabase` and must never target the development schema:

~~~powershell
& 'E:\xampp8.2\mysql\bin\mysql.exe' --protocol=tcp --host=127.0.0.1 --port=3306 --user=root --execute="CREATE DATABASE IF NOT EXISTS event_management_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
~~~

`phpunit.xml` fixes `DB_DATABASE=event_management_testing` for automated tests. Do not point this value at a schema containing development or production records.

The default XAMPP development configuration uses the local root account with a blank password. Keep MySQL bound to localhost. Use a dedicated least-privilege account and a secret password for any shared or production environment.

## Install and configure the project

From E:\xampp8.2\htdocs\EventManagement:

~~~powershell
composer install
Copy-Item -LiteralPath '.env.example' -Destination '.env'
php artisan key:generate
npm ci
php artisan storage:link
php artisan migrate
npm run build
php artisan test
vendor\bin\pint
~~~

Do not overwrite an existing .env. If it already exists, preserve its values and update only the settings that are intentionally changing. The expected local database values are:

~~~dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=event_management
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log
~~~

## Create the first administrator

Public registration is disabled. After migrations and role seeding, create the first Administrator / Business Manager interactively so no password is stored in source control:

~~~powershell
php artisan db:seed --class=RolePermissionSeeder
php artisan app:create-administrator
~~~

For repeatable local-only setup, you may instead set `DEV_ADMIN_NAME`, `DEV_ADMIN_EMAIL`, and `DEV_ADMIN_PASSWORD` in the uncommitted `.env`, then run `php artisan db:seed`. Leave all three values blank in `.env.example` and production source control.

## URL option 1: Laravel development server

Apache is not required for this option:

~~~powershell
php artisan serve --host=127.0.0.1 --port=8000
~~~

Open http://127.0.0.1:8000. The health endpoint is http://127.0.0.1:8000/up.

For live asset development, run npm run dev in a second terminal. Production and normal shared-hosting requests use npm run build output and do not require Node.js.

## URL option 2: XAMPP Apache

The simplest local URL is http://localhost/EventManagement/public, but a virtual host is preferred because Laravel's document root should be the public directory.

Add a local-only virtual host to E:\xampp8.2\apache\conf\extra\httpd-vhosts.conf:

~~~apacheconf
<VirtualHost *:80>
    ServerName event-management.local
    DocumentRoot "E:/xampp8.2/htdocs/EventManagement/public"

    <Directory "E:/xampp8.2/htdocs/EventManagement/public">
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
~~~

Add 127.0.0.1 event-management.local to C:\Windows\System32\drivers\etc\hosts from an Administrator editor, validate Apache, and restart Apache from the XAMPP Control Panel:

~~~powershell
& 'E:\xampp8.2\apache\bin\httpd.exe' -t
~~~

Then set APP_URL=http://event-management.local, clear cached configuration with php artisan config:clear, and open http://event-management.local. Never point Apache at the repository root.

## PHP extension and path checks

Confirm that the CLI uses XAMPP PHP:

~~~powershell
Get-Command php
php --version
php --ini
php -m
~~~

Laravel 12 requires Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, and XML. This application also needs pdo_mysql; bcmath, gd, exif, intl, xmlreader, xmlwriter, and zip are useful for later SRS features.

Check common extensions precisely:

~~~powershell
php -r '$required=["ctype","curl","dom","fileinfo","filter","hash","mbstring","openssl","pcre","pdo","pdo_mysql","session","tokenizer","xml"]; foreach ($required as $extension) { echo $extension.": ".(extension_loaded($extension) ? "enabled" : "MISSING").PHP_EOL; }'
~~~

If an extension is missing, stop Apache, back up E:\xampp8.2\php\php.ini, uncomment only its extension line, then restart Apache. Do not make machine-wide PHP or PATH changes.

## Common checks

~~~powershell
php artisan --version
php artisan about --only=environment,cache,drivers
php artisan migrate:status
php artisan route:list
composer validate --strict
npm run build
php artisan test
vendor\bin\pint
~~~

If public/storage is absent or points to the wrong place, remove only that link after verifying its resolved target, then run php artisan storage:link again.
