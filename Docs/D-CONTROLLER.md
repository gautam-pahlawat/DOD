# 🧩 Laravel Domain Controller Generator (`make:d-controller`)


## توضیح کوتاه (Short Description)
این دستور یک کنترلر در ساختار Domain (app/Domain/{Domain}/Http/Controllers) ایجاد می‌کند و در صورت درخواست، کلاس‌های FormRequest برای عملیات Store و Update را نیز تولید می‌کند.

Creates a controller inside a specific Domain structure (app/Domain/{Domain}/Http/Controllers) and optionally generates FormRequest classes for store/update operations.

---

## Usage / دستور استفاده

```bash
php artisan make:d-controller {ControllerName} --domain={DomainName} [options]
---



### Example
php artisan make:d-controller UserController --domain=Test
```
---

## ⚙️ Options

| Option           | Description (EN)                                                              | توضیح کوتاه (FA)                                      |
| ---------------- | ----------------------------------------------------------------------------- | ----------------------------------------------------- |
| `--domain=`      | Name of the domain where the controller will be created (required)            | نام دامنه برای ایجاد کنترلر (الزامی)                  |
| `--model=`       | Bind the controller to a specific Eloquent model                              | اتصال کنترلر به مدل مشخص                              |
| `--requests`     | Create FormRequest classes (StoreXRequest & UpdateXRequest) inside the domain | ایجاد کلاس‌های FormRequest برای عملیات Store و Update |
| `--force`        | Overwrite existing controller if exists                                       | بازنویسی کنترلر در صورت وجود                          |
| `-r, --resource` | Generate a resource controller                                                | ایجاد کنترلر Resource                                 |
| `-m, --model`    | Generate controller with model binding (Laravel default)                      | ایجاد کنترلر با اتصال به مدل (Laravel default)        |

---

## 🧱 Directory Structure Example

```
app/
└── Domain/
    └── Blog/
        ├── Http/
        │   ├── Controllers/
        │   │   └── PostController.php
        │   └── Requests/
        │       ├── StorePostRequest.php
        │       └── UpdatePostRequest.php
        └── Models/
            └── Post.php

```

---

## 💡 Notes

این دستور برای ساخت کنترلر در ساختار **Domain Oriented Design (DOD)** طراحی شده است.  

### 📋 نکات مهم:


- این دستور فقط ساختار Domain را پشتیبانی می‌کند.

- اگر فولدر Domain موجود نباشد، دستور متوقف می‌شود.

- کلاس FormRequest‌ها تنها در صورتی ایجاد می‌شوند که گزینه --requests فعال و مدل مشخص شده باشد.

- همه پیام‌های کنسول حاوی علائم +، >، * و ! برای نمایش وضعیت موفقیت/هشدار/خطا هستند.

---

## 🧪 Test Commands (برای تست عملکرد)

```bash
# Create controller only
php artisan make:d-controller PostController --domain=Blog

# Create controller + FormRequest + connecting to model
php artisan make:d-controller PostController --domain=Blog --model=Post --requests

# Overwrite existing controller
php artisan make:d-controller PostController --domain=Blog --force
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
Inspired by Laravel’s `make:controller` command, extended for Domain-Oriented Design.

---
