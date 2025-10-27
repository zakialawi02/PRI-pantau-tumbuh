# PRI Pantau Tumbuh

PRI Pantau Tumbuh is a smart satellite-based monitoring system for accurately detecting plant health and stress. This project provides a comprehensive solution for monitoring growth patterns and developmental milestones. PRI Pantau Tumbuh is a platform that combines Sentinel-2 satellite data, AI-assisted processing, and a credit-based business model to help growers and analysts monitor plant stress with Photochemical Reflectance Index (PRI) maps.

## Table of Contents

-   [Overview](#overview)
-   [Key Features](#key-features)
-   [Tech Stack](#tech-stack)
-   [Project Layout](#project-layout)
-   [Getting Started](#getting-started)
    -   [Prerequisites](#prerequisites)
    -   [Installation](#installation)
    -   [Database & Seed Data](#database--seed-data)
    -   [Frontend Assets](#frontend-assets)
-   [Background Jobs & Scheduling](#background-jobs--scheduling)
-   [Python Imagery Pipeline](#python-imagery-pipeline)
-   [Environment Reference](#environment-reference)
-   [API & Documentation](#api--documentation)
-   [Testing & Quality](#testing--quality)
-   [Troubleshooting](#troubleshooting)
-   [Contributing](#contributing)
-   [License](#license)

## Overview

The application ships with a full dashboard for managing users, plans, payments, and imagery workflows. A public-facing map experience lets authenticated users browse Sentinel-2 collections, clip scenes to registered fields, and download processed rasters once background jobs finish. The system tracks credit balances, refreshes currency exchange rates, integrates with PayPal, and exposes a Sanctum-secured API for user management.

## Key Features

-   **Role-based dashboard** with `superadmin`, `admin`, and `user` access, social login (Google, GitHub, Facebook), and Sanctum API tokens.
-   **Credit marketplace** covering plan management, PayPal checkout, manual proof uploads, and audit trails through `user_credits` and `user_credit_histories`.
-   **Imagery lifecycle** including chunked uploads, retryable processing, GeoServer publishing, and Sentinel-2 scene/clip orchestration through queued jobs.
-   **Sentinel map client** (`/app/imagery`) with token-aware Copernicus downloads, WMS previews, field overlays, and credit balance hints.
-   **Exchange rate service** that caches remote currency pairs, falls back to configurable rates, and drives multi-currency pricing.
-   **Scheduled maintenance** commands to expire overdue payments and refresh exchange tables.
-   **Observability hooks** for Laravel Telescope, Sentry, IPInfo geolocation, and optional Gemini integrations.
-   **Python tooling** in `scripts/` for advanced imagery processing, GEE access, and AI-assisted PRI estimation.

## Tech Stack

-   **Backend:** PHP 8.2+, Laravel 12, Laravel Sanctum, Yajra DataTables, Socialite, Srmklive PayPal SDK.
-   **Frontend:** Vite, Tailwind CSS, Alpine.js, Remix Icons, custom map widgets (`resources/js/map.js`, `resources/css/map.css`).
-   **Storage & Queue:** MySQL (default), database-backed queues, Laravel Filesystem (`storage/app/public`) with `php artisan storage:link`.
-   **External Services:** Copernicus Data Space, GeoServer REST API, PayPal, optional Stripe placeholders, Sentry.
-   **Data Processing:** Laravel queues (`processing`, `default`) and Python scripts under `scripts/`.

## Project Layout

```text
.
|-- app/
|   |-- Console/Commands/        # Scheduled artisan commands (exchange:update, payments:expire-overdue)
|   |-- Http/Controllers/        # Web, API, dashboard, Socialite, payments, imagery, map
|   |-- Jobs/                    # Imagery ingestion, Sentinel scene/clip processing, chunk merging
|   |-- Models/                  # Core entities (User, Plan, Payment, ImageryData, FieldArea, ExchangeRate, Credits)
|   `-- Services/                # Copernicus tokens, GeoServer, Credit logic, Payment gateways, Exchange rates
|-- resources/
|   |-- views/pages/front/       # Public pages, map client, marketing content
|   |-- views/pages/dashboard/   # Admin & user dashboards
|   |-- js/                      # app.js, map.js, helpers, navigation
|   `-- css/                     # Tailwind entrypoints, map styles
|-- routes/
|   |-- web.php                  # Web routes, dashboards, imagery, payments, map
|   |-- api.php                  # /api/v1 endpoints with Sanctum auth
|   `-- api/auth.php             # Authentication endpoints (login, register, password reset)
|-- database/
|   |-- migrations/              # Plans, payments, imagery, credits, exchange rates, Telescope, etc.
|   `-- seeders/                 # Default users, plans, credit balances
|-- scripts/                     # Python & Node imagery tooling (Earth Engine, PRI pipelines)
|-- docs/                        # Postman/OpenAPI definitions
|-- public/                      # Compiled assets and public uploads
`-- config/                      # Service configuration (exchange, queue, telescope, services)
```

## Getting Started

### Prerequisites

-   PHP **8.2+** with required extensions (`pdo_mysql`, `mbstring`, `openssl`, `curl`, `intl`, `bcmath`, `exif`, `fileinfo`).
-   Composer **2.x**.
-   Node.js **18+** (recommended 20.x) and npm **10+** for Vite.
-   MySQL/MariaDB instance.
-   Optional: Redis or another queue backend if you switch from the default database queue.
-   Optional: Python **3.11+** for imagery scripts (virtual environment recommended).

### Installation

1. **Clone the repository**

    ```bash
    git clone https://github.com/zakialawi02/PRI-pantau-tumbuh.git
    cd PRI-pantau-tumbuh
    ```

2. **Install PHP dependencies**

    ```bash
    composer install
    ```

3. **Install JavaScript dependencies**

    ```bash
    npm install
    ```

4. **Prepare environment configuration**

    ```bash
    cp .env.example .env
    php artisan key:generate
    php artisan storage:link
    ```

5. **Configure your `.env`** (see [Environment Reference](#environment-reference)).

### Database & Seed Data

Run the migrations and seeders to bootstrap plans, credits, and default accounts:

```bash
php artisan migrate --seed
```

Seeders provision three users you can use to explore the app:

| Role       | Email                 | Password     |
| ---------- | --------------------- | ------------ |
| Superadmin | `superadmin@mail.com` | `superadmin` |
| Admin      | `admin@mail.com`      | `admin`      |
| User       | `user@mail.com`       | `user`       |

### Frontend Assets

-   Development build with hot reload:
    ```bash
    npm run dev
    ```
-   Production build:
    ```bash
    npm run build
    ```
-   All-in-one local stack (artisan serve + queue listener + Vite):
    ```bash
    composer run dev
    ```

Serve the application locally with:

```bash
php artisan serve
```

## Background Jobs & Scheduling

-   **Queue workers:** Imagery uploads, Sentinel scene pulls, and credit refunds run on the `processing` and `default` queues. Start at least one worker that listens to both:

    ```bash
    php artisan queue:work --queue=default,emails,download,processing
    ```

    For long-running workers consider `supervisor` or `pm2`.

-   **Scheduled tasks:** Add an entry to your system cron (or run `php artisan schedule:work` or `php artisan schedule:run`) to execute:

    -   `exchange:update` weekly (refresh remote currency rates).
    -   `payments:expire-overdue` every five minutes (mark unpaid invoices as expired).

-   **Telescope:** Enable with `TELESCOPE_ENABLED=true` and visit `/telescope` (guarded by auth).

## Python Imagery Pipeline

Advanced Sentinel processing lives in the `scripts/` directory. To use it:

```bash
cd scripts
python -m venv .venv
.\.venv\Scripts\activate      # Windows
source .venv/bin/activate   # Linux/macOS
pip install -r requirements.txt
```

Authenticate with Google Earth Engine if you run the `example_*` scripts:

```bash
earthengine authenticate
```

Key entry points:

-   `getImagery*.py`: download and mosaic Sentinel data.
-   `process_to_multispectral_auto.py`, `process_clipped_multispectral_auto.py`: auto-generate multispectral/PRI outputs.
-   `download_imagery.py`: used by queued Laravel jobs to request Copernicus scenes.

## Environment Reference

Group related settings in `.env`:

-   **App basics:** `APP_NAME`, `APP_URL`, `APP_TIMEZONE`, `APP_LOCALE`.
-   **Database & cache:** `DB_*`, `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`.
-   **Mail & notifications:** `MAIL_*` (log driver by default), `SENTRY_LARAVEL_DSN`, `TELESCOPE_ENABLED`.
-   **Auth & social login:** `SANCTUM_STATEFUL_DOMAINS`, `GOOGLE_*`, `GITHUB_*`, `FACEBOOK_*`.
-   **Copernicus access:** `COPERNICUS_CLIENT_ID`, `COPERNICUS_CLIENT_SECRET`, optional `COPERNICUS_TOKEN_CACHE_SECONDS`.
-   **GeoServer integration:** `GEOSERVER_URL`, `GEOSERVER_USER`, `GEOSERVER_PASS`, `GEOSERVER_WORKSPACE`.
-   **Payments & credits:** `STRIPE_*` (placeholder), `PAYPAL_MODE`, `PAYPAL_{SANDBOX|LIVE}_CLIENT_ID`, `PAYPAL_{SANDBOX|LIVE}_CLIENT_SECRET`, `PAYPAL_CURRENCY`, `PAYPAL_VALIDATE_SSL`.
-   **Exchange rates:** `EXCHANGE_API_BASE_URL`, `EXCHANGE_CACHE_TTL`, `FALLBACK_RATE_USD_IDR`, `FALLBACK_RATE_IDR_USD`.
-   **Utilities:** `IPINFO_TOKEN`, `GEMINI_API_KEY`, `CONTACT_FORM_APP_SCRIPT`.

### Copernicus Data Space Credentials

1. Sign in at https://dataspace.copernicus.eu/ or https://shapps.dataspace.copernicus.eu/ (create an ESA account if you do not already have one).
2. Open your profile menu and choose **My Account** / **User Setting** -> **OAuth clients**.
3. Create a new OAuth client (service account) or reuse an existing one, then copy the generated client ID and client secret.
4. Add the values to `.env`:
    ```env
    COPERNICUS_CLIENT_ID=your-client-id
    COPERNICUS_CLIENT_SECRET=your-client-secret
    # COPERNICUS_TOKEN_CACHE_SECONDS=3300
    ```
5. Restart your Laravel workers (queue, horizon, scheduler) so fresh tokens are requested on the next Sentinel-2 download.

## API & Documentation

-   REST endpoints are scoped under `/api/v1` and protected with Laravel Sanctum. See `routes/api.php` for current resources.
-   Authentication endpoints live in `routes/api/auth.php` and cover login, register, password reset, email verification, and logout.
-   Machine-readable documentation is bundled in `docs/` (`laravel-restfull-api.postman_collection.json`, OpenAPI YAML/JSON).

## Testing & Quality

-   PHP test suite:
    ```bash
    php artisan test
    ```
-   Static analysis & formatting:
    ```bash
    ./vendor/bin/phpstan analyse
    ./vendor/bin/pint
    ```
-   Frontend linting/formatting (configure as needed):
    ```bash
    npm run lint
    npm run format
    ```
-   Optional helpers:
    -   `php artisan telescope:install` to (re)publish Telescope assets.
    -   `npm run dev-all` in `package.json` for combined dev tooling.

## Troubleshooting

-   **Imagery stuck in “waiting” status:** ensure the queue worker is running and has access to Python scripts if processing requires them. Check `storage/logs/laravel.log` for job exceptions.
-   **Currency conversions stale:** run `php artisan exchange:update` manually or verify the scheduler is active. Fallback rates can be tuned with `FALLBACK_RATE_*`.
-   **PayPal webhooks / callbacks:** confirm `APP_URL` matches the public URL reachable by PayPal and that `PAYPAL_MODE` aligns with the configured credentials.
-   **GeoServer errors:** validate workspace credentials and that the GeoServer REST service is reachable from the Laravel host.

## Contributing

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/amazing-feature`).
3. Commit (`git commit -m "Describe feature"`).
4. Push and open a Pull Request.

Guidelines: follow PSR-12, add/adjust tests, keep documentation up to date, and ensure all queues and scheduled jobs remain green.

## Support and Donations

For additional support, please:

1. Check the troubleshooting section
2. Review existing issues in the repository
3. Create a new issue with detailed information
4. Contact the development team

-   **Documentation**: [Wiki](https://github.com/zakialawi02/PRI-pantau-tumbuh/wiki)
-   **Issues**: [GitHub Issues](https://github.com/zakialawi02/PRI-pantau-tumbuh/issues)
-   **Discussions**: [GitHub Discussions](https://github.com/zakialawi02/PRI-pantau-tumbuh/discussions)

If you find this project useful and would like to support its further development, you can make a donation via the following platforms:

https://ko-fi.com/zakialawi

Every contribution you make is greatly appreciated. Thank you!

## License

This project is distributed under the **PRI Pantau Tumbuh Limited Use License**. See [`LICENSE`](LICENSE) for details and contact `hallo@zakialawi.my.id` for commercial access.
