# 🚀 DevOps - Production Infrastructure

This directory contains Infrastructure as Code (IaC) configurations for deploying Conecta HN to Ubuntu 22.04/24.04 LTS servers.

## 📁 Files

| File | Purpose |
|------|---------|
| `deploy.sh` | Atomic deployment script with zero-downtime |
| `nginx_app.conf` | Nginx server block with security hardening |
| `conecta-worker.conf` | Supervisor configuration for background processes |

---

## 🛠️ Server Requirements

- **OS:** Ubuntu 22.04 LTS or 24.04 LTS
- **PHP:** 8.2+
- **Web Server:** Nginx
- **Database:** MySQL 8.0+ or MariaDB 10.6+
- **Cache:** Redis 6+
- **Process Manager:** Supervisor
- **Node.js:** 18+ LTS

---

## 📥 Initial Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y \
    nginx \
    supervisor \
    redis-server \
    nodejs npm \
    git \
    certbot python3-certbot-nginx

# Install PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-redis \
    php8.2-gd \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    php8.2-bcmath \
    php8.2-intl

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 🔧 Configuration Installation

### 1. Clone Repository

```bash
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/your-org/conecta-hn.git
sudo chown -R www-data:www-data conecta-hn
```

### 2. Application Setup

```bash
cd /var/www/conecta-hn
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
# Edit .env with production values
sudo -u www-data php artisan migrate --force
```

### 3. Install Nginx Config

```bash
sudo cp devops/nginx_app.conf /etc/nginx/sites-available/conecta-hn
# Edit server_name and SSL paths
sudo nano /etc/nginx/sites-available/conecta-hn
sudo ln -s /etc/nginx/sites-available/conecta-hn /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### 4. Install SSL Certificate

```bash
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

### 5. Install Supervisor Config

```bash
# Edit paths if needed
sudo cp devops/conecta-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start conecta:*
```

### 6. Setup Deploy Script

```bash
sudo chmod +x devops/deploy.sh
sudo mkdir -p /var/log
sudo touch /var/log/conecta-deploy.log
sudo chown www-data:www-data /var/log/conecta-deploy.log
```

---

## 🚀 Deploying Updates

```bash
cd /var/www/conecta-hn
sudo -u www-data ./devops/deploy.sh
```

### Deploy Options

```bash
# Skip asset compilation (faster deploys when no frontend changes)
./devops/deploy.sh --skip-assets

# Skip database backup
./devops/deploy.sh --no-backup

# View deployment log
tail -f /var/log/conecta-deploy.log
```

---

## 📊 Monitoring

### Check Worker Status

```bash
sudo supervisorctl status conecta:*
```

### View Worker Logs

```bash
# Real-time log
sudo supervisorctl tail -f conecta-worker:*

# Or from file
tail -f /var/www/conecta-hn/storage/logs/worker.log
```

### Check Nginx Status

```bash
sudo systemctl status nginx
sudo tail -f /var/log/nginx/conecta-hn.error.log
```

### Check Security Logs

```bash
tail -f /var/www/conecta-hn/storage/logs/security.log
```

---

## 🔒 Security Checklist

- [ ] `.env` has production values (APP_DEBUG=false, APP_ENV=production)
- [ ] Database credentials are secure
- [ ] Redis password is set (if exposed)
- [ ] SSL certificate is installed
- [ ] Firewall only allows 80, 443, 22
- [ ] SSH uses key-based auth (password disabled)
- [ ] Regular security updates enabled

---

## 🆘 Troubleshooting

### 502 Bad Gateway
```bash
# Check PHP-FPM is running
sudo systemctl status php8.2-fpm
sudo systemctl restart php8.2-fpm
```

### Workers Not Processing Jobs
```bash
# Restart workers
sudo supervisorctl restart conecta:*

# Check failed jobs
php artisan queue:failed
```

### Permission Errors
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```
