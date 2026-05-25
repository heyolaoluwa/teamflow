# TeamFlow — PHP + MySQL (Shared Hosting Edition)
Works on ANY shared hosting with cPanel (Truehost, Hostinger, Bluehost, etc.)
No Node.js, no VPS, no extra buying needed.

## Files
```
teamflow-php/
├── index.html        ← The whole app (HTML + CSS + JS)
├── database.sql      ← Run this once in phpMyAdmin
├── api/
│   ├── config.php    ← ✏️ Edit your DB credentials here
│   ├── dashboard.php
│   ├── members.php
│   ├── shifts.php
│   ├── tasks.php
│   └── messages.php
```

## Deploy in 4 Steps

### Step 1 — Create your database in cPanel
1. Log in to Truehost cPanel
2. Go to **MySQL Databases**
3. Create a database e.g. `teamflow`
4. Create a user and password
5. Add the user to the database with **All Privileges**

### Step 2 — Run the SQL schema
1. In cPanel → open **phpMyAdmin**
2. Click your database on the left
3. Click the **SQL** tab
4. Open `database.sql`, copy everything, paste it in, click **Go**

### Step 3 — Edit config.php
Open `api/config.php` and fill in your 4 details:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'cpanelusername_teamflow');  // cPanel username + _ + db name
define('DB_PASS', 'your_password');
define('DB_NAME', 'cpanelusername_teamflow');
```
Note: Truehost prefixes DB names with your cPanel username automatically.

### Step 4 — Upload via File Manager
1. In cPanel → **File Manager** → go to `public_html`
2. Create a folder e.g. `teamflow`
3. Upload ALL files keeping the folder structure
4. Visit: `https://yourdomain.com/teamflow/`

Done! 🎉
