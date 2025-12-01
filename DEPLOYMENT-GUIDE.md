# Deployment Guide - Rental Platform

## Prerequisites
- Plesk hosting with Laravel Toolkit
- MariaDB database (already created)
- GitHub repo: `https://github.com/charleswatts1308-source/rental-platform.git`

---

## Step 1: Connect Git Repository in Plesk

1. Log into Plesk control panel
2. Select your domain
3. Click **Git**
4. Click **Add Repository**
5. Enter repository URL: `https://github.com/charleswatts1308-source/rental-platform.git`
6. If repo is private, enter GitHub credentials (use Personal Access Token as password)
7. Click **OK** to clone

---

## Step 2: Set Document Root

1. In Plesk, go to **Hosting Settings**
2. Change Document Root to point to `/public` folder
3. Save

---

## Step 3: Configure Environment

In Plesk Terminal (or SSH), navigate to your Laravel folder and run:

```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env` file with production settings:

```
APP_NAME=Renters
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

MAIL_MAILER=smtp
MAIL_HOST=your_mail_host
MAIL_PORT=587
MAIL_USERNAME=your_mail_username
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Renters"
```

---

## Step 4: Install Dependencies

In Plesk Terminal:

```bash
composer install --no-dev --optimize-autoloader
```

---

## Step 5: Run Migrations

```bash
php artisan migrate
```

---

## Step 6: Cache Configuration (Production)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Step 7: Set Permissions

Ensure these folders are writable:

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

---

## Future Updates

After making changes locally:

1. Commit and push to GitHub:
   ```bash
   git add .
   git commit -m "Description of changes"
   git push
   ```

2. In Plesk Git panel, click **Pull**

3. If needed, run:
   ```bash
   php artisan migrate
   php artisan config:cache
   php artisan route:cache
   ```

---

## Troubleshooting

### 500 Error
- Check `storage/logs/laravel.log`
- Ensure `.env` exists and has correct values
- Ensure `storage/` and `bootstrap/cache/` are writable

### Database Connection Error
- Verify DB credentials in `.env`
- Check database exists and user has access

### Blank Page
- Run `php artisan config:clear`
- Check PHP version is 8.2+

---

## Important Notes

- Never commit `.env` to Git (contains secrets)
- `APP_DEBUG=false` in production (security)
- Email verification requires working SMTP settings
