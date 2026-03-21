# Vinpack Development Guide

## Quick Start

### **Option 1: Easiest - Use the startup script** ⭐

```bash
./start-server.sh
```

Then open: **http://127.0.0.1:8000**

---

### **Option 2: Manual** (if script doesn't work)

```bash
cd /Users/aaryanpatel/Desktop/Projects/Vinpack
php -S 127.0.0.1:8000
```

---

## Database Setup

Before first use, run the database schema:

```sql
-- From MySQL client:
source /Users/aaryanpatel/Desktop/Projects/Vinpack/database/schema.sql
```

---

## Admin Access

1. Start the server (see above)
2. Go to: **http://127.0.0.1:8000/admin/login.php**  
3. Default credentials:
   - Username: `admin`
   - Password: `admin123` (change this!)

---

## Environment Variables

Copy `.env.example` to `.env` and fill in your details:

```bash
cp .env.example .env
```

Edit `.env` with your MySQL credentials.

---

## Project Structure

```
Vinpack/
├── index.html              # Home page
├── contact.html            # Contact form
├── products.html           # Products page
├── about.html              # About page
├── admin/
│   ├── login.php          # Admin login
│   ├── dashboard.php      # View inquiries
│   └── get-inquiry.php    # Inquiry details
├── api/
│   ├── contact.php        # Form submission API
│   └── sync-inquiries.php # Auto-sync offline inquiries
├── config/
│   └── database.php       # Database config
├── assets/
│   ├── css/styles.css
│   ├── js/main.js         # Contact form logic + offline mode
│   └── images/
└── database/
    └── schema.sql         # Database schema
```

---

## How It Works

### User Flow:
1. User fills contact form → **Web3Forms** sends email immediately
2. Form also saved to localStorage (if MySQL offline) or database
3. User sees: "Thank you! We will reach you in 2-3 working days"

### Admin Flow:
1. Login to **http://127.0.0.1:8000/admin**
2. View all inquiries with status tracking
3. See which came from offline mode, when they synced, etc.
4. Mark as read/replied/archived

---

## Offline Mode

- When MySQL is **down** or **no internet**: Inquiry saves to browser's localStorage
- Every 30 seconds: Auto-sync attempts to upload to database
- When MySQL comes **back online**: All pending inquiries sync automatically
- Users don't know the difference - they see the same message always

---

## Features

✅ **Web3Forms Integration** - Reliable email notifications  
✅ **Hybrid Storage** - Database + localStorage fallback  
✅ **Auto-Sync** - Silent background syncing every 30 seconds  
✅ **Admin Dashboard** - Full inquiry management  
✅ **Security** - Credentials in `.env` (never in git)  
✅ **Responsive Design** - Works on mobile & desktop  

---

## Credentials Storage

**IMPORTANT:** Never commit `.env` to GitHub!

- `.env` = Your actual passwords (local only) 🔐
- `.env.example` = Safe template (committed to git) ✅
- `.gitignore` = Prevents `.env` from uploading ✅

