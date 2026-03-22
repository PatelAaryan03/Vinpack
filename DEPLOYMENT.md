# Vinpack Deployment Guide

## Overview
Vinpack is a professional business inquiry management system with a PHP backend, MySQL database, and modern admin dashboard. This guide covers production deployment.

---

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [Installation](#installation)
4. [Database Setup](#database-setup)
5. [Web Server Configuration](#web-server-configuration)
6. [Environment Configuration](#environment-configuration)
7. [Security Setup](#security-setup)
8. [Starting the Application](#starting-the-application)
9. [Verification](#verification)
10. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Minimum Specifications
- **PHP:** 7.2 or higher
- **MySQL:** 5.7 or higher (or MariaDB 10.1+)
- **Web Server:** Apache 2.4+ (with mod_rewrite) or Nginx 1.14+
- **Operating System:** Linux/Unix (not Windows/Mac for production)
- **Disk Space:** 500MB minimum
- **RAM:** 512MB minimum (1GB recommended)

### Required PHP Extensions
```
- mysqli (for MySQL)
- filter (for input validation)
- session (for admin authentication)
```

### Required Services
- **MySQL/MariaDB:** Running and accessible
- **Web Server:** Apache or Nginx
- **Mail Server:** SMTP (optional, for email notifications)

---

## Pre-Deployment Checklist

- [ ] All credentials stored in `.env` (not in version control)
- [ ] `.env` file is in `.gitignore` ✅
- [ ] `.env.example` template created with placeholders ✅
- [ ] SSL certificate obtained (Let's Encrypt or CA)
- [ ] Domain/IP address registered
- [ ] Database backups configured
- [ ] Monitoring/logging system ready
- [ ] Admin backup credentials stored securely (password manager)

---

## Installation

### Step 1: Clone Repository

```bash
cd /var/www
git clone https://github.com/yourusername/vinpack.git
cd vinpack
chmod 755 start-server.sh  # Make startup script executable
```

### Step 2: Install Dependencies

All PHP files are included. No Composer/npm dependencies required.

```bash
# Verify directory structure
ls -la
# Should show: config/, database/, logs/, public/, start-server.sh, etc.
```

### Step 3: Create Environment File

```bash
# Copy template
cp .env.example .env

# Edit with your values
nano .env
```

Edit `.env` with your production credentials:

```env
# MySQL Connection
DB_HOST=localhost
DB_PORT=3306
DB_USER=vinpack_user
DB_PASS=your_secure_password_here_change_this
DB_NAME=vinpack_prod
```

**⚠️ Security:** Never commit `.env` to version control. Use strong passwords.

---

## Database Setup

### Step 1: Create Database User

```bash
# Connect to MySQL
mysql -u root -p

# Run these commands in MySQL shell:
```

```sql
-- Create application database
CREATE DATABASE vinpack_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user (change password!)
CREATE USER 'vinpack_user'@'localhost' IDENTIFIED BY 'your_secure_password_here_change_this';

-- Grant permissions
GRANT ALL PRIVILEGES ON vinpack_prod.* TO 'vinpack_user'@'localhost';
FLUSH PRIVILEGES;

-- Exit MySQL
EXIT;
```

### Step 2: Initialize Database Schema

```bash
# From project root directory
mysql -u vinpack_user -p vinpack_prod < database/schema.sql

# You'll be prompted for the password you set above
```

### Step 3: Verify Database

```bash
# Connect to verify
mysql -u vinpack_user -p vinpack_prod

# In MySQL shell, verify tables exist:
SHOW TABLES;
# Should show: deleted_inquiries, inquiries

# Exit
EXIT;
```

---

## Web Server Configuration

### Option A: Apache

Create `/etc/apache2/sites-available/vinpack.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    ServerAdmin admin@yourdomain.com
    
    # Redirect HTTP to HTTPS
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    ServerAdmin admin@yourdomain.com
    
    # Document root
    DocumentRoot /var/www/vinpack/public
    
    # SSL Certificate (Let's Encrypt)
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem
    
    # Enable rewrite module
    <Directory /var/www/vinpack/public>
        AllowOverride All
        Require all granted
        
        # PHP handler
        AddHandler application/x-httpd-php .php
        
        # Prevent directory listing
        Options -Indexes +FollowSymLinks
    </Directory>
    
    # Logs
    ErrorLog /var/log/apache2/vinpack_error.log
    CustomLog /var/log/apache2/vinpack_access.log combined
    
    # Security headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

Enable the site:

```bash
sudo a2ensite vinpack.conf
sudo a2enmod ssl rewrite
sudo systemctl restart apache2
```

### Option B: Nginx

Create `/etc/nginx/sites-available/vinpack`:

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

# HTTPS Server
server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/vinpack/public;
    index index.php index.html;
    
    # SSL Certificates (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    
    # Security Headers
    add_header X-Content-Type-Options "nosniff";
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    
    # PHP Handler
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;  # Adjust PHP version
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Logs
    access_log /var/log/nginx/vinpack_access.log;
    error_log /var/log/nginx/vinpack_error.log;
    
    # Deny direct access to sensitive files
    location ~ /\. {
        deny all;
    }
    location ~ /config/ {
        deny all;
    }
    location ~ /database/ {
        deny all;
    }
    location ~ /logs/ {
        deny all;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/vinpack /etc/nginx/sites-enabled/
sudo systemctl restart nginx
```

---

## Environment Configuration

### Setting Up `.env`

1. **Database Configuration:**
```env
DB_HOST=localhost        # Or your remote database IP
DB_PORT=3306            # Standard MySQL port
DB_USER=vinpack_user    # User created in database setup
DB_PASS=SecurePassword123!  # Strong password (20+ chars recommended)
DB_NAME=vinpack_prod    # Database name
```

2. **Admin Credentials:**
Update in login.php (one-time setup):
```php
// The admin password should be changed immediately after first login
// Store admin access credentials securely (e.g., password manager)
// Default: admin / [set your password]
```

3. **Security Settings:**
- Session timeout: 20 minutes (configured in check-session.php)
- Password field in login.php reads from environment
- All queries use prepared statements (SQL injection protected)

### File Permissions

```bash
cd /var/www/vinpack

# Web server readable/writable
sudo chown -R www-data:www-data .
sudo chmod 755 .
sudo chmod 755 public
sudo chmod 755 public/*
sudo chmod 755 config

# Logs directory writable
sudo chmod 777 logs

# Restrict sensitive directories
sudo chmod 750 config
sudo chmod 750 database
```

---

## Security Setup

### 1. SSL/TLS Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache  # For Apache
# OR
sudo apt install certbot python3-certbot-nginx   # For Nginx

# Generate certificate (auto setup)
sudo certbot certonly --apache -d yourdomain.com -d www.yourdomain.com
# OR
sudo certbot certonly --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renew (runs daily)
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

### 2. Firewall Configuration

```bash
# UFW (Ubuntu/Debian)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

### 3. Database Security

```bash
# Remove anonymous users
mysql -u root -p
```

```sql
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DELETE FROM mysql.db WHERE Db='test' OR Db='test_%';
FLUSH PRIVILEGES;
EXIT;
```

### 4. PHP Security

Edit `/etc/php/7.4/apache2/php.ini`:

```ini
# Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source,file_get_contents,readfile

# Hide PHP version
expose_php = Off

# Increase timeouts
max_execution_time = 30
upload_max_filesize = 20M
post_max_size = 20M

# Session security
session.secure = On
session.httponly = On
session.samesite = "Strict"
```

### 5. Backup Strategy

```bash
# Create backup script: /usr/local/bin/backup-vinpack.sh
#!/bin/bash

BACKUP_DIR="/backups/vinpack"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/var/www/vinpack"

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u vinpack_user -p'DB_PASS' vinpack_prod | gzip > $BACKUP_DIR/database_$TIMESTAMP.sql.gz

# Backup files
tar -czf $BACKUP_DIR/files_$TIMESTAMP.tar.gz $PROJECT_DIR/

# Keep only last 30 days
find $BACKUP_DIR -type f -mtime +30 -delete

echo "Backup completed: $TIMESTAMP"

# Set as cron job (daily at 2 AM)
# 0 2 * * * /usr/local/bin/backup-vinpack.sh
```

```bash
sudo chmod +x /usr/local/bin/backup-vinpack.sh

# Add to crontab
chmod 0644 /etc/cron.d/vinpack-backup
echo "0 2 * * * root /usr/local/bin/backup-vinpack.sh" | sudo tee /etc/cron.d/vinpack-backup
```

---

## Starting the Application

### Option 1: Apache/Nginx (Production)

Web server starts automatically:

```bash
# Apache
sudo systemctl start apache2
sudo systemctl status apache2

# OR Nginx
sudo systemctl start nginx
sudo systemctl status nginx

# Enable on boot
sudo systemctl enable apache2   # or nginx
```

Visit: `https://yourdomain.com`

### Option 2: PHP Development Server

Only for development/testing:

```bash
cd /var/www/vinpack
./start-server.sh          # Default port 8000
./start-server.sh 3000     # Custom port 3000

# Visit: http://localhost:8000
```

---

## Verification

### 1. Website Access
- Visit `https://yourdomain.com` → Should show home page ✓
- Visit `https://yourdomain.com/about.html` → About page ✓
- Visit `https://yourdomain.com/products.html` → Products page ✓
- Submit contact form → Data saved in database ✓

### 2. Admin Access
- Visit `https://yourdomain.com/admin/login.php` → Login page ✓
- Login with credentials → Dashboard loads ✓
- View submitted inquiries → Data displays correctly ✓
- Update status → Changes persist ✓

### 3. Database Connectivity
```bash
# Test connection
mysql -h localhost -u vinpack_user -p vinpack_prod -e "SELECT COUNT(*) FROM inquiries;"
```

### 4. Health Check API
```bash
curl https://yourdomain.com/health-check.php

# Should show: {"status":"ok","timestamp":"2024-..."}
```

### 5. Log Verification
```bash
# Check logs for errors
tail -f /var/log/apache2/vinpack_error.log    # Apache
# OR
tail -f /var/log/nginx/vinpack_error.log      # Nginx

# PHP errors
tail -f /var/www/vinpack/logs/php_errors.log
```

---

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed
```
Error: "Connection failed: (2002) No such file or directory"
```
**Solution:**
- Verify MySQL running: `sudo systemctl status mysql`
- Check .env credentials: `grep DB_ .env`
- Test manually: `mysql -u vinpack_user -p vinpack_prod`

#### 2. Admin Login Fails
```
Error: "Incorrect password" or "Login failed"
```
**Solution:**
- Check password variable in login.php
- Verify .env DB_PASS matches database user password
- Check login rate limiting (5 attempts per 15 min)
- Clear session: `rm /var/lib/php/sessions/*` (Linux)

#### 3. Forms Not Saving Data
```
Error: "Failed to submit inquiry" despite valid input
```
**Solution:**
- Check database transaction support
- Verify table structure: `SHOW TABLES;`
- Check MySQL error log: `tail -f /var/log/mysql/error.log`
- Verify user permissions: `SHOW GRANTS FOR 'vinpack_user'@'localhost';`

#### 4. SSL Certificate Error
```
Error: "ERR_SSL_PROTOCOL_ERROR" or "certificate not trusted"
```
**Solution:**
- Renew certificate: `sudo certbot renew`
- Check certificate: `sudo certbot certificates`
- Verify domain points to server IP: `nslookup yourdomain.com`

#### 5. Permission Denied Errors
```
Error: "Permission denied" on file operations
```
**Solution:**
```bash
sudo chown -R www-data:www-data /var/www/vinpack
sudo chmod 755 /var/www/vinpack
sudo chmod 777 /var/www/vinpack/logs
```

---

## Production Best Practices

1. **Regular Backups:** Daily automated backups ✓
2. **Monitoring:** Monitor server resources and error logs
3. **Updates:** Keep PHP, MySQL, and web server updated
4. **Security Audits:** Periodically review security headers
5. **Logging:** Maintain audit trail of admin actions
6. **Secrets Management:** Use environment variables, never commit credentials
7. **SSL/TLS:** Always use HTTPS in production ✓
8. **Rate Limiting:** Built-in (5 login attempts per 15 min) ✓
9. **Input Validation:** All inputs validated and sanitized ✓
10. **Prepared Statements:** All DB queries use prepared statements (no SQL injection) ✓

---

## Support & Maintenance

- **Security Issues:** Report immediately
- **PHP Version:** Update to PHP 8.0+ when possible
- **Database:** Consider read replicas for scalability
- **Monitoring:** Set up uptime monitoring (Pingdom, UptimeRobot)
- **Updates:** Subscribe to security mailing lists

---

## License & Contact

For issues or questions, contact the development team.

Last Updated: 2024
Version: 1.0 (Production Ready)
