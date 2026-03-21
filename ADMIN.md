# Admin Dashboard Setup

Simple admin authentication with username and password.

## Quick Start

1. **Set credentials in .env:**
```ini
ADMIN_USERNAME=admin
ADMIN_PASSWORD=your_password_here
```

2. **Login at:** `http://localhost:8000/admin/login.php`

3. **Default credentials:**
   - Username: `admin`
   - Password: `admin123`

## Features

- ✅ View all inquiries
- ✅ Update inquiry status
- ✅ Add admin notes
- ✅ Delete/archive inquiries
- ✅ View deleted inquiries
- ✅ Search inquiries
- ✅ Login attempt limiting (5 attempts per 15 minutes)
- ✅ Activity logging (track all actions)
- ✅ Auto-logout when tab closes

## Security Features

### Login Attempt Limiting
- Maximum 5 failed login attempts per 15 minutes
- After 5 failures, user must wait 15 minutes before trying again
- Prevents brute force attacks

### Activity Logging
All admin actions are logged in `logs/admin_activity.log`:
- Login success/failure
- Status updates
- Delete operations
- Logout events
- Tab close auto-logout

**View logs:**
```bash
tail -f logs/admin_activity.log
```

**Log format:**
```
[2026-03-20 14:30:45] | Action: LOGIN_SUCCESS | User: admin | IP: 127.0.0.1
[2026-03-20 14:35:12] | Action: STATUS_UPDATED | User: admin | IP: 127.0.0.1 | Details: Inquiry #5 updated to contacted
[2026-03-20 14:40:00] | Action: TAB_CLOSED (Auto-logout) | User: admin | IP: 127.0.0.1
```

### Auto-Logout on Tab Close
- Closing the admin tab automatically logs you out
- Session is destroyed to prevent unauthorized access
- Event is logged with timestamp

## That's it!

No complex security setup needed. Simple and straightforward with basic protections.
