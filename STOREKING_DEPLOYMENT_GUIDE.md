# StoreKing Template Deployment Guide

> Complete step-by-step guide for deploying StoreKing e-commerce template to DigitalOcean VPS.

---

## 📋 Prerequisites

| Item | Details |
|------|---------|
| Template | StoreKing (Laravel + Vue.js) |
| VPS | DigitalOcean Droplet (Ubuntu 22.04+) |
| Domain | Registered and ready |
| GitHub | Repository created |

---

## Phase 1: Local Preparation

### 1. Create `.env.example` for production

```env
APP_NAME="YourStoreName"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_DATABASE=yourdb_name
DB_USERNAME=yourdb_user
DB_PASSWORD=YOUR_SECURE_PASSWORD

TIMEZONE=Africa/Cairo
CURRENCY=EGP
DEMO=true
```

### 2. Push to GitHub

```bash
cd your-project-folder
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/username/repo.git
git push -u origin main
```

---

## Phase 2: VPS Server Setup

### Access VPS Console

1. Go to DigitalOcean → Droplets → Your Droplet
2. Click **Access** → **Launch Droplet Console**

### Install Dependencies (copy one by one)

```bash
# 1. Update system
apt update && apt upgrade -y

# 2. Add PHP repository
add-apt-repository ppa:ondrej/php -y && apt update

# 3. Install PHP 8.2 + extensions
apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath php8.2-http php8.2-imagick

# 4. Install MySQL & Nginx
apt install -y mysql-server nginx

# 5. Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && apt install -y nodejs

# 6. Install Composer
curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
```

---

## Phase 3: Clone & Configure Project

```bash
# Clone from GitHub
cd /var/www
git clone https://github.com/username/repo.git sitename
cd sitename

# Set ownership
chown -R www-data:www-data /var/www/sitename

# Install dependencies
composer install --optimize-autoloader --no-dev
npm install && npm run build

# Configure Laravel
cp .env.example .env
php artisan key:generate
php artisan storage:link
```

---

## Phase 4: Database Setup

```bash
# Enter MySQL
mysql
```

```sql
CREATE DATABASE yourdb_name;
CREATE USER 'yourdb_user'@'localhost' IDENTIFIED BY 'YourSecurePassword123!';
GRANT ALL PRIVILEGES ON yourdb_name.* TO 'yourdb_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Update .env with database credentials

```bash
nano .env
```

Update these lines:

```
DB_DATABASE=yourdb_name
DB_USERNAME=yourdb_user
DB_PASSWORD=YourSecurePassword123!
```

---

## Phase 5: Nginx Configuration

```bash
nano /etc/nginx/sites-available/sitename
```

Paste this config:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/sitename/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable site:

```bash
ln -s /etc/nginx/sites-available/sitename /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

---

## Phase 6: DNS Configuration

In DigitalOcean → Networking → Domains:

| Type | Hostname | Value |
|------|----------|-------|
| A | @ | Select your Droplet |
| A | www | Select your Droplet |

---

## Phase 7: SSL Certificate

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

Follow prompts:

- Enter email → `Y` → `N` → Let it configure

---

## Phase 8: Final Laravel Setup

```bash
cd /var/www/sitename

# Permissions
chmod -R 777 storage
chown -R www-data:www-data storage bootstrap/cache

# Clear caches
php artisan config:clear
php artisan cache:clear

# Run migrations & seeders
php artisan migrate:fresh --seed --force
```

---

## Phase 9: Fix API Key Middleware (Important!)

This template has API key verification that blocks the frontend.

```bash
nano app/Http/Middleware/ApiKeyMiddleware.php
```

Replace ALL content with:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
```

Then:

```bash
php artisan config:clear && php artisan cache:clear
```

---

## Phase 10: Access Your Site

- **Frontend**: <https://yourdomain.com>
- **Admin Panel**: <https://yourdomain.com/admin>

### Default Admin Login

| Field | Value |
|-------|-------|
| Email | <admin@example.com> |
| Password | 123456 |

---

## 🔄 Future Updates Workflow

### On your local machine

```bash
# Make changes
git add .
git commit -m "Description of changes"
git push
```

### On the VPS

```bash
cd /var/www/sitename
git pull
npm run build  # if frontend changes
php artisan config:clear
php artisan cache:clear
```

---

## 📋 Credentials Template

Save this for each deployment:

```
Domain: 
VPS IP: 
GitHub Repo: 

Database:
- Host: localhost
- Name: 
- User: 
- Password: 

Admin:
- Email: admin@example.com
- Password: 123456

SSL Expiry: (90 days from install)
```

---

## ⚠️ Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| 500 Error | `php artisan key:generate && php artisan config:cache` |
| Session not saving | `chmod -R 777 storage` |
| Invalid Api Key | Modify `ApiKeyMiddleware.php` as shown above |
| Products not showing | Check `DEMO=true` in `.env` and run seeders |
| SSL fails | Wait 5 mins for DNS propagation |

---

## ✅ Deployment Checklist

- [ ] GitHub repo created & code pushed
- [ ] VPS created with Ubuntu 22.04
- [ ] PHP 8.2, MySQL, Nginx, Node.js installed
- [ ] Project cloned to `/var/www/sitename`
- [ ] Composer & npm dependencies installed
- [ ] Database created and .env configured
- [ ] Nginx configured and enabled
- [ ] DNS A records pointing to VPS IP
- [ ] SSL certificate installed
- [ ] ApiKeyMiddleware bypassed
- [ ] Migrations & seeders run
- [ ] Site accessible via HTTPS
