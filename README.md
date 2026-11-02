# InvoiceMini — companion app for "Laravel feature and unit tests, with a real app"

A small invoicing app built to teach testing on something real: clients, drafts, sent invoices
with numbers and due dates, a signed payment webhook, a PDF mailed from a queue, exchange
rates from an HTTP API, and overdue reminders on a schedule. 128 tests cover it: unit, feature
and browser.

The series that walks through it, part by part, is on
[shipbytes.dev](https://shipbytes.dev/blog/laravel-feature-and-unit-tests-with-a-real-app/).
Each part is one commit in this repository's history.

| Part | What it covers | Commit |
|---|---|---|
| 1 | The app's foundation, a login, and what feature versus unit means in Laravel | `Part 1:` |
| 2 | The billing domain as pure classes, and their unit tests | `Part 2:` |
| 3 | Invoice flows over HTTP: validation, policies, numbering, time | `Part 3:` |
| 4 | Fakes and time: webhook, queue, mail, storage, HTTP, notifications, commands | `Part 4:` |
| 5 | Database, factories, architecture tests, speed, parallel runs and CI | `Part 5:` |
| 6 | Browser tests with Pest and Playwright | `Part 6:` |

## Run it

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # demo login: demo@invoicemini.test / password
npm install && npm run build
php artisan serve
```

## Run the tests

```
./vendor/bin/pest                   # unit + feature + browser, SQLite in memory
./vendor/bin/pest --parallel
./vendor/bin/pest tests/Browser     # needs: npm install && npx playwright install chromium
./vendor/bin/pest --type-coverage --min=100
```

Against MySQL, the way CI does it:

```
docker run -d --name invoicemini-mysql -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=invoicemini_test -p 127.0.0.1:3310:3306 mysql:8.4
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3310 DB_DATABASE=invoicemini_test DB_USERNAME=root DB_PASSWORD=root ./vendor/bin/pest --parallel
```

Built on Laravel 13, Pest 5 and PHP 8.5 in October 2026. MIT.
