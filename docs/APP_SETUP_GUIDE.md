# Cameco Application Setup Guide

Full setup for the Laravel + Inertia/React application, queue workers, scheduler, and RFID gate PC.

---

## Stack Overview

| Layer | Technology |
|---|---|
| Backend | Laravel 11 (PHP) |
| Frontend | React + Inertia.js (Vite) |
| Database | PostgreSQL |
| Queue | Laravel Queue (database driver) |
| RFID Gate PC | Python (`rfid-server/`) |
| Reverse Proxy (prod) | Caddy |

---

## Part 1 — Development Setup

### 1.1 Prerequisites

| Requirement | Version | Check |
|---|---|---|
| PHP | 8.2+ | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 18+ | `node -v` |
| npm | 9+ | `npm -v` |
| PostgreSQL | 14+ | `psql --version` |
| Python | 3.11+ | `python --version` (RFID only) |

### 1.2 Install Dependencies

```bash
composer install
npm install
```

### 1.3 Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set at minimum:

```env
APP_NAME=Cameco
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cameco
DB_USERNAME=your_pg_user
DB_PASSWORD=your_pg_password

QUEUE_CONNECTION=database

MAIL_MAILER=log   # logs mail to storage/logs/laravel.log instead of sending
```

### 1.4 Create the Database

```bash
psql -U postgres -c "CREATE DATABASE cameco;"
```

### 1.5 Migrate and Seed

> ⚠️ `migrate:fresh` **drops all tables** and re-creates them. Only use on a dev database.

```bash
php artisan migrate:fresh --seed
```

This runs every seeder in `DatabaseSeeder.php` including:
- Departments, Positions, Employees
- Filipino profile names + mock photos (`EmployeeFilipinoProfileSeeder`)
- Real RFID card UIDs (`RfidCardMappingSeeder`)
- RFID devices, ledger history, attendance events
- Roles, permissions, user accounts
- Payroll data

### 1.6 Link Storage

```bash
php artisan storage:link
```

---

## Part 2 — Running the Dev Stack

You need **four terminal windows** running simultaneously.

### Terminal 1 — Laravel Dev Server

```bash
php artisan serve
```

Serves at `http://localhost:8000`.

### Terminal 2 — Vite (Frontend / HMR)

```bash
npm run dev
```

Vite serves assets at `http://localhost:5173` and handles Hot Module Replacement.
Inertia automatically connects — just keep this running alongside Laravel.

### Terminal 3 — Queue Worker

Processes queued jobs (RFID tap processing, email notifications, etc.):

```bash
php artisan queue:work --tries=3 --timeout=90
```

> Without this running, RFID taps will queue but never be processed.
> Failed jobs appear in `storage/logs/laravel.log` and the `failed_jobs` table.

### Terminal 4 — Scheduler

Runs scheduled jobs every minute (RFID ledger processing, device health checks):

```bash
php artisan schedule:work
```

> Without this, `ProcessRfidLedgerJob` never fires and attendance records won't generate.

---

### Quick Start Summary (Dev)

Open 4 terminals, run one command in each:

```
Terminal 1:  php artisan serve
Terminal 2:  npm run dev
Terminal 3:  php artisan queue:work --tries=3 --timeout=90
Terminal 4:  php artisan schedule:work
```

Then open `http://localhost:8000` in your browser.

**Default accounts after seeding:**
| Email | Password | Role |
|---|---|---|
| `superadmin@cameco.com` | `password` | Superadmin |
| `hrmanager@cameco.com` | `password` | HR Manager |

---

### Terminal 5 (optional) — RFID Gate PC Simulation

If you want to test RFID taps on your dev machine:

```bash
cd rfid-server
.venv\Scripts\Activate.ps1   # Windows
python main.py
```

See `docs/RFID_SETUP_GUIDE.md` for full RFID setup.

---

## Part 3 — Re-seeding During Development

To fully reset and reseed (wipes all data):

```bash
php artisan migrate:fresh --seed
```

To reseed only specific data without wiping:

```bash
# Re-assign Filipino profiles + photos
php artisan db:seed --class=EmployeeFilipinoProfileSeeder

# Re-assign physical RFID card UIDs
php artisan db:seed --class=RfidCardMappingSeeder

# Re-seed RFID ledger + attendance history
php artisan db:seed --class=RfidLedgerSeeder
```

---

## Part 4 — Production Setup (Linux Server)

### 4.1 Server Requirements

| Requirement | Notes |
|---|---|
| Ubuntu 22.04 / Debian 12 | Or any modern Linux |
| PHP 8.2+ with extensions | `php-pgsql php-mbstring php-xml php-curl php-zip php-bcmath` |
| Composer | System-wide |
| Node.js 18+ | For building frontend assets |
| PostgreSQL 14+ | Can be remote |
| Caddy | Web server / reverse proxy |
| Supervisor | Manages queue workers |

### 4.2 Deploy Application Files

```bash
git clone https://github.com/your-org/cameco.git /var/www/cameco
cd /var/www/cameco

composer install --no-dev --optimize-autoloader
npm ci
npm run build        # compiles assets to public/build/
```

### 4.3 Configure `.env` for Production

```env
APP_NAME=Cameco
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cameco.yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cameco_prod
DB_USERNAME=cameco_user
DB_PASSWORD=strong_secret_password

QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=mail_password
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Cameco HR"

SESSION_DRIVER=database
CACHE_STORE=database
```

```bash
php artisan key:generate
php artisan storage:link
```

### 4.4 Migrate and Seed (Production)

```bash
php artisan migrate --force
```

> **Do NOT run `migrate:fresh` in production** — it drops all data.
> 
> If you need to seed initial data (first deploy only):
> ```bash
> php artisan db:seed --class=RolesAndPermissionsSeeder
> php artisan db:seed --class=DepartmentSeeder
> php artisan db:seed --class=PositionSeeder
> # etc. — call specific seeders, never DatabaseSeeder wholesale
> ```

### 4.5 Set File Permissions

```bash
chown -R www-data:www-data /var/www/cameco
chmod -R 755 /var/www/cameco/storage
chmod -R 755 /var/www/cameco/bootstrap/cache
```

### 4.6 Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

### 4.7 Caddy Web Server

`Caddyfile` is already included in the repo root. Edit `/etc/caddy/Caddyfile` or point Caddy at the project's `Caddyfile`:

```
cameco.yourdomain.com {
    root * /var/www/cameco/public
    php_fastcgi unix//run/php/php8.2-fpm.sock
    file_server
    encode gzip
}
```

```bash
sudo systemctl enable caddy
sudo systemctl start caddy
```

---

### 4.8 Queue Worker via Supervisor

Create `/etc/supervisor/conf.d/cameco-worker.conf`:

```ini
[program:cameco-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cameco/artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-jobs=1000
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/cameco/storage/logs/worker.log
stopwaitsecs=120
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cameco-worker:*
sudo supervisorctl status
```

> `numprocs=2` runs two worker processes in parallel — increase if you have many queued jobs.

---

### 4.9 Scheduler via Cron

Add a single cron entry that fires every minute — Laravel handles the rest:

```bash
crontab -e -u www-data
```

Add:
```
* * * * * cd /var/www/cameco && php artisan schedule:run >> /dev/null 2>&1
```

Verify the scheduler sees all jobs:
```bash
php artisan schedule:list
```

Expected scheduled jobs:
| Job | Frequency |
|---|---|
| `ProcessRfidLedgerJob` | Every 1 minute |
| `timekeeping:cleanup-deduplication-cache` | Every 5 minutes |
| `timekeeping:check-device-health` | Every 2 minutes |
| `timekeeping:generate-daily-summaries` | Daily at 23:59 |

---

### 4.10 Production Services Summary

| Service | How it runs |
|---|---|
| PHP-FPM | Systemd service (`php8.2-fpm`) |
| Caddy | Systemd service (`caddy`) |
| Queue Workers | Supervisor (`cameco-worker`) |
| Scheduler | Cron (every minute, www-data) |
| RFID Gate PC | NSSM Windows service (`CamecoRfidServer`) — see RFID_SETUP_GUIDE.md |

---

## Part 5 — Deploying Updates (Production)

```bash
cd /var/www/cameco
git pull origin main

composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

sudo supervisorctl restart cameco-worker:*
```

---

## Part 6 — Monitoring & Logs

| What | Where |
|---|---|
| Laravel application log | `storage/logs/laravel.log` |
| Queue worker log | `storage/logs/worker.log` |
| Failed jobs | `failed_jobs` table / `php artisan queue:failed` |
| RFID gate PC log | `rfid-server/logs/service.log` |
| Caddy access log | `journalctl -u caddy` |

**Useful artisan commands:**
```bash
# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all

# Manually trigger RFID ledger processing
php artisan tinker
>>> dispatch(new \App\Jobs\Timekeeping\ProcessRfidLedgerJob());

# Check scheduled job list
php artisan schedule:list

# View supervisor status
sudo supervisorctl status
```

---

## Part 7 — RFID Gate PC (Production)

See `docs/RFID_SETUP_GUIDE.md` for full detail. Summary:

1. Copy `rfid-server/` to the gate PC (`C:\apps\rfid-server\`)
2. Create `.venv` and install requirements
3. Set `.env` with production `API_URL` + `API_KEY` + `DEVICE_ID`
4. Run `.\install-service.ps1 -ServiceUser ".\GateUser" -ServicePass "..."`
5. Device appears as **Online** in System → Device Management

The gate PC is completely independent — it only needs HTTPS access to the Laravel server. It buffers taps locally in SQLite if connectivity drops.
