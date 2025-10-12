# 🧩 Laravel Domain Resource Generator (`make:d-resource`)

## توضیح کوتاه (Short description)
این دستور یک API Resource یا Resource Collection داخل ساختار Domain-oriented (پوشه `app/Domain/{Domain}/Http/Resources`) می‌سازد.  

Creates an API Resource or Resource Collection inside a Domain-oriented structure (`app/Domain/{Domain}/Http/Resources`).  


---


## Usage (نحوه استفاده)
```
php artisan make:d-resource NameResource --domain=DomainName [--model=ModelName] [--collection] [--force] [-v]
```

---

## Options
- `--domain` (required): domain name (e.g. `--domain=Test`).  
- `--model` / `-m`: model short name (e.g. `Post`) or FQCN (e.g. `App\Models\Post`).
- `--collection` / `-c`: create a Resource Collection instead of JsonResource.  
- `--force`: overwrite existing file.  
- `-v`: verbose output (shows which stub was used, extra info).

---

## مثال‌ها (Examples)

1. ساخت Resource معمولی:
```bash
php artisan make:d-resource PostResource --domain=Test 
```
3. بازنویسی با force:
```bash
php artisan make:d-resource PostResource --domain=Test --force 
```
4. ساخت Collection:
```bash
php artisan make:d-resource PostCollection --domain=Test --collection 
```
5. همراه با مدل:
```bash
php artisan make:d-resource OKmPostResource --domain=Test --model=faketm -v
```

---

## جزئیات پیاده‌سازی و نکات فنی (Implementation details)
- **تشخیص stub**: ابتدا `stubs/resource.stub` یا `stubs/resource.collection.stub` در پروژه را چک می‌کند، سپس مسیرهای ممکن داخل `vendor/laravel/framework` را، و در آخر یک stub موقت در `storage/framework/stubs` می‌سازد و از آن استفاده می‌کند. این طوری اگر لاراول شما stub را تغییر داد یا نسخهٔ لاراول فرق دارد، باز هم کار می‌کند.  
- **مدل (`--model`)**: مقدار ورودی نرمال می‌شود به FQCN دامنه و در `input` قرار می‌گیرد تا `parent::handle()` یا پردازش‌های بعدی از آن استفاده کنند. سپس `buildClass()` تضمین می‌کند که `use App\Domain\\{Domain}\\Models\\{Model};` در بالای فایل اضافه شود (حتی اگر parent import نساخته باشد).  
- **تشخیص Collection**: اگر `--collection` داده شود یا اسم به `Collection` ختم شود، خودش گزینهٔ collection را فعال می‌کند.  
- **اعتبار‌سنجی ورودی‌ها**: دستور قبل از اجرا `validateInput()` را اجرا می‌کند (بررسی `--domain`, نام کلاس، و `--model` برای الگوهای خطرناک). در صورت خطا اجرای دستور متوقف می‌شود و پیام مناسب چاپ می‌شود.  
- **پیش‌گیری از نوشتار دوگانه**: اگر فایل مقصد وجود داشته باشد و `--force` داده نشده باشد، دستور بلافاصله متوقف می‌شود و پیغام خطا می‌دهد.  
- **پیغام‌ها**: پیام‌ها (info/warn/error) روشن هستند، برای محیط CLI مناسب‌اند و خطایابی را ساده می‌کنند.

---

## خطاهای رایج و رفع آن‌ها (Troubleshooting)

- If you see `Resource already exists`: use `--force` to overwrite.
- `Model file not found`: import is added but file missing — create the model first or ignore if intended.
- Permission errors: ensure writable permissions for `app/Domain/...` and `storage/framework/stubs`.

---

## نسخه‌ها و سازگاری (Compatibility)
این دستور برای لاراول نسخه‌های اخیر (۸، ۹، ۱۰، 11 — بسته به محل stubs در vendor) طراحی شده؛ اگر لاراولت ساختار stubs متفاوت دارد، دستور temp-stub fallback از کار می‌افتد اما کد ما از چند مسیر vendor نامزد استفاده می‌کند تا با تغییر مسیرها مقاوم باشد.

Designed to work with modern Laravel versions (8..12+). Because vendor stub locations may vary between versions, the command tries several vendor paths and falls back to a safe temp stub under `storage/framework/stubs`.

---

## نمونهٔ فایل تولیدشده (Example file)
مثالی که در تست تولید شد (با `--model=tm` و دامنه `Test`):
```php
<?php

namespace App\Domain\Test\Http\Resources;

use App\Domain\Test\Models\Tm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VeryOKmPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Example: return new TmResource($this->resource);
        return parent::toArray($request);
    }
}
```

---
Developed by [Hadi HassanZadeh]