# 🧩 Laravel Domain Generator (`make:domain`)

---

## دستور / Command
**فارسی:**  
دستور: `php artisan make:domain {name} [--with-provider] [--register] [--force]`  
ایجاد اسکلت دامین در پروژه لاراول مطابق الگوی Domain-Oriented Design (DOD).

**English:**  
Command: `php artisan make:domain {name} [--with-provider] [--register] [--force]`  
Creates a Domain skeleton in a Laravel project following the Domain-Oriented Design (DOD) pattern.

---

## هدف دستور / Purpose
**فارسی:**  
ایجاد اسکلت دامین در مسیر `app/Domain/{DomainName}` شامل پوشه‌ها و فایل‌های پایه برای Models, Actions, Controllers, Requests, Resources, Policies, Routes و Providers.

**English:**  
Create a domain skeleton under `app/Domain/{DomainName}` including base folders and files for Models, Actions, Controllers, Requests, Resources, Policies, Routes and Providers.

---

## خروجی  / Output 
```
app/
 └── Domain/
      └── Blog/
           ├── Actions/
           │    └── .gitkeep
           ├── Http/
           │    ├── Controllers/
           │    │     └── .gitkeep
           │    ├── Requests/
           │    │     └── .gitkeep
           │    └── Resources/
           │          └── .gitkeep
           ├── Models/
           │    └── .gitkeep
           ├── Policies/
           │    └── .gitkeep
           ├── Providers/
           │    └── DomainServiceProvider.php
           └── Routes/
                ├── web.php
                └── api.php
```

Same structure as above. Each domain contains its own Actions, Http (Controllers/Requests/Resources), Models, Policies, Providers (optional DomainServiceProvider), and Routes files (web.php, api.php).

---

## منطق عملکرد / Behavior
1. نام دامنه را به StudlyCase تبدیل می‌کند (`blog` → `Blog`).  
2. بررسی می‌کند که پوشه قبلاً وجود نداشته باشد مگر `--force` داده شده باشد.  
3. پوشه‌ها و `.gitkeep` برای پوشه‌های خالی ساخته می‌شوند.  
4. فایل‌های `Routes/web.php` و `Routes/api.php` تولید می‌شوند با اسکلت اولیه.  
5. در صورت استفاده از `--with-provider`فایل `DomainServiceProvider.php` تولید می‌شود.  

1. Converts domain name to StudlyCase (`blog` → `Blog`).  
2. Checks whether the folder already exists and aborts unless `--force` is provided.  
3. Creates directories and `.gitkeep` placeholders for empty folders.  
4. Generates `Routes/web.php` and `Routes/api.php` stubs.  
5. If `--with-provider` is used, creates a `DomainServiceProvider.php`.  


---

## گزینه‌ها (Options)
- آپشن `--with-provider` : ایجاد `DomainServiceProvider` داخل دامین.  
- آپشن `--force` : بازنویسی دامین در صورت وجود قبلی.

- `--with-provider` : Create a `DomainServiceProvider` inside the domain.  
- `--force` : Overwrite the domain if it already exists.

---

## مثال‌ها / Examples
```bash
php artisan make:domain Blog
php artisan make:domain Blog --with-provider
php artisan make:domain Blog --force
```

---

## فایل‌های نمونه تولیدشده / Sample generated files
نمونه محتویات `Routes/web.php`، `Routes/api.php` و `Providers/DomainServiceProvider.php` در ادامه آمده است.

Sample contents for `Routes/web.php`, `Routes/api.php`, and `Providers/DomainServiceProvider.php` are provided below.

### `Routes/web.php`

```php
<?php
use Illuminate\Support\Facades\Route;

// Domain: Blog - web routes
Route::middleware('web')->group(function () {
    // Route::get('/posts', [App\Domain\Blog\Http\Controllers\PostController::class, 'index']);
});
```

### `Routes/api.php`

```php
<?php
use Illuminate\Support\Facades\Route;

// Domain: Blog - api routes
Route::prefix('api')->middleware('api')->group(function () {
    // Route::get('blog', [App\Domain\Blog\Http\Controllers\PostController::class, 'index']);
});
```

### `Providers/DomainServiceProvider.php`

```php
<?php

namespace App\Domain\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (file_exists(base_path('app/Domain/Blog/Routes/web.php'))) {
            $this->loadRoutesFrom(base_path('app/Domain/Blog/Routes/web.php'));
        }
        if (file_exists(base_path('app/Domain/Blog/Routes/api.php'))) {
            $this->loadRoutesFrom(base_path('app/Domain/Blog/Routes/api.php'));
        }
    }

    public function register(): void
    {
        // Register bindings, observers, policies, etc.
    }
}
```

---

Developed by [Hadi HassanZadeh]  