# Dot.Mines

Dot.Mines is the fleet record for a mining operation. It holds machines, positions and telemetry, fuel, maintenance, geofences, operators and their assignments, and exposes them to people through the web application and to other Dot platforms through a versioned API, scheduled sync and signed webhooks.

`wiki.md` is the maintained account of what is built, what is verified and what is open. This README covers orientation, setup and operations.

## What it does

- Fleet: machines, status, health, cycle and loading times, assignments per mine area, subscription-enforced fleet slots.
- Live map and geofences: machine positions on OpenStreetMap or satellite imagery, mine areas, geofence entry and exit events, route waypoints and traffic management plans.
- Operators: licences, medicals, inductions, fatigue tracking and machine assignments with eligibility checks.
- Fuel: tanks, allocations, transactions, monthly budgets, consumption per machine and forecasts.
- Maintenance: preventive and corrective bookings, service history, health alerts, machine status set to maintenance on booking.
- Production: recorded against reported loads, shift targets, trends, CSV export.
- Operations feed: categorised posts with required fields, acknowledgements, comments, approvals, pins and digests.
- Alerts, compliance violations, incident reports and generated reports in PDF or CSV.
- Manufacturer integrations: one service class per manufacturer with stored credentials and poll-based sync. Only the Bell service is implemented against a published specification and none has completed a live sync against a real account. The application marks the rest as unverified or coming soon.
- API for other platforms: `/api/v1` with Sanctum bearer tokens, a `/sync` endpoint with scopes and tombstones, machine, alert, geofence, operator and assignment resources, and outbound webhooks signed with a per-endpoint secret.
- Billing through Stripe, team-based tenancy with roles and policies.

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, Laravel 12 |
| UI | Livewire 3, Alpine.js, Tailwind CSS, DaisyUI |
| Charts and maps | ApexCharts, Chart.js, Leaflet with Leaflet Draw |
| Database | PostgreSQL 16+ |
| Real time | Laravel Reverb, Laravel Echo |
| Queue | Database driver locally, Redis in production |
| Auth | Jetstream teams, Sanctum |
| Payments | Stripe through Cashier |
| Storage | S3 through Flysystem |
| Monitoring | Sentry |
| Analysis | PHPStan at level max and Psalm, both without baselines |
| Tests | PHPUnit 11 |

## Requirements

PHP 8.2 or newer, Composer 2, Node.js 18 or newer with npm, PostgreSQL 16 or newer. Redis, an S3 bucket, a Stripe account and a Sentry DSN are optional and only needed for the features that use them.

## Setup

```bash
composer run setup
```

That runs the Composer install, copies `.env.example` to `.env`, generates the application key, migrates and builds the front end. The manual equivalent:

```bash
git clone https://github.com/sakhileb/mines.git
cd mines
composer install
npm install
cp .env.example .env
php artisan key:generate
createdb mines
php artisan migrate
php artisan db:seed
npm run build
```

Set the database, Reverb, S3, Stripe and Sentry values in `.env` as needed. Never put a secret in a file that is committed; `DB_PASSWORD` and the other secrets are read from the environment.

## Running locally

```bash
composer run dev
```

That starts the Laravel server, the queue listener, the log viewer and the Vite dev server together. The Reverb WebSocket server runs separately:

```bash
php artisan reverb:start
```

Named queues exist for webhooks and integration sync. To drain one by hand:

```bash
php artisan queue:work --queue=webhooks --stop-when-empty
```

## Integrating another platform

Mint an API token for a team with the console command below. It warns when the user lacks a permission the token would need.

```bash
php artisan integration:mint-token user@example.com "Team name" --abilities=read,create,update,delete
```

Outbound webhooks are configured per team under Integrations. Deliveries carry `X-Mines-Signature` with a timestamp and an HMAC over the body. Private and loopback endpoint addresses are refused unless `WEBHOOKS_ALLOW_PRIVATE_URLS=true` is set on a non-production environment.

The API is described by the generated OpenAPI document; see `App\Services\OpenApiGenerator` and the route file `routes/api_v1.php`.

## Checks

```bash
php artisan test
vendor/bin/pint --dirty
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --memory-limit=1G
vendor/bin/psalm
```

Project rules for contributors live in `.ai/rules/`; read the index before editing.

## Project structure

```
app/
  Actions/        write operations, one class per operation
  Console/        artisan commands
  Events/         domain events, including webhook-dispatched ones
  Http/           controllers (web and Api), middleware, resources
  Jobs/           queued work: sync, webhook delivery, analysis
  Livewire/       page and inline components
  Models/         Eloquent models
  Policies/       authorisation
  Services/       integrations, webhooks, operators, AI analysis
config/           application configuration, including integrations.php
database/         migrations, factories, seeders
deploy/           queue worker and Reverb service definitions
resources/        Blade views, CSS, JavaScript
routes/           web.php, api.php, api_v1.php, channels.php, console.php
tests/            Feature and Unit suites
```

## Deployment

### Production Checklist

1. Set environment
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   ```

2. Install dependencies (no dev)
   ```bash
   composer install --optimize-autoloader --no-dev
   npm ci
   ```

3. Build frontend assets
   ```bash
   npm run build
   ```

4. Cache configuration
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```

5. Run migrations
   ```bash
   php artisan migrate --force
   ```

6. Set storage permissions
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

7. Start queue worker (use `deploy/queue-worker.service` for systemd or `deploy/queue-worker.supervisord.conf` for Supervisor). Requires `QUEUE_CONNECTION=redis`, matching `.env.production.example`.
   ```bash
   php artisan queue:work redis --tries=3 --timeout=90
   ```

8. Start the Reverb WebSocket server (use `deploy/reverb.service` for systemd or `deploy/reverb.supervisord.conf` for Supervisor) -- never run this as a bare foreground command in production, it needs the same process supervision as the queue worker.
   ```bash
   php artisan reverb:start
   ```
   Binds to `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` (loopback-only by default in `.env.production.example`) -- it is not meant to be reachable directly from the internet. See "WebSocket Reverse Proxy" below for how browsers actually reach it over `wss://`.

### Web Server Configuration

#### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name your-domain.com;
    root /var/www/mines/public;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Only Reverb's client-facing path (/app/{key}, what Echo/pusher-js
    # connects to) is proxied here. Its server-to-server publish API
    # (/apps/{id}/events etc.) is deliberately NOT exposed publicly --
    # config/broadcasting.php's reverb connection talks to it directly over
    # the internal REVERB_SERVER_HOST/PORT instead, so it never needs to be
    # reachable from outside this box.

    # WebSocket Reverse Proxy -- proxies wss://your-domain.com/app/... to the
    # internal reverb:start process (REVERB_SERVER_HOST/PORT, loopback-only
    # by default). The Upgrade/Connection headers are what turn this from a
    # plain HTTP proxy into a WebSocket one; without them the client's
    # protocol upgrade handshake fails and Echo falls back to polling or
    # errors outright. Long read_timeout because these are meant to be
    # long-lived connections, not request/response.
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
    }
}
```

#### Apache

Requires `mod_proxy`, `mod_proxy_wstunnel`, and `mod_ssl` enabled (`a2enmod proxy proxy_wstunnel ssl`).

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/mines/public

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/your-domain.com/privkey.pem

    <Directory /var/www/mines/public>
        AllowOverride All
        Require all granted
    </Directory>

    # WebSocket Reverse Proxy -- see the Nginx /app block above for what
    # this achieves and why. ProxyPass's own ws:// scheme (rather than
    # http://) is what makes mod_proxy_wstunnel handle the Upgrade
    # handshake instead of treating this as a normal HTTP proxy.
    ProxyPass        /app ws://127.0.0.1:8080/app
    ProxyPassReverse /app ws://127.0.0.1:8080/app
</VirtualHost>
```

---

## Troubleshooting

### Map Not Loading

- Check internet connectivity (CDN access required for tile layers)
- Check browser console for JavaScript errors
- Clear browser cache

### Session Expired / Frequent Logouts

```bash
php artisan config:clear
php artisan cache:clear
# Ensure SESSION_DRIVER=database in .env
```

### CSRF Token Mismatch (419)

```bash
php artisan cache:clear
php artisan config:clear
# Verify cookies are enabled in the browser
```

### Database Connection Failed

```bash
# Check PostgreSQL is running
sudo systemctl status postgresql

# Test from artisan
php artisan tinker
> DB::connection()->getPdo();
```

### Storage / Permission Denied

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Livewire Component Not Updating

```bash
php artisan livewire:discover
php artisan view:clear
php artisan config:clear
```

### Assets Not Loading in Production (404)

```bash
npm run build
# Verify public/build/ exists and vite manifest is present
```

### Getting More Help

- Check application logs: `storage/logs/laravel.log`
- Enable debug mode temporarily: `APP_DEBUG=true`
- [Laravel Documentation](https://laravel.com/docs)
- [Livewire Documentation](https://livewire.laravel.com/docs)
- [Laravel Reverb Documentation](https://reverb.laravel.com)

---

## License

Proprietary. All rights reserved.
