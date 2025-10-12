# 🧩 Laravel Domain Provider Generator (`make:d-provider`)
---

## دستور / Command
دستور: `php artisan make:d-provider {domain} {name?} [--force]`  
این دستور یک ServiceProvider مخصوص دامنه (DomainServiceProvider) در ساختار DOD ایجاد می‌کند.

Command: `php artisan make:d-provider {domain} {name?} [--force]`  
Creates a Domain-scoped ServiceProvider under `app/Domain/{Domain}/Providers`.

---

## خلاصهٔ عملکرد / Summary
این فرمان فایل provider را در `app/Domain/{Domain}/Providers/{Name}.php` می‌سازد. 

This command generates a provider file at `app/Domain/{Domain}/Providers/{Name}.php`. 

---

## خروجی / Output
When successful the command prints:
```
Created directory: app/Domain/{Domain}/Providers    # only if missing
Provider created: app/Domain/{Domain}/Providers/{Name}.php
```

---


## گزینه‌ها / Options
- `domain` (required)
- `name` (optional) 
- `--force`, `-f` (optional) 
---

## استاب‌ها / Stubs

- `{{NAMESPACE}}` → `App\Domain\{Domain}\Providers`
- `{{CLASS}}` → provider class name
- `{{DOMAIN}}` → domain studly name
- `{{ROUTES_PATH}}` → `app/Domain/{Domain}/Routes`

**نمونهٔ ساده استاب (stubs/provider.stub):**
```php
<?php

namespace {{NAMESPACE}};

use Illuminate\Support\ServiceProvider;

class {{CLASS}} extends ServiceProvider
{
    public function boot(): void
    {
        if (file_exists(base_path('{{ROUTES_PATH}}/web.php'))) {
            \$this->loadRoutesFrom(base_path('{{ROUTES_PATH}}/web.php'));
        }
        if (file_exists(base_path('{{ROUTES_PATH}}/api.php'))) {
            \$this->loadRoutesFrom(base_path('{{ROUTES_PATH}}/api.php'));
        }
    }

    public function register(): void
    {
        //
    }
}
```

## Examples / مثال‌ها

1. Create default DomainServiceProvider:
```bash
php artisan make:d-provider Blog
```

2. Create a custom-named provider:
```bash
php artisan make:d-provider Blog BlogServiceProvider
```

3. Overwrite existing provider:
```bash
php artisan make:d-provider Blog BlogServiceProvider --force
```

4. Use a custom stub:
```bash
php artisan make:d-provider Blog CustomProvider --stub=./stubs/my-provider.stub
```

---

## بررسی خروجی فایل / What to check after running
1. File exists: `app/Domain/{Domain}/Providers/{Name}.php`.  
2. Namespace inside file equals `App\\Domain\\{Domain}\\Providers`.  


---

## Edge cases & troubleshooting / موارد لبه و رفع خطا
- **No domain folder:** Command aborts with message — create domain first (`php artisan make:domain {Domain}`).
- **Custom stub path invalid:** Command will warn and fall back to inline template.
- **Failure during write:** The command attempts rollback of created files and prints error details.

---

## Notes for maintainers / نکات برای توسعه‌دهندگان
- Keep `stubs/provider.stub` in repo to ensure consistent output across environments.
- Consider using a PHP AST (`nikic/php-parser`) for robust `config/app.php` edits if you need to support lots of custom configs.
- Keep messages developer-friendly and avoid silent failures — command intentionally errs loudly on ambiguous situations.

---

Developed by [Hadi HassanZadeh]  