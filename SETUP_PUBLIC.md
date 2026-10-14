# 🚀 How to Host & Make Asha Bank (BMS) Public from GitHub

This guide walks you through publishing your **Asha Bank (BMS)** project to the public internet directly from your GitHub repository.

---

## ⚠️ Important Note: GitHub Pages vs. Full-Stack PHP

> **Why GitHub Pages alone won't work:**  
> GitHub Pages only hosts **static** files (HTML, CSS, JavaScript). Asha Bank is a full-stack dynamic banking application powered by **PHP 8** and a **MySQL relational database** with sessions, security, and live transactions.  
> 
> To host it publicly from GitHub, you need a cloud platform that supports **PHP + MySQL** and connects to your GitHub repository for continuous deployment.

---

## 📋 Step 0: Push Your Code to GitHub

Make sure your latest local code is committed and pushed to your GitHub repository:

```bash
# 1. Open Terminal in the project root
cd /Applications/XAMPP/xamppfiles/htdocs/BMS

# 2. Stage all files
git add .

# 3. Commit changes
git commit -m "feat: complete Bangladesh map, mobile responsive nav, and cloud hosting configs"

# 4. Push to your GitHub repository
git push origin main
```

---

## 🌟 Method 1: Railway.app (Recommended — Easiest & Auto-Deploys on Every Git Push)

[Railway](https://railway.app/) provides seamless GitHub integration with built-in MySQL support and instant SSL (`https://`).

### Step-by-Step Setup:
1. **Sign Up / Log In**:
   - Go to [railway.app](https://railway.app/) and sign in with your **GitHub** account.
2. **Create New Project**:
   - Click **+ New Project** → Select **Deploy from GitHub repo**.
   - Choose your **BMS** repository.
3. **Add MySQL Database**:
   - In your Railway project canvas, click **+ New** → **Database** → **Add MySQL**.
   - Railway will provision a private MySQL instance in seconds.
4. **Connect PHP App to MySQL**:
   - Click on your BMS web service card in Railway.
   - Go to **Variables** tab and click **Add Variable Reference** or enter:
     - `DB_HOST`: `${{MySQL.MYSQLHOST}}`
     - `DB_PORT`: `${{MySQL.MYSQLPORT}}`
     - `DB_NAME`: `${{MySQL.MYSQLDATABASE}}`
     - `DB_USER`: `${{MySQL.MYSQLUSER}}`
     - `DB_PASS`: `${{MySQL.MYSQLPASSWORD}}`
5. **Import Database**:
   - Option A: Visit your public URL: `https://your-app.up.railway.app/setup.php` and click **"Run Setup"** to auto-create all tables.
   - Option B: Use Railway's **Data** tab to import `database.sql`.
6. **Generate Domain**:
   - In your BMS web service settings, go to **Networking** → **Generate Domain**.
   - Your website is now live on the internet with free automatic HTTPS!

---

## 🌐 Method 2: Render.com (Free Web Service via Docker)

[Render](https://render.com/) automatically builds and deploys the repository using the included `Dockerfile`.

### Step-by-Step Setup:
1. Go to [render.com](https://render.com/) and sign in with GitHub.
2. Click **New +** → **Web Service**.
3. Select your **BMS** GitHub repository.
4. Render will auto-detect the `Dockerfile`:
   - **Environment**: Docker
   - **Branch**: `main`
5. **Database**:
   - Create a free MySQL database on [Aiven.io](https://aiven.io/) or [Clever Cloud](https://www.clever-cloud.com/).
   - In Render Web Service settings, add Environment Variables:
     - `DB_HOST`: your remote MySQL host
     - `DB_PORT`: `3306`
     - `DB_NAME`: your database name
     - `DB_USER`: your database username
     - `DB_PASS`: your database password
6. Click **Create Web Service**. Render will build and deploy your site with a free `onrender.com` URL.

---

## 🏢 Method 3: Traditional cPanel Hosting (InfinityFree, Hostinger, Namecheap)

If you have a shared hosting plan or free hosting like [InfinityFree](https://www.infinityfree.com/):

1. **Database Setup**:
   - In cPanel, go to **MySQL Databases** → Create a database (e.g. `yourname_ashabank`).
   - Create a user and grant **All Privileges**.
   - Open **phpMyAdmin** → Select your database → Click **Import** → Upload `database.sql`.
2. **Deploy from GitHub**:
   - In cPanel, click **Git Version Control** → Click **Create**.
   - Paste your GitHub Repository Clone URL (e.g. `https://github.com/yourusername/BMS.git`).
   - Set deployment path to `public_html` or a subfolder.
   - Click **Deploy**.
3. **Configure Database Credentials**:
   - In cPanel File Manager, edit `config/db.php`:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_PORT', '3306');
     define('DB_NAME', 'yourname_ashabank');
     define('DB_USER', 'yourname_dbuser');
     define('DB_PASS', 'your_strong_password');
     ```
4. Visit your domain: `https://yourdomain.com/`

---

## 🖥️ Method 4: VPS (DigitalOcean / Linode / AWS EC2 with Ubuntu)

For production deployment on an Ubuntu Linux server:

### 1. Server Setup:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install apache2 mysql-server php8.2 php8.2-mysql libapache2-mod-php git -y
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 2. Clone from GitHub:
```bash
cd /var/www/html
sudo git clone https://github.com/yourusername/BMS.git .
sudo chown -R www-data:www-data /var/www/html
```

### 3. Import Database:
```bash
sudo mysql -u root -p -e "CREATE DATABASE asha_bank CHARACTER SET utf8mb4;"
sudo mysql -u root -p asha_bank < /var/www/html/database.sql
```

### 4. Enable SSL with Let's Encrypt (Free HTTPS):
```bash
sudo apt install certbot python3-certbot-apache -y
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

---

## 🔒 Production Security Checklist Before Going Public

When making your application public:

- [ ] **Change Default Passwords**:
  - Update admin/staff passwords from the defaults in `database.sql`.
- [ ] **Set Production Mode**:
  - In `config/db.php`, change:
    ```php
    define('APP_ENV', 'production');
    ```
    This disables verbose PHP error displays to protect sensitive database details.
- [ ] **Delete or Protect `setup.php`**:
  - Once the database is created, delete `setup.php` or rename it so visitors cannot re-initialize your database.
- [ ] **Enable HTTPS / SSL**:
  - Always enforce HTTPS in production (automatically handled by Railway, Render, and Let's Encrypt).

---

## 💡 Summary Comparison

| Platform | Difficulty | Database Included | Continuous Git Deploy | Cost |
| :--- | :--- | :--- | :--- | :--- |
| **Railway.app** | ⭐ Easy (Recommended) | Yes (1-click MySQL) | ✅ Automatic | Free Trial / Low Pay-as-you-go |
| **Render.com** | ⭐ Easy (Docker) | External MySQL required | ✅ Automatic | Free tier available |
| **InfinityFree** | ⭐⭐ Moderate | Yes (phpMyAdmin) | ⚠️ Manual / Git Webhook | Free |
| **Ubuntu VPS** | ⭐⭐⭐ Advanced | Yes (Native MySQL) | ✅ Via GitHub Action | ~$4-6 / month |
