# 🚀 Production Deployment Guide - Pushify

Bu kılavuz Pushify platformunu production'a almak için gereken tüm adımları içerir.

---

## 📋 İçindekiler
1. [Gereksinimler](#gereksinimler)
2. [Server Seçimi](#server-seçimi)
3. [Server Kurulumu](#server-kurulumu)
4. [Database Kurulumu](#database-kurulumu)
5. [Domain ve SSL](#domain-ve-ssl)
6. [Uygulama Kurulumu](#uygulama-kurulumu)
7. [Environment Variables](#environment-variables)
8. [RabbitMQ Setup](#rabbitmq-setup)
9. [GitHub OAuth](#github-oauth)
10. [Nginx Yapılandırması](#nginx-yapılandırması)
11. [SSL Sertifikaları](#ssl-sertifikaları)
12. [Worker Process](#worker-process)
13. [Backup Stratejisi](#backup-stratejisi)
14. [Monitoring](#monitoring)
15. [Security Hardening](#security-hardening)
16. [Performance Optimization](#performance-optimization)
17. [Deployment](#deployment)
18. [Post-Deployment](#post-deployment)

---

## 1. Gereksinimler

### Minimum Server Gereksinimleri:
- **CPU:** 2 core (4 core önerilir)
- **RAM:** 4 GB (8 GB önerilir)
- **Disk:** 50 GB SSD (100 GB önerilir)
- **OS:** Ubuntu 22.04 LTS

### Yazılım Gereksinimleri:
- PHP 8.2+
- Composer 2.x
- Node.js 20.x
- NPM/Yarn
- PostgreSQL 15+ veya MySQL 8.0+
- Redis 7.x
- Docker & Docker Compose
- Nginx
- RabbitMQ 3.x
- Git
- Supervisor
- Certbot (Let's Encrypt SSL için)

---

## 2. Server Seçimi

### Önerilen Cloud Providers:

#### Option 1: Hetzner Cloud (💰 En Uygun)
```
CX21: 2 vCPU, 4GB RAM, 40GB SSD = €5.83/ay
CX31: 2 vCPU, 8GB RAM, 80GB SSD = €11.66/ay (Önerilen)
CX41: 4 vCPU, 16GB RAM, 160GB SSD = €23.32/ay
```

#### Option 2: DigitalOcean
```
Basic Droplet: 2 vCPU, 4GB RAM, 80GB SSD = $24/ay
Premium Droplet: 2 vCPU, 8GB RAM, 160GB SSD = $48/ay
```

#### Option 3: AWS Lightsail
```
4GB Instance: 2 vCPU, 4GB RAM, 80GB SSD = $24/ay
8GB Instance: 2 vCPU, 8GB RAM, 160GB SSD = $48/ay
```

#### Option 4: Linode/Akamai
```
Linode 4GB: 2 vCPU, 4GB RAM, 80GB SSD = $24/ay
Linode 8GB: 4 vCPU, 8GB RAM, 160GB SSD = $48/ay
```

**Tavsiye:** Başlangıç için Hetzner CX31 (€11.66/ay) mükemmel. Büyüyünce scale edebilirsin.

---

## 3. Server Kurulumu

### 3.1 İlk Bağlantı
```bash
ssh root@your-server-ip
```

### 3.2 Update System
```bash
apt update && apt upgrade -y
apt install -y software-properties-common apt-transport-https ca-certificates curl gnupg lsb-release
```

### 3.3 Create Non-Root User
```bash
adduser pushify
usermod -aG sudo pushify
su - pushify
```

### 3.4 SSH Key Setup (Local makinenden)
```bash
# Local makinende çalıştır
ssh-keygen -t ed25519 -C "pushify-production"
ssh-copy-id pushify@your-server-ip

# Test et
ssh pushify@your-server-ip
```

### 3.5 Disable Root SSH Login
```bash
sudo nano /etc/ssh/sshd_config
```

Değiştir:
```
PermitRootLogin no
PasswordAuthentication no
```

Restart SSH:
```bash
sudo systemctl restart sshd
```

### 3.6 Setup Firewall
```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

---

## 4. Database Kurulumu

### Option 1: PostgreSQL (Önerilen)

```bash
# PostgreSQL 15 Install
sudo apt install -y postgresql postgresql-contrib

# Start PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Create Database and User
sudo -u postgres psql

# PostgreSQL console'da:
CREATE DATABASE pushify_production;
CREATE USER pushify WITH ENCRYPTED PASSWORD 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON DATABASE pushify_production TO pushify;
ALTER DATABASE pushify_production OWNER TO pushify;
\q

# Remote access için (opsiyonel)
sudo nano /etc/postgresql/15/main/postgresql.conf
# listen_addresses = '*' yap

sudo nano /etc/postgresql/15/main/pg_hba.conf
# Ekle: host all all 0.0.0.0/0 md5

sudo systemctl restart postgresql
```

### Option 2: MySQL 8.0

```bash
# MySQL Install
sudo apt install -y mysql-server

# Secure Installation
sudo mysql_secure_installation

# Create Database
sudo mysql

# MySQL console'da:
CREATE DATABASE pushify_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pushify'@'localhost' IDENTIFIED WITH mysql_native_password BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON pushify_production.* TO 'pushify'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Domain ve SSL

### 5.1 Domain Setup

1. **Domain Satın Al** (Namecheap, GoDaddy, Cloudflare, etc.)

2. **DNS Kayıtları Oluştur:**
```
A Record:
@ -> your-server-ip
www -> your-server-ip
api -> your-server-ip

CNAME Records (wildcard for projects):
* -> pushify.dev
*.pushify.dev -> pushify.dev
```

3. **DNS Propagation Test:**
```bash
dig pushify.dev +short
dig www.pushify.dev +short
```

### 5.2 Cloudflare Setup (Önerilen)

Cloudflare kullanmanı öneririm çünkü:
- ✅ DDoS protection
- ✅ CDN
- ✅ Free SSL
- ✅ Analytics
- ✅ Fast DNS

**Cloudflare Settings:**
```
1. Site ekle: pushify.dev
2. Nameserver'ları domain registrar'da güncelle
3. SSL/TLS -> Full (strict)
4. Always Use HTTPS -> On
5. Automatic HTTPS Rewrites -> On
6. Minimum TLS Version -> 1.2
```

---

## 6. Uygulama Kurulumu

### 6.1 Install PHP 8.2
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-common \
    php8.2-mysql php8.2-pgsql php8.2-zip php8.2-gd php8.2-mbstring \
    php8.2-curl php8.2-xml php8.2-bcmath php8.2-intl php8.2-redis \
    php8.2-opcache
```

### 6.2 Install Composer
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 6.3 Install Node.js 20
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node --version
npm --version
```

### 6.4 Install Docker
```bash
# Docker install
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Add user to docker group
sudo usermod -aG docker $USER
newgrp docker

# Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Test
docker --version
docker-compose --version
```

### 6.5 Install Redis
```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Test
redis-cli ping
# Çıktı: PONG
```

### 6.6 Clone Application
```bash
cd /var/www
sudo mkdir -p pushify
sudo chown -R $USER:$USER pushify
cd pushify

# Clone repository
git clone https://github.com/pushifydev/pushify.git .
# veya
git clone git@github.com:your-username/pushify.git .
```

### 6.7 Install Dependencies
```bash
# PHP dependencies
composer install --no-dev --optimize-autoloader

# JavaScript dependencies
npm install
npm run build
```

### 6.8 Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/pushify
sudo chmod -R 755 /var/www/pushify
sudo chmod -R 775 /var/www/pushify/var
sudo chmod -R 775 /var/www/pushify/var/cache
sudo chmod -R 775 /var/www/pushify/var/log
```

---

## 7. Environment Variables

### 7.1 Create .env.local
```bash
cd /var/www/pushify
cp .env .env.local
nano .env.local
```

### 7.2 Production Environment Variables

```bash
###> symfony/framework-bundle ###
APP_ENV=prod
APP_SECRET=GENERATE_32_CHAR_RANDOM_STRING
APP_DEBUG=0
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
# PostgreSQL
DATABASE_URL="postgresql://pushify:PASSWORD@127.0.0.1:5432/pushify_production?serverVersion=15&charset=utf8"

# MySQL (alternatif)
# DATABASE_URL="mysql://pushify:PASSWORD@127.0.0.1:3306/pushify_production?serverVersion=8.0"
###< doctrine/doctrine-bundle ###

###> symfony/messenger ###
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
###< symfony/messenger ###

###> RabbitMQ ###
RABBITMQ_HOST=127.0.0.1
RABBITMQ_PORT=5672
RABBITMQ_USER=pushify
RABBITMQ_PASSWORD=STRONG_RABBITMQ_PASSWORD
RABBITMQ_VHOST=/
###< RabbitMQ ###

###> Redis ###
REDIS_URL=redis://127.0.0.1:6379
###< Redis ###

###> Application ###
DEFAULT_URI=https://pushify.dev
DOCKER_REGISTRY_URL=registry.pushify.dev:5000
###< Application ###

###> GitHub OAuth ###
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
###< GitHub OAuth ###

###> Hetzner Cloud (optional) ###
HETZNER_API_TOKEN=your_hetzner_token_if_using
###< Hetzner Cloud ###

###> Email (Gmail via RabbitMQ) ###
MAILER_DSN=gmail://username:app-password@default
###< Email ###
```

### 7.3 Generate APP_SECRET
```bash
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
# Çıktıyı APP_SECRET'a kopyala
```

---

## 8. RabbitMQ Setup

### 8.1 Install RabbitMQ
```bash
# Import signing key
curl -fsSL https://github.com/rabbitmq/signing-keys/releases/download/2.0/rabbitmq-release-signing-key.asc | sudo apt-key add -

# Add repository
sudo apt install -y apt-transport-https
echo "deb https://packagecloud.io/rabbitmq/rabbitmq-server/ubuntu/ $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/rabbitmq.list

# Install
sudo apt update
sudo apt install -y rabbitmq-server

# Enable and start
sudo systemctl enable rabbitmq-server
sudo systemctl start rabbitmq-server
```

### 8.2 Configure RabbitMQ
```bash
# Enable management plugin
sudo rabbitmq-plugins enable rabbitmq_management

# Create user
sudo rabbitmqctl add_user pushify STRONG_RABBITMQ_PASSWORD
sudo rabbitmqctl set_user_tags pushify administrator
sudo rabbitmqctl set_permissions -p / pushify ".*" ".*" ".*"

# Delete guest user (security)
sudo rabbitmqctl delete_user guest

# Management UI: http://your-ip:15672
```

---

## 9. GitHub OAuth

### 9.1 Create GitHub OAuth App

1. Git to: https://github.com/settings/developers
2. Click "New OAuth App"
3. Fill in:
   ```
   Application name: Pushify Production
   Homepage URL: https://pushify.dev
   Authorization callback URL: https://pushify.dev/auth/github/callback
   ```
4. Click "Register application"
5. Copy **Client ID** and generate **Client Secret**
6. Add to `.env.local`:
   ```
   GITHUB_CLIENT_ID=your_client_id
   GITHUB_CLIENT_SECRET=your_client_secret
   ```

---

## 10. Nginx Yapılandırması

### 10.1 Install Nginx
```bash
sudo apt install -y nginx
```

### 10.2 Create Nginx Config
```bash
sudo nano /etc/nginx/sites-available/pushify
```

Paste:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name pushify.dev www.pushify.dev *.pushify.dev;

    # Redirect to HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name pushify.dev www.pushify.dev;

    root /var/www/pushify/public;
    index index.php;

    # SSL Configuration (will be added by Certbot)
    # ssl_certificate /etc/letsencrypt/live/pushify.dev/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/pushify.dev/privkey.pem;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Logs
    access_log /var/log/nginx/pushify_access.log;
    error_log /var/log/nginx/pushify_error.log;

    # Max upload size
    client_max_body_size 100M;

    # PHP-FPM
    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Static assets caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}

# Wildcard subdomain for user projects
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name *.pushify.dev;

    # SSL Configuration (wildcard certificate)
    # ssl_certificate /etc/letsencrypt/live/pushify.dev/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/pushify.dev/privkey.pem;

    # Proxy to Docker containers
    location / {
        proxy_pass http://127.0.0.1:PORT;  # Dynamic port based on project
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 10.3 Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/pushify /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## 11. SSL Sertifikaları

### 11.1 Install Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx
```

### 11.2 Get SSL Certificate
```bash
# For main domain + wildcard
sudo certbot certonly --nginx -d pushify.dev -d www.pushify.dev -d *.pushify.dev

# Follow prompts
# Email: your-email@example.com
# Agree to Terms: Yes
# Share email: No (optional)
```

### 11.3 Auto-renewal Test
```bash
sudo certbot renew --dry-run

# Setup auto-renewal (already setup, but verify)
sudo systemctl status certbot.timer
```

### 11.4 Update Nginx for SSL
```bash
sudo nano /etc/nginx/sites-available/pushify
# Uncomment SSL lines
sudo nginx -t
sudo systemctl reload nginx
```

---

## 12. Worker Process

### 12.1 Create Supervisor Config
```bash
sudo nano /etc/supervisor/conf.d/pushify-messenger.conf
```

Paste:
```ini
[program:pushify-messenger-worker]
command=php /var/www/pushify/bin/console messenger:consume async email_queue backup_queue database_queue --time-limit=3600 --memory-limit=256M
user=www-data
numprocs=2
startsecs=0
autostart=true
autorestart=true
startretries=10
process_name=%(program_name)s_%(process_num)02d
redirect_stderr=true
stdout_logfile=/var/www/pushify/var/log/messenger_worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10
```

### 12.2 Start Workers
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pushify-messenger-worker:*
sudo supervisorctl status
```

---

## 13. Database Migration

```bash
cd /var/www/pushify

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Verify
php bin/console doctrine:migrations:status
```

---

## 14. Cache Warmup

```bash
# Clear cache
php bin/console cache:clear --env=prod

# Warmup cache
php bin/console cache:warmup --env=prod
```

---

## 15. Backup Stratejisi

### 15.1 Database Backup Script
```bash
sudo nano /usr/local/bin/backup-pushify-db.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/pushify"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="pushify_production"
DB_USER="pushify"

mkdir -p $BACKUP_DIR

# PostgreSQL backup
PGPASSWORD="YOUR_PASSWORD" pg_dump -U $DB_USER -h localhost $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete

echo "Backup completed: db_backup_$DATE.sql.gz"
```

Make executable:
```bash
sudo chmod +x /usr/local/bin/backup-pushify-db.sh
```

### 15.2 Setup Cron Job
```bash
sudo crontab -e
```

Add:
```cron
# Backup database every day at 2 AM
0 2 * * * /usr/local/bin/backup-pushify-db.sh

# Backup uploaded files every day at 3 AM
0 3 * * * tar -czf /var/backups/pushify/files_$(date +\%Y\%m\%d).tar.gz /var/www/pushify/var/deployments

# Clean old logs weekly
0 0 * * 0 find /var/www/pushify/var/log -name "*.log" -mtime +30 -delete
```

---

## 16. Monitoring

### 16.1 Setup Log Rotation
```bash
sudo nano /etc/logrotate.d/pushify
```

```
/var/www/pushify/var/log/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### 16.2 Install Monitoring Tools

```bash
# Install htop
sudo apt install -y htop

# Install Netdata (optional, powerful monitoring)
bash <(curl -Ss https://my-netdata.io/kickstart.sh)
# Access: http://your-ip:19999
```

---

## 17. Security Hardening

### 17.1 Install Fail2ban
```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 17.2 Configure Fail2ban for Nginx
```bash
sudo nano /etc/fail2ban/jail.local
```

```ini
[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/pushify_error.log

[nginx-noscript]
enabled = true
port = http,https
logpath = /var/log/nginx/pushify_access.log

[nginx-badbots]
enabled = true
port = http,https
logpath = /var/log/nginx/pushify_access.log
```

Restart:
```bash
sudo systemctl restart fail2ban
```

### 17.3 Disable Unused Services
```bash
sudo systemctl disable apache2 2>/dev/null || true
sudo systemctl stop apache2 2>/dev/null || true
```

---

## 18. Performance Optimization

### 18.1 PHP-FPM Optimization
```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Optimize:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

# Memory limits
php_admin_value[memory_limit] = 256M
```

Restart:
```bash
sudo systemctl restart php8.2-fpm
```

### 18.2 OPcache Configuration
```bash
sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

### 18.3 Enable Redis Session Storage (Optional)
```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

Find and modify:
```ini
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379"
```

---

## 19. Final Deployment Steps

### 19.1 Test Application
```bash
# Test Symfony console
php bin/console about

# Test database connection
php bin/console doctrine:schema:validate

# Check routes
php bin/console debug:router
```

### 19.2 Open Browser
```
https://pushify.dev
```

### 19.3 First User Registration
```
1. Open https://pushify.dev/register
2. Create admin account
3. Connect GitHub
4. Test deployment
```

---

## 20. Post-Deployment Checklist

### ✅ Final Checklist

- [ ] Server güvenlik güncellemeleri yapıldı
- [ ] Non-root user oluşturuldu
- [ ] SSH key authentication aktif
- [ ] Root SSH login devre dışı
- [ ] Firewall (UFW) aktif
- [ ] Database kuruldu ve çalışıyor
- [ ] Domain DNS kayıtları doğru
- [ ] SSL sertifikası aktif
- [ ] Nginx yapılandırması doğru
- [ ] PHP-FPM çalışıyor
- [ ] Redis çalışıyor
- [ ] RabbitMQ çalışıyor
- [ ] Docker kuruldu ve çalışıyor
- [ ] Worker process'ler çalışıyor (Supervisor)
- [ ] Cron jobs kuruldu
- [ ] Backup stratejisi aktif
- [ ] Log rotation aktif
- [ ] Fail2ban aktif
- [ ] GitHub OAuth yapılandırıldı
- [ ] Environment variables doğru
- [ ] Database migration çalıştırıldı
- [ ] Cache warmup yapıldı
- [ ] Test deployment başarılı
- [ ] Monitoring aktif

---

## 21. Maintenance Commands

### Günlük Kontroller:
```bash
# Check disk space
df -h

# Check memory
free -h

# Check processes
htop

# Check logs
tail -f /var/www/pushify/var/log/prod.log
tail -f /var/log/nginx/pushify_error.log

# Check workers
sudo supervisorctl status
```

### Uygulama Güncelleme:
```bash
cd /var/www/pushify

# Maintenance mode ON
php bin/console lexik:maintenance:lock

# Pull latest code
git pull origin master

# Update dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Clear cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
sudo supervisorctl restart pushify-messenger-worker:*

# Maintenance mode OFF
php bin/console lexik:maintenance:unlock
```

---

## 22. Troubleshooting

### Problem: 500 Internal Server Error

```bash
# Check logs
tail -100 /var/www/pushify/var/log/prod.log
tail -100 /var/log/nginx/pushify_error.log

# Check permissions
ls -la /var/www/pushify/var
sudo chown -R www-data:www-data /var/www/pushify/var

# Clear cache
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
```

### Problem: Database Connection Failed

```bash
# Test connection
php bin/console dbal:run-sql "SELECT 1"

# Check PostgreSQL
sudo systemctl status postgresql
sudo -u postgres psql -l

# Check credentials in .env.local
```

### Problem: Worker Not Processing Jobs

```bash
# Check supervisor
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart pushify-messenger-worker:*

# Check worker logs
tail -f /var/www/pushify/var/log/messenger_worker.log

# Manually consume (for testing)
php bin/console messenger:consume async -vv
```

### Problem: High Memory Usage

```bash
# Check processes
ps aux --sort=-%mem | head

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Adjust PHP-FPM config
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

---

## 23. Scaling Strategy

### Horizontal Scaling (Multiple Servers):

1. **Database Server:** Separate PostgreSQL server
2. **Redis Server:** Dedicated Redis instance
3. **RabbitMQ Server:** Separate message queue
4. **Application Servers:** Multiple Nginx + PHP-FPM
5. **Load Balancer:** Nginx or HAProxy

### Vertical Scaling (Same Server):

1. Upgrade server resources (CPU, RAM)
2. Optimize PHP-FPM worker count
3. Increase OPcache memory
4. Add more Supervisor workers

---

## 24. Cost Estimation (Monthly)

### Minimal Setup:
```
Server (Hetzner CX31): €11.66
Domain (.com): $1-2
Total: ~€14/mo ($15/mo)
```

### Recommended Setup:
```
Server (Hetzner CX41): €23.32
Domain (.com): $1-2
Cloudflare Pro (optional): $20
Backup Storage (optional): $5
Total: ~€30-50/mo ($32-54/mo)
```

### Production Scale:
```
App Server (CX51): €46.65
Database Server (CX41): €23.32
Redis/Queue Server (CX21): €5.83
Domain: $1-2
Cloudflare Pro: $20
Backups: $10
Total: ~€100-110/mo ($108-118/mo)
```

---

## 25. Support & Resources

### Documentation:
- Symfony: https://symfony.com/doc/current/setup.html
- Doctrine: https://www.doctrine-project.org/
- Nginx: https://nginx.org/en/docs/

### Community:
- GitHub Issues: (your repository)
- Discord: (if you have one)
- Email: support@pushify.dev

---

## 🎉 Congratulations!

Projeniz artık production'da çalışıyor!

**Next Steps:**
1. Test et
2. İlk kullanıcıları davet et
3. Feedback topla
4. Optimize et
5. Marketing'e başla!

**Başarılar! 🚀**
