# ✅ Hostinger Deployment Checklist

## Quick Setup (5-10 minutes)

### Pre-Deployment
- [ ] Backup your Hostinger database
- [ ] Verify domain is pointing to your server
- [ ] Note down your database credentials

### Step 1: Upload Files
```bash
# On your computer
cd /Users/aaryanpatel/Desktop/Projects/Vinpack
zip -r vinpack.zip . -x ".git/*" ".github/*" "node_modules/*"
```
- [ ] Upload `vinpack.zip` to Hostinger `public_html/`
- [ ] Extract the zip file
- [ ] New structure: `public_html/vinpack/public/`

### Step 2: Create Required Directories
Via Hostinger File Manager, in `public_html/vinpack/`:
```
[ ] logs/       (create folder)
[ ] backups/    (create folder)
```

### Step 3: Set Permissions
Via SSH (if available):
```bash
chmod 755 logs backups
chmod 644 config/database.php
chmod 644 .env
```

Or in File Manager:
- [ ] Set `logs/` permissions to 755
- [ ] Set `backups/` permissions to 755
- [ ] Set `config/database.php` permissions to 644

### Step 4: Configure Database
- [ ] Copy `.env.example` to `.env`
- [ ] Edit `.env` with your Hostinger credentials:
  - `DB_HOST` = Usually `localhost`
  - `DB_USER` = Your database user
  - `DB_PASS` = Your database password
  - `DB_NAME` = Your database name
  - `ADMIN_PASSWORD` = Strong password for admin

### Step 5: Initialize Database
1. [ ] Go to Hostinger cPanel → Databases → phpMyAdmin
2. [ ] Select your database
3. [ ] Click Import
4. [ ] Choose `vinpack/database/schema.sql`
5. [ ] Click Import
6. [ ] Wait for success message

### Step 6: Point Domain to Public Folder
**Option A: Via .htaccess (Recommended)**
Create `.htaccess` in `public_html/` with:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ vinpack/public/$1 [L]
```
- [ ] File created and saved

**Option B: Change DocumentRoot**
Contact Hostinger support to change DocumentRoot to `/public_html/vinpack/public/`

### Step 7: Test Everything
- [ ] Visit `https://yoursite.com/` → See homepage
- [ ] Visit `https://yoursite.com/about.html` → See about page
- [ ] Visit `https://yoursite.com/health-check.php` → See JSON status
- [ ] Visit `https://yoursite.com/admin/login.php` → See login form
- [ ] Submit test contact form → Check database (phpMyAdmin)
- [ ] Check logs: `vinpack/logs/api_errors.log` → No errors

### Step 8: Set Up Automated Backups
Via SSH (if available):
```bash
# Add to crontab
crontab -e

# Add this line:
0 2 * * * /home/username/public_html/vinpack/backup.sh
```
- [ ] Backup script scheduled (optional but recommended)

---

## 🔧 Troubleshooting

### "404 Page Not Found"
**Solution:** 
- Verify `.htaccess` in `public_html/` is correct
- Or change DocumentRoot to `/public_html/vinpack/public/`

### "Cannot connect to database"
**Solution:**
- Check `.env` file values match Hostinger database credentials
- Verify database user has privileges
- Test connection in phpMyAdmin

### "Permission denied" on logs
**Solution:**
```bash
# Via SSH
chmod 755 vinpack/logs
chmod 755 vinpack/backups
chmod 644 vinpack/.env
```

### "health-check.php shows database error"
**Solution:**
- Verify `.env` database credentials
- Test in phpMyAdmin that credentials work
- Restart your Hostinger site

---

## 📁 Final Structure on Hostinger

```
public_html/
├── vinpack/
│   ├── public/          ← Website files (served by .htaccess)
│   ├── config/          ← Protected config
│   ├── database/        ← Schema files
│   ├── logs/            ← Auto-created
│   ├── backups/         ← Auto-created
│   ├── .env             ← Your configuration
│   └── ...
└── .htaccess            ← Routes requests to vinpack/public/
```

---

## 🚀 Post-Deployment

### Google Search Console
1. [ ] Add site to Google Search Console
2. [ ] Submit sitemap: `https://yoursite.com/sitemap.xml`
3. [ ] Submit robots.txt: `https://yoursite.com/robots.txt`
4. [ ] Request indexing

### Email Notifications (Optional)
1. [ ] Update Web3Forms API key in `.env` if needed
2. [ ] Test contact form submission
3. [ ] Verify email received

### Monitoring (Optional)
1. [ ] Set up monitoring for `/health-check.php`:
   ```bash
   # Check every 5 minutes
   */5 * * * * curl -s https://yoursite.com/health-check.php | grep "healthy" || alert
   ```

---

## 📞 Support

**All documentation:**
- Setup: `STRUCTURE.md` (this file structure)
- Deployment: `DEPLOYMENT_GUIDE_V2.md` (detailed guide)
- Troubleshooting: `DEPLOYMENT_GUIDE_V2.md` (Troubleshooting section)

**Quick links:**
- Homepage: `public/index.html`
- Admin: `public/admin/login.php`
- Health: `public/health-check.php`
- API: `public/api/contact.php`

---

**Status:** Ready for Hostinger deployment ✅
**Estimated Time:** 5-10 minutes to deploy
**Success Indicator:** https://yoursite.com → Shows homepage with no errors
