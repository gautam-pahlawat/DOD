# 🧩 Laravel Domain Model Generator (`make:d-model`)

A powerful custom Artisan command for **Domain-Oriented Design (DOD)** in Laravel.  
This command creates a **Model** inside your custom domain structure (e.g. `app/Domain/Test/Models/Post.php`)  
and optionally generates related **migration**, **factory**, and **seeder** files automatically —  
with correct namespaces and references.

---

## 📦 Command Overview

```bash
php artisan make:d-model {ModelName} --domain={DomainName} [options]
```

### Example

```bash
php artisan make:d-model Post --domain=Blog -m -f -s
```

✅ This will generate:
```
app/Domain/Blog/Models/Post.php
database/migrations/2025_10_08_000000_create_posts_table.php
database/factories/PostFactory.php
database/seeders/PostSeeder.php
```

---

## ⚙️ Options

| Option | Description | Example |
|--------|--------------|----------|
| `--domain=` | **(Required)** Domain name where the model will be placed. | `--domain=Shop` |
| `-m, --migration` | Create a migration file. | `-m` |
| `-f, --factory` | Create a factory file. | `-f` |
| `-s, --seed` | Create a seeder file. | `-s` |
| `-p, --pivot` | Mark as pivot model (adds `$incrementing = false;`). | `-p` |
| `--force` | Overwrite the model if it already exists. | `--force` |

---

## 🧱 Directory Structure Example

```
app/
└── Domain/
    └── Blog/
        └── Models/
            └── Post.php
database/
├── factories/
│   └── PostFactory.php
├── seeders/
│   └── PostSeeder.php
└── migrations/
    └── xxxx_xx_xx_xxxxxx_create_posts_table.php
```

---

## 🧠 Features

✅ Creates model inside domain namespace automatically  
✅ Generates migration, factory, and seeder (if flags used)  
✅ Patches `PostFactory` to reference domain model  
✅ Updates `PostSeeder` to include factory usage example  
✅ Checks domain folder existence  
✅ Prevents overwriting unless `--force` is given  
✅ Works seamlessly with Laravel’s default `stubs` system

---

## 🔍 Seeder Auto-Patching Example

When the `--seed` flag is used, the seeder file will be auto-patched to include:
```php
public function run(): void
{
    // Example: create 10 Post records via factory
    Post::factory()->count(10)->create();
}
```

---

## 💡 Notes 

این دستور برای ساخت مدل در ساختار **Domain Oriented Design (DOD)** طراحی شده است.  
به شما اجازه می‌دهد تا فایل‌های مدل، مهاجرت، کارخانه (Factory) و سیدر (Seeder) را  
در محل درست دامنه‌ی خود ایجاد کنید.

### 📋 نکات مهم:
- گزینه‌ی `--domain` اجباری است.
- اگر مدل از قبل وجود داشته باشد، بدون `--force` بازنویسی نمی‌شود.
- در صورت استفاده از `--seed`، اگر `--factory` داده نشده باشد، خودش به‌صورت خودکار ساخته می‌شود.
- در فایل Seeder، دستور نمونه‌ی ساخت ۱۰ رکورد با فکتوری به‌صورت خودکار اضافه می‌شود.
- مسیرهای ساخته‌شده به ساختار دامنه شما کاملاً هماهنگ هستند.

---

## 🧪 Test Commands (برای تست عملکرد)

```bash
# Create model only
php artisan make:d-model Post --domain=Blog

# Create model + migration
php artisan make:d-model Post --domain=Blog -m

# Create model + factory
php artisan make:d-model Post --domain=Blog -f

# Create model + seeder
php artisan make:d-model Post --domain=Blog -s

# Create model + all extras (migration, factory, seeder)
php artisan make:d-model Post --domain=Blog -m -f -s

# Overwrite existing model
php artisan make:d-model Post --domain=Blog --force
```

---

## 🧰 Requirements

- Laravel 10+ (fully compatible with Laravel 12)
- PHP 8.1+
- Domain folder must exist before running the command:
  ```
  app/Domain/{YourDomain}/
  ```

---

## ✨ Credits

Developed by [Hadi HassanZadeh]  
Inspired by Laravel’s `make:model` command, extended for Domain-Oriented Design.

---
