---
description: Deploy Laravel + Vue site to Ubuntu server after backup restoration
---

# Laravel Site Deployment Workflow

## Prerequisites

- Ubuntu server with SSH access
- PHP 8.x, MySQL, Nginx, Node.js, Composer installed
- SQL backup file
- Project files uploaded to `/var/www/[project_name]`

---

## Step 0: Restore Site from Backup

### 0.1: Remove Wrongly Installed Files

```bash
# Check what's currently in /var/www
ls -la /var/www/

# Remove the wrongly installed project
# For valdor project:
rm -rf /var/www/valdor

# OR for any other project (replace [project_name] with actual name):
# rm -rf /var/www/[project_name]

# Verify removal
ls -la /var/www/
```

### 0.2: Locate Backup Files

```bash
# List backup files in root
ls -lah /root/ | grep -E "\.tar\.gz|\.zip|\.sql|backup"

# Check for common backup names
ls -lah /root/ | grep -E "[project_name]|backup|site"

# List all files in root to identify backup
ls -lah /root/
```

### 0.3: Extract and Restore Project Files

**If backup is a .tar.gz file:**

```bash
# Extract to /var/www
cd /var/www/
tar -xzf /root/[backup_name].tar.gz

# OR extract with specific output name
tar -xzf /root/[backup_name].tar.gz
mv [extracted_folder_name] [project_name]
```

**If backup is a .zip file:**

```bash
cd /var/www/
unzip /root/[backup_name].zip

# Rename if needed
mv [extracted_folder_name] [project_name]
```

**If files are in a directory:**

```bash
# Copy entire directory from root to /var/www
cp -r /root/[backup_folder] /var/www/[project_name]
```

### 0.4: Verify Project Structure

```bash
cd /var/www/[project_name]

# Check Laravel structure
ls -la

# Should see: app, config, database, public, resources, routes, storage, vendor, etc.
# If vendor is missing, you'll need to run: composer install

# Check if .env exists
ls -la .env

# If missing, copy from example
cp .env.example .env
```

### 0.5: Locate Database Backup

```bash
# Find SQL backup in root
ls -lah /root/*.sql

# Common backup names
ls -lah /root/ | grep -E "\.sql|database|dump"

# If SQL is inside the tar/zip backup
find /var/www/[project_name] -name "*.sql"
```

**Take note of:**

- Project backup location: `/root/[backup_name]`
- Database backup file: `/root/[database_backup].sql`
- Target directory: `/var/www/[project_name]`

---

## Step 1: Fix MySQL (if needed)

```bash
# Check MySQL status
systemctl status mysql

# If MySQL has issues, reset it:
systemctl stop mysql
rm -rf /var/lib/mysql/*
mysqld --initialize-insecure --user=mysql
systemctl start mysql

# Set root password
mysql -u root
# Inside MySQL:
ALTER USER 'root'@'localhost' IDENTIFIED BY 'SecurePass2026';
FLUSH PRIVILEGES;
EXIT;
```

---

## Step 2: Create Database

```bash
mysql -u root -pSecurePass2026
```

```sql
CREATE DATABASE [db_name] CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES;
EXIT;
```

---

## Step 3: Import Backup

```bash
# Find databases in backup
grep -n "CREATE DATABASE" /root/all_databases.sql

# Extract specific database (adjust line numbers)
sed -n '[start_line],[end_line]p' /root/all_databases.sql > /root/[db_name]_only.sql

# Import with foreign key checks disabled
mysql -u root -pSecurePass2026 [db_name] -e "SET FOREIGN_KEY_CHECKS=0; SOURCE /root/[db_name]_only.sql; SET FOREIGN_KEY_CHECKS=1;"

# Verify
mysql -u root -pSecurePass2026 -e "USE [db_name]; SHOW TABLES;"
```

---

## Step 4: Configure .env

```bash
cd /var/www/[project_name]
nano .env
```

**Update these values:**

```env
APP_URL=https://[domain.com]
DB_DATABASE=[db_name]
DB_USERNAME=root
DB_PASSWORD=SecurePass2026
```

---

## Step 5: Laravel Setup

```bash
# Clear and cache
php artisan config:clear
php artisan cache:clear
php artisan config:cache

# Storage link (if not exists)
php artisan storage:link
```

---

## Step 6: Build Frontend

```bash
npm run build
ls -la public/build/
```

---

## Step 7: Set Permissions

```bash
chown -R www-data:www-data /var/www/[project_name]
chmod -R 775 storage bootstrap/cache
```

---

## Step 8: Configure Nginx

```bash
nano /etc/nginx/sites-available/[project_name]
```

**Nginx Config:**

```nginx
server {
    listen 80;
    server_name [domain.com] www.[domain.com];
    root /var/www/[project_name]/public;
    index index.php;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

```bash
ln -sf /etc/nginx/sites-available/[project_name] /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

---

## Step 9: SSL Certificate

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d [domain.com] -d www.[domain.com]
```

---

## Step 10: Final Steps

```bash
# Update APP_URL to https
nano .env
# Change: APP_URL=https://[domain.com]

php artisan config:cache
npm run build

# Test
curl -I https://[domain.com]
```

---

## Troubleshooting

### Database Connection Error

```bash
cat .env | grep DB_
php artisan config:clear
php artisan config:cache
```

### 500 Error

```bash
tail -50 storage/logs/laravel.log
tail -50 /var/log/nginx/[project_name]-error.log
```

### Assets Not Loading

```bash
cat .env | grep APP_URL
npm run build
# Clear browser cache (Ctrl+Shift+R)
```

---

## Complete Workflow: Restore Valdor Site from Backup

### Quick Checklist

- [ ] Step 1: Remove old `valdor` folder from `/var/www/`
- [ ] Step 2: Find backup files in `/root/`
- [ ] Step 3: Extract backup to `/var/www/valdor`
- [ ] Step 4: Verify project structure
- [ ] Step 5: Find and prepare database backup
- [ ] Step 6: Create MySQL database
- [ ] Step 7: Import database backup
- [ ] Step 8: Configure `.env` file
- [ ] Step 9: Run Laravel setup commands
- [ ] Step 10: Install dependencies (if needed)
- [ ] Step 11: Build frontend
- [ ] Step 12: Set permissions
- [ ] Step 13: Configure Nginx
- [ ] Step 14: Setup SSL certificate
- [ ] Step 15: Test the site

---

### Detailed Steps

#### 1. Remove Old Installation

```bash
# Backup check (optional - in case you need to revert)
ls -la /var/www/valdor

# Remove the wrongly installed valdor
rm -rf /var/www/valdor

# Confirm removal
ls -la /var/www/
```

#### 2. Find Backup in Root

```bash
# List all files in root directory
ls -lah /root/

# Look for valdor backup files
ls -lah /root/ | grep -i valdor

# Look for any compressed files
ls -lah /root/*.tar.gz
ls -lah /root/*.zip

# Look for SQL files
ls -lah /root/*.sql
```

#### 3. Extract Backup to /var/www/

**Choose based on your backup format:**

**Option A: tar.gz file**

```bash
cd /var/www/
tar -xzf /root/valdor_backup.tar.gz
# If extracted folder has different name, rename it:
mv [extracted_folder_name] valdor
```

**Option B: zip file**

```bash
cd /var/www/
unzip /root/valdor_backup.zip
mv [extracted_folder_name] valdor
```

**Option C: Direct folder copy**

```bash
cp -r /root/valdor /var/www/
# OR
cp -r /root/valdor_backup /var/www/valdor
```

#### 4. Verify Project Structure

```bash
cd /var/www/valdor
ls -la

# You should see Laravel structure:
# app, bootstrap, config, database, public, resources, routes, storage, vendor, .env, etc.
```

#### 5. Locate Database Backup

```bash
# Check root directory for SQL files
ls -lah /root/*.sql

# Or check if SQL is inside the project backup
find /var/www/valdor -name "*.sql"

# Note the SQL file path for next steps
```

#### 6. Create MySQL Database

```bash
# Login to MySQL
mysql -u root -p

# Inside MySQL console:
CREATE DATABASE valdor_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES;
EXIT;
```

#### 7. Import Database Backup

```bash
# If you have complete SQL backup in root:
mysql -u root -p valdor_db < /root/valdor_backup.sql

# OR if backup has all databases, extract specific one first:
# Find the database in the backup
grep -n "CREATE DATABASE" /root/all_databases.sql

# Extract specific database (adjust line numbers based on grep output)
sed -n '100,5000p' /root/all_databases.sql > /root/valdor_only.sql

# Import with foreign key checks disabled
mysql -u root -p valdor_db -e "SET FOREIGN_KEY_CHECKS=0; SOURCE /root/valdor_only.sql; SET FOREIGN_KEY_CHECKS=1;"

# Verify import
mysql -u root -p -e "USE valdor_db; SHOW TABLES;"
```

#### 8. Configure .env File

```bash
cd /var/www/valdor

# Check if .env exists
ls -la .env

# If missing, create from example
cp .env.example .env

# Edit .env
nano .env
```

**Update these critical values:**

```env
APP_NAME=Valdor
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=valdor_db
DB_USERNAME=root
DB_PASSWORD=YourMySQLPassword
```

Save and exit (Ctrl+X, then Y, then Enter)

#### 9. Run Laravel Setup

```bash
cd /var/www/valdor

# Generate application key if needed
php artisan key:generate

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache config
php artisan config:cache

# Create storage link
php artisan storage:link
```

#### 10. Install Dependencies (if needed)

```bash
# Check if vendor directory exists
ls -la vendor/

# If vendor is missing or incomplete, install:
composer install --no-dev --optimize-autoloader

# Check if node_modules exists
ls -la node_modules/

# If missing, install:
npm install
```

#### 11. Build Frontend

```bash
cd /var/www/valdor

# Build production assets
npm run build

# Verify build output
ls -la public/build/
```

#### 12. Set Permissions

```bash
# Set ownership to web server user
chown -R www-data:www-data /var/www/valdor

# Set correct permissions
chmod -R 775 /var/www/valdor/storage
chmod -R 775 /var/www/valdor/bootstrap/cache
```

#### 13. Configure Nginx

```bash
# Create or edit Nginx config
nano /etc/nginx/sites-available/valdor
```

**Paste this config (adjust domain name):**

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/valdor/public;
    index index.php index.html;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Enable the site
ln -sf /etc/nginx/sites-available/valdor /etc/nginx/sites-enabled/

# Test Nginx config
nginx -t

# If test passes, restart Nginx
systemctl restart nginx
```

#### 14. Setup SSL Certificate

```bash
# Install certbot if not already installed
apt install -y certbot python3-certbot-nginx

# Get SSL certificate (replace with your actual domain)
certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Update APP_URL in .env to use https
cd /var/www/valdor
nano .env
# Change: APP_URL=https://yourdomain.com

# Recache config
php artisan config:cache
```

#### 15. Test the Site

```bash
# Test HTTP response
curl -I http://yourdomain.com

# Test HTTPS response
curl -I https://yourdomain.com

# Check Laravel logs for any errors
tail -50 /var/www/valdor/storage/logs/laravel.log

# Check Nginx error logs
tail -50 /var/log/nginx/error.log
```

**Open in browser:** `https://yourdomain.com`

---

### Common Issues & Solutions

#### Issue: Can't find backup file

```bash
# Search entire root directory
find /root -name "*valdor*" -o -name "*.tar.gz" -o -name "*.zip"
```

#### Issue: Database import fails

```bash
# Check MySQL is running
systemctl status mysql

# Check database exists
mysql -u root -p -e "SHOW DATABASES;"

# Try import with verbose output
mysql -u root -p valdor_db < /root/valdor_backup.sql --verbose
```

#### Issue: 500 Error on website

```bash
# Check Laravel logs
tail -100 /var/www/valdor/storage/logs/laravel.log

# Check permissions
ls -la /var/www/valdor/storage
ls -la /var/www/valdor/bootstrap/cache

# Fix permissions if needed
chmod -R 775 /var/www/valdor/storage
chmod -R 775 /var/www/valdor/bootstrap/cache
chown -R www-data:www-data /var/www/valdor
```

#### Issue: Assets not loading (CSS/JS)

```bash
# Check APP_URL in .env
cat /var/www/valdor/.env | grep APP_URL

# Rebuild assets
cd /var/www/valdor
npm run build

# Clear config cache
php artisan config:cache

# Check build folder
ls -la /var/www/valdor/public/build/
```
