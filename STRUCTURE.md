# Vinpack Project Structure for Hosting

## 📁 Directory Layout

```
vinpack/
├── public/                          ← WEB ROOT (Upload to public_html on Hostinger)
│   ├── index.html                   ← Homepage
│   ├── about.html                   ← About page
│   ├── contact.html                 ← Contact page
│   ├── products.html                ← Products page
│   ├── health-check.php             ← System health monitoring
│   ├── robots.txt                   ← SEO robots configuration
│   ├── sitemap.xml                  ← XML sitemap
│   ├── .htaccess                    ← Apache security & caching rules
│   │
│   ├── assets/                      ← Static files
│   │   ├── css/
│   │   │   ├── about.css
│   │   │   ├── contact.css
│   │   │   ├── index.css
│   │   │   └── products.css
│   │   ├── js/
│   │   │   └── main.js
│   │   └── images/
│   │
│   ├── api/                         ← API endpoints
│   │   ├── contact.php              ← Form submission handler
│   │   ├── delete-inquiry.php       ← Soft delete
│   │   └── permanent-delete.php     ← Hard delete
│   │
│   └── admin/                       ← Admin dashboard
│       ├── login.php                ← Admin login
│       ├── dashboard.php            ← Admin dashboard
│       ├── get-inquiry.php          ← Inquiry management
│       └── logout-api.php           ← Logout handler
│
├── config/                          ← CONFIGURATION (Outside web root)
│   └── database.php                 ← Database connection (KEEP SECURE!)
│
├── database/                        ← Database schemas
│   ├── schema.sql                   ← Main database schema
│   └── migrations.sql               ← Migration scripts
│
├── logs/                            ← Application logs (Auto-created)
│   ├── php_errors.log
│   ├── api_errors.log
│   └── backup.log
│
├── backups/                         ← Database backups (Auto-created)
│   └── vinpack_db_YYYYMMDD_*.sql.gz
│
├── .env                             ← Configuration values (DO NOT COMMIT)
├── .env.example                     ← Configuration template
├── .gitignore                       ← Git ignore rules
│
├── Dockerfile                       ← Docker image configuration
├── docker-compose.yml               ← Multi-container setup
├── backup.sh                        ← Automated backup script
│
└── DEPLOYMENT_GUIDE_V2.md           ← Complete deployment instructions
```

---

## 🚀 For Hostinger Shared Hosting

### Step 1: Prepare Files
1. Create a zip file with contents of `vinpack/`
2. Extract to your local machine

### Step 2: Upload to Hostinger
1. Login to Hostinger → File Manager
2. Navigate to `public_html`
3. Upload the entire `vinpack` folder
   ```
   public_html/
   └── vinpack/
       ├── public/          ← All website files here
       ├── config/          ← Database config
       ├── database/        ← Schemas
       ├── logs/            ← (will be created)
       ├── backups/         ← (will be created)
       └── .env             ← YOUR config
   ```

### Step 3: Create Symlink or Update Apache
In Hostinger File Manager:

**Option A (Recommended):** Create symbolic link
1. SSH to your server
2. Run: `ln -s /public_html/vinpack/public /public_html/www`
3. Your site is now accessible at domain.com

**Option B:** If symlinks not allowed
1. Update `.htaccess` in `public_html/` with:
   ```apache
   RewriteEngine On
   RewriteRule ^(.*)$ vinpack/public/$1 [L]
   ```

### Step 4: Create Required Directories
```bash
mkdir -p logs backups
chmod 755 logs backups
```

### Step 5: Set Permissions
```bash
chmod 644 public_html/vinpack/config/database.php
chmod 755 public_html/vinpack/config/
```

### Step 6: Update .env on Server
1. Edit `.env` via Hostinger File Manager
2. Set database credentials:
   ```
   DB_HOST=localhost
   DB_USER=your_db_user
   DB_PASS=your_db_password
   DB_NAME=your_db_name
   ```

### Step 7: Import Database Schema
1. Go to phpMyAdmin (via Hostinger cPanel)
2. Select your database
3. Import `database/schema.sql`

### Step 8: Test
1. Visit `yoursite.com` - should see homepage
2. Visit `yoursite.com/health-check.php` - should show system status
3. Visit `yoursite.com/admin/login.php` - should see login form

---

## 🐳 For Docker Deployment

### Step 1: Review Structure
Everything is already organized! Docker will:
- Serve files from `public/` directory
- Keep `config/` protected
- Create `logs/` and `backups/` automatically

### Step 2: Deploy
```bash
# Copy .env
cp .env.example .env

# Edit for your settings
nano .env

# Deploy
docker-compose up -d

# Access
# Website: http://localhost
# Admin:   http://localhost/admin/login.php
# Health:  http://localhost/health-check.php
```

---

## 📋 File Access Pattern

### What's Publicly Accessible
✅ `public/` - Everything here is public
- HTML pages
- CSS/JS files
- API endpoints
- Admin login (protected by password)

### What's PROTECTED
🔒 `config/` - Not accessible via web
- Database credentials safe
- Apache blocks access via `.htaccess`

### What's Auto-Created
📁 `logs/` - Created on first run
- Contains error logs
- For debugging

📁 `backups/` - Created on first run
- Automated backup files
- 7-day retention

---

## 🔒 Security

**Included Security Features:**
✅ `.htaccess` blocks:
- Direct access to `.env`
- Direct access to `config/` directory
- Direct access to `logs/` directory
- Directory listing (no folder browsing)

✅ Database credentials:
- Stored in `.env` (not in version control)
- Not accessible via web
- Protected by Apache rules

✅ API validation:
- Input validation
- Email verification
- Field length limits
- Error logging (no exposing errors to users)

---

## 📝 Important Notes

1. **Always copy `.env.example` to `.env`** before deploying
   ```bash
   cp .env.example .env
   ```

2. **Never commit `.env`** - Already in `.gitignore`

3. **Set database password on server**
   - Update `.env` with real credentials
   - Use Hostinger's database configuration

4. **Create directories if needed**
   ```bash
   mkdir -p logs backups
   chmod 755 logs backups
   ```

5. **Test health endpoint**
   ```bash
   curl https://yoursite.com/health-check.php
   # Should return JSON with system status
   ```

---

## 🎯 File Permissions (Hostinger/VPS)

```bash
# Read-only config
chmod 600 config/database.php

# Writable directories
chmod 755 logs/
chmod 755 backups/

# Web accessible
chmod 644 public/*.html
chmod 644 public/*.xml
chmod 644 public/*.txt
```

---

**Status:** ✅ Organized for easy hosting  
**Best For:** Hostinger, FTP upload, or Docker  
**Setup Time:** 5-10 minutes  
