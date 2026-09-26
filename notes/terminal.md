To run this project on macOS using your Homebrew MySQL installation and PHP's built-in server, follow these steps:

### 1. Start MySQL & Import the Database

1. Open your Terminal and start the MySQL service:
```bash
brew services start mysql

```


2. Navigate into the project folder where `database.sql` is located:
```bash
cd path/to/BMS/asha_bank

```


3. Check the database configuration:
Open the files inside the `config/` folder (likely named `database.php`, `db.php`, or `config.php`) to see the required **database name**, **username**, and **password**.
4. Log into MySQL and create the database (replace `asha_bank_db` with whatever name is defined in your config):
```bash
mysql -u root -p

```


*(Press Return if you don't have a password set)*
```sql
CREATE DATABASE asha_bank_db;
EXIT;

```


5. Import `database.sql` into that database:
```bash
mysql -u root -p asha_bank_db < database.sql

```



---

### 2. Configure Credentials

Make sure your `config/` files match your local MySQL setup:

* **Host:** `127.0.0.1` or `localhost`
* **User:** `root`
* **Password:** *(your local MySQL password, or leave blank if unset)*
* **Database Name:** *(the database you created above)*

---

### 3. Run the Built-in PHP Server

1. Make sure PHP is installed:
```bash
php -v

```


*(If not installed, run `brew install php`)*
2. From inside the `asha_bank` directory, start the server:
```bash
cd path/to/BMS/asha_bank
php -S localhost:8000

```


3. Open your browser and visit:
```
http://localhost:8000

```



*(Note: There is also a `setup.php` file in the folder. If manual SQL import is tricky, you can simply visit `http://localhost:8000/setup.php` first, as many PHP apps use this script to initialize tables automatically.)*