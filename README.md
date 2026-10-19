# LinkPilot

LinkPilot is a Laravel and React publishing console for owned short links and publishable link-in-bio pages.

## Included workflows

- Create, edit, pause, delete, and inspect owned short links.
- Group owned short links in private folders.
- Schedule link availability and require an access code before a public redirect.
- Download locally generated SVG QR codes for owned short links.
- Follow an active short URL and record one privacy-minimal click event.
- Create multiple bio pages, order named outbound buttons, and publish only active buttons at `/bio/{handle}`.

## QR codes

The authenticated QR dashboard serves each owned link through
`GET /links/{link}/qr-code.svg`. LinkPilot generates SVG bytes locally with
Endroid QR Code; it does not send the destination or QR image through an
external image service. Each QR code encodes the absolute public short-link
URL, so normal availability and access-code controls still apply after a scan.

## Requirements

- Docker Engine with Docker Compose v2.

The default local database is SQLite at `database/database.sqlite`. The app is served at [http://localhost:8000](http://localhost:8000).

## First run

```bash
cp .env.example .env
touch database/database.sqlite
docker compose build app
docker compose run --rm app composer install
docker compose run --rm app npm install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose run --rm app npm run build
docker compose up --build
```

Keep the final command running while using the application. In another terminal, rerun migrations after pulling schema changes:

```bash
docker compose run --rm app php artisan migrate
```

Local email verification uses the log mailer. After registering, open the verification URL written to `storage/logs/laravel.log`.

## Verification commands

```bash
docker compose run --rm app php artisan test
docker compose run --rm app npm run build
```

## Production asset rebuild

After changing React or CSS source, rebuild the Vite manifest:

```bash
docker compose run --rm app npm run build
```
