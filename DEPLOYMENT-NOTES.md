# Deployment & Email Setup Notes

## Current Status (as of 2025-11-28)

### Recent Changes Completed
- ✅ Implemented UK postcode lookup using postcodes.io API
- ✅ Added automatic formatting for UK postcodes (uppercase with space)
- ✅ Added visual feedback for postcode lookups (success/error messages)
- ✅ Reordered address fields: Postcode → Line 1 → Line 2 → City → County → Contact Info
- ✅ Moved original homepage to "Old Home Page" under Renter Resources nav
- ✅ Created temporary homepage with "Opening soon..." placeholder

### Application Overview
- **Framework**: Laravel 11 with Breeze authentication
- **Database**: SQLite (local) - needs MySQL/PostgreSQL for production
- **CSS**: Bootstrap 5 (removed Tailwind)
- **Features**:
  - User registration/login with email verification middleware
  - Rental property management (CRUD operations)
  - File attachments for rentals
  - Page view statistics
  - Postcode lookup integration

---

## Next Steps: Deployment & Email Configuration

### Pre-Deployment Questions to Answer

1. **Hosting Provider**:
   - [ ] Identify hosting platform (cPanel, VPS, Laravel Forge, etc.)
   - [ ] Confirm PHP 8.2+ support (required for Laravel 11)
   - [ ] SSH access available? Yes/No
   - [ ] Git deployment available? Yes/No

2. **Domain & DNS**:
   - [ ] Domain name configured?
   - [ ] SSL certificate available/needed?

3. **Database**:
   - [ ] Production database type (MySQL/PostgreSQL)
   - [ ] Database credentials from host
   - [ ] Migration plan for existing data

4. **Email Service Decision**:
   Choose one option:
   - [ ] **Option A**: Host's SMTP server (check sending limits)
   - [ ] **Option B**: Mailgun (5,000 emails/month free)
   - [ ] **Option C**: SendGrid (100 emails/day free)
   - [ ] **Option D**: Amazon SES (very cheap, pay-as-you-go)
   - [ ] **Option E**: Postmark (100 emails/month free)

5. **Email Requirements**:
   - Password reset emails (built into Breeze)
   - Email verification (currently using `verified` middleware)
   - Expected volume of emails?

---

## Deployment Checklist

### Local Preparation
- [ ] Run `php artisan config:cache` to test for config errors
- [ ] Run `php artisan route:cache` to test for route errors
- [ ] Run `php artisan view:cache` to precompile views
- [ ] Test all functionality locally one more time
- [ ] Export/backup local database if needed
- [ ] Create `.env.production` template with placeholder values

### Server Setup
- [ ] Upload all files (or git clone)
- [ ] Run `composer install --optimize-autoloader --no-dev`
- [ ] Set proper file permissions (storage/ and bootstrap/cache/)
- [ ] Create production database
- [ ] Configure `.env` file with production values
- [ ] Generate new `APP_KEY` with `php artisan key:generate`
- [ ] Run migrations: `php artisan migrate --force`
- [ ] Link storage: `php artisan storage:link`

### Production .env Configuration Needed
```bash
APP_NAME="Renters"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql  # or pgsql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

MAIL_MAILER=smtp  # or mailgun, ses, etc.
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Post-Deployment Testing
- [ ] Test homepage loads correctly
- [ ] Test user registration (creates account + sends verification email)
- [ ] Test login/logout
- [ ] Test password reset flow (sends email)
- [ ] Test creating a rental
- [ ] Test postcode lookup works on production
- [ ] Test file uploads work
- [ ] Check all navigation links work
- [ ] Test on mobile/different browsers

### Security Checklist
- [ ] `APP_DEBUG=false` in production
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secure
- [ ] `.env` file NOT in version control
- [ ] `storage/` directory writable but not web-accessible
- [ ] Force HTTPS if SSL available

---

## Common Issues & Solutions

### Issue: "No application encryption key has been specified"
**Solution**: Run `php artisan key:generate`

### Issue: "Permission denied" errors
**Solution**: Set permissions
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache  # adjust user as needed
```

### Issue: Email not sending
**Solution**:
1. Check `.env` mail configuration
2. Test with `php artisan tinker` then `Mail::raw('Test', fn($msg) => $msg->to('your@email.com')->subject('Test'));`
3. Check host's email sending limits
4. Verify MAIL_FROM_ADDRESS is valid for your domain

### Issue: Routes not working / 404 errors
**Solution**:
1. Check web server document root points to `/public`
2. Ensure `.htaccess` exists in `/public`
3. Enable `mod_rewrite` for Apache

### Issue: Postcode lookup not working
**Solution**: Check that your server allows outbound HTTPS requests to api.postcodes.io

---

## Email Service Quick Setup Guides

### Mailgun (Recommended)
1. Sign up at mailgun.com
2. Verify domain or use sandbox domain for testing
3. Get API credentials from dashboard
4. Add to `.env`:
```bash
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.com
MAILGUN_SECRET=your-api-key
MAILGUN_ENDPOINT=api.mailgun.net
```

### Gmail SMTP (For Testing Only)
```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-gmail@gmail.com
MAIL_PASSWORD=your-app-specific-password  # not regular password!
MAIL_ENCRYPTION=tls
```
Note: Requires "App Password" from Google Account settings

---

## Files Modified in Recent Session

- `resources/views/welcome.blade.php` - Temporary "Opening soon" homepage
- `resources/views/old-home-page.blade.php` - NEW: Original homepage content
- `resources/views/rentals/create.blade.php` - Postcode lookup + field reordering
- `resources/views/rentals/edit.blade.php` - Postcode lookup + field reordering
- `resources/views/layouts/app.blade.php` - Added "Old Home Page" link
- `routes/web.php` - Added old-home-page route

---

## Questions for Next Session

Before deploying, you'll need to tell me:
1. What hosting platform are you using?
2. Do you have SSH access or just FTP/cPanel?
3. Which email service do you want to use?
4. What's your expected email volume?
5. Is this a test deployment or going live for real users?

---

## Useful Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches (production optimization)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Check environment
php artisan about

# Run migrations
php artisan migrate --force  # needed in production

# Test email configuration
php artisan tinker
Mail::raw('Test message', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

---

## Contact for Next Session

To continue this work tomorrow, start your session with:

> "I'm ready to deploy my Laravel rental platform to production and set up email.
> Here's my hosting info: [provide details]
> I want to use [email service] for sending emails."

Then I can pick up exactly where we left off!
