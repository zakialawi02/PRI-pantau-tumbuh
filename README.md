# PRI Pantau Tumbuh - Project

## Overview

PRI Pantau Tumbuh is a smart satellite-based monitoring system for accurately detecting plant health and stress. This project provides a comprehensive solution for monitoring growth patterns and developmental milestones

## Table of Contents

-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Usage](#usage)
-   [Contributing](#contributing)
-   [Development](#development)
-   [Testing](#testing)
-   [Deployment](#deployment)
-   [License](#license)
-   [Support](#Support)
-   [Changelog](#Changelog)

## Installation

1. **Clone the repository**

    ```bash
    git clone https://github.com/zakialawi02/PRI-pantau-tumbuh
    cd starterpack-laravel12
    ```

2. **Install PHP dependencies**

    ```bash
    composer install
    ```

3. **Install Node.js dependencies**

    ```bash
    npm install
    ```

4. **Environment setup**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

5. **Database setup**

    ```bash
    php artisan migrate --seed
    ```

6. **Build frontend assets**
    ```bash
    npm run build
    ```

## Configuration

### Environment Variables

Update your `.env` file with the following configurations:

```env
APP_NAME="PantauTumbuh.id"
APP_ENV=local
APP_KEY=base64:your-app-key
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pantau-tumbuh-db
DB_USERNAME=root
DB_PASSWORD=
```

-   `DB_HOST` - Database host
-   `DB_PORT` - Database port
-   `DB_DATABASE` - Database name
-   `DB_USERNAME` - Database username
-   `DB_PASSWORD` - Database password

### Copernicus Data Space access token

Downloading full Sentinel-2 scenes from the Copernicus Data Space Ecosystem requires a short-lived OAuth access token. Follow the steps below to obtain one and enable downloads inside the Sentinel panel:

1. Sign in at [dataspace.copernicus.eu](https://dataspace.copernicus.eu/).
2. Open your account menu and navigate to **API & Credentials**.
3. Create a service account (or reuse an existing one) to receive a **client ID** and **client secret**.
4. Request an access token by calling the token endpoint:

    ```bash
    curl -X POST \
         -d "grant_type=client_credentials" \
         -d "client_id=YOUR_CLIENT_ID" \
         -d "client_secret=YOUR_CLIENT_SECRET" \
         https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token
    ```

    The response contains an `access_token` string that remains valid for roughly one hour.

5. Copy the `access_token` value and store it in your Laravel environment file:

    ```env
    COPERNICUS_ACCESS_TOKEN="eyJhbGciOi..."
    ```

6. Reload the application (or redeploy if you are running in production) so the new token is embedded in the Sentinel panel downloads.

The token expires roughly every hour. When it does, repeat steps 4–6 with a freshly issued token.

### Database Configuration

1. Create a new database for your project
2. Update the database credentials in your `.env` file
3. Run migrations and seeders:
    ```bash
    php artisan migrate:fresh --seed
    ```

## Usage

### Development Server

Start the Laravel development server:

```bash
php artisan serve
```

Start the Vite development server for frontend assets:

```bash
npm run dev
```

### Default Credentials

After running the seeders, you can login with:

-   **Email**: superadmin@mail.com
-   **Password**: superadmin
    OR
-   **Email**: admin@mail.com
-   **Password**: admin
    OR
-   **Email**: user@mail.com
-   **Password**: user

## Development

### Code Style

This project follows PSR-12 coding standards. Run the following commands to maintain code quality:

```bash
# PHP CS Fixer
./vendor/bin/php-cs-fixer fix

# PHPStan (Static Analysis)
./vendor/bin/phpstan analyse

# ESLint (JavaScript)
npm run lint

# Prettier (Code Formatting)
npm run format
```

### Testing

```bash
php artisan test
```

## Deployment

### Production Setup

1. **Environment Configuration**

    ```bash
    APP_ENV=production
    APP_DEBUG=false
    ```

2. **Optimize for Production**

    ```bash
    composer install --optimize-autoloader --no-dev
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    npm run build
    ```

3. **Database Migration**
    ```bash
    php artisan migrate --force
    ```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

-   Follow PSR-12 coding standards
-   Write comprehensive tests for new features
-   Update documentation as needed
-   Ensure all tests pass before submitting PR

### Common Issues

#### Database Connection Error

-   Verify database credentials in `.env`
-   Ensure database server is running
-   Check network connectivity

#### Permission Errors

-   Verify file permissions for storage directories
-   Ensure web server has appropriate access rights

#### Performance Issues

-   Enable caching mechanisms
-   Optimize database queries
-   Check server resources

## Security

If you discover any security vulnerabilities, please send an email to hallo@zakialawi.my.id instead of using the issue tracker.

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

This project is licensed under the **PRI Pantau Tumbuh Limited Use License**. See the [LICENSE](LICENSE) file for details.

**Important**: This is a restrictive license that allows limited non-commercial use only. Commercial use requires a separate license. Contact hallo@zakialawi.my.id for commercial licensing inquiries.

## Changelog

See CHANGELOG.md for a detailed list of changes and updates.
