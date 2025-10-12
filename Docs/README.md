# <p align="center"> 🗺 Full Development Path `Roadmap` </p>


## 👨‍🏫 مسیر توسعهٔ اپلیکیشن ` گام‌به‌گام `
در ادامه یک مسیر توسعهٔ عملی و دقیق از اولین قدم تا deployment برای ایجاد یک موجودیت (مثلا `Post`) را با توضیحات عملی هر گام آورده شده.

---

> **فرض اولیه:** شما یک دامنهٔ `Blog` دارید و قصد دارید موجودیت `Post` را پیاده‌سازی کنید.

### گام 0 — آماده‌سازی دامنه
- دستور زیر یا اقدام دستی برای ساخت ساختار پوشه‌ها
```bash
php artisan make:domain Blog
```
- بررسی کنید شامل زیرپوشه‌ها باشد: 

```
app/
 └── Domain/
      └── Blog/
           ├── Actions/
           ├── Http/
           │    ├── Controllers/
           │    ├── Requests/
           │    └── Resources/
           ├── Models/
           ├── Policies/
           ├── Providers/
           └── Routes/
                ├── web.php
                └── api.php
```

**<p align="center">[👨‍🏫 Full documentation](DOMAIN.md)</p>**

- Create a Domain ServiceProvider.

```bash
php artisan make:d-provider Blog 
```

**<p align="center">[👨‍🏫 Full documentation](D-PROVIDER.md)</p>**

---

### گام 1 — ساخت مدل
- دستور زیرا را اجرا کنید (در حالت DOD دستور ما migration/factory/seeder را نیز domain-aware خواهد ساخت).
```bash
php artisan make:d-model Post --domain=Blog -m -f -s
```
- فایل مدل: `app/Domain/Blog/Models/Post.php`.

**نکات داخل مدل:**
1. Namespace صحیح: `namespace App\Domain\Blog\Models;`
2. Use statements (در صورت نیاز)
```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
```

**<p align="center">[👨‍🏫 Full documentation](D-MODEL.md)</p>**

---

### گام 2 — تعریف وابستگی‌ها و روابط (Relationships)
- در مدل `Post` روابط را تعریف کنید:
```php
public function author() { return $this->belongsTo(User::class, 'user_id'); }
public function comments() { return $this->hasMany(Comment::class); }
public function tags() { return $this->belongsToMany(Tag::class); }
```
- **نکته:** اگر مدل دیگر در همان domain است از FQCN `App\Domain\Blog\Models\User::class` استفاده کنید؛ اگر مدل در `App\Models` است از آن‌جا استفاده کنید.

---

### گام 3 — Mass assignment (fillable / guarded)
- تصمیم‌گیری بین `$fillable` و `$guarded` 
- پیشنهاد: از `$fillable` صریح استفاده کنید.
```php
protected $fillable = ['title', 'body', 'user_id', 'published_at'];
```
- این از حملات mass-assignment جلوگیری می‌کند.

---

### گام 4 — Attribute casting
- برای تبدیل اتوماتیک نوع‌ها از `$casts` استفاده کنید.
```php
protected $casts = [
    'published_at' => 'datetime',
    'is_featured' => 'boolean',
];
```

---

### گام 5 — Attribute classes (Accessors & Mutators) 
- از Accessor/Mutator سنتی یا کلاس `Attribute` (Laravel 9+) استفاده کنید.
```php
protected function title(): Attribute {
    return Attribute::make(
        get: fn($v) => ucfirst($v),
        set: fn($v) => strtolower($v)
    );
}
```

---

### گام 6 — Scopes (Query Scopes)
- برای قانون‌گذاری queryهای تکراری از scopes استفاده کنید.
```php
public function scopePublished($q) { return $q->whereNotNull('published_at'); }
```
- استفاده: 
```php
Post::published()->get();
```

---

### گام 7 — Soft Deletes
- اگر لازم است:
```php
use Illuminate\Database\Eloquent\SoftDeletes;
class Post extends Model { use SoftDeletes; }
```
- migration: `$table->softDeletes();`

---

### گام 8 — boot() و booted() در مدل
- برای ثبت observerها یا افزودن global scopeها استفاده کنید.
```php
protected static function booted() {
    static::creating(function($model){ /* modify attributes */ });
    static::addGlobalScope('published', function(Builder $builder){ /* ... */});
}
```

---

### گام 9 — Observer و Model Events
- ایجاد Observer: 
```bash
php artisan make:observer PostObserver --model=App\Domain\Blog\Models\Post
```
- ثبت Observer: در `DomainServiceProvider::boot` یا `AppServiceProvider` ثبت کنید:
```php
Post::observe(PostObserver::class);
```
- در Observer متدهایی مانند `created`, `updated`, `deleted` را پیاده کنید.

---

### گام 10 — Model Collection
- اگر نیاز به collection سفارشی دارید، کلاس collection بسازید و آن را در مدل ارجاع دهید.
```php
protected $collection = PostCollection::class;
```
- این کمک می‌کند که رفتارهای collection مخصوصی را اضافه کنید.

---

### گام 11 — Route Model Binding
- در routeها از type-hint مدل استفاده کنید تا Binding اتوماتیک انجام شود.
```php
Route::apiResource('posts', PostController::class);
// controller show(Post $post) { ... }
```
- برای تغییر کلید binding (مثلا `slug`) در مدل:
```php
public function getRouteKeyName() { return 'slug'; }
```

---

### گام 12 — FormRequest (Validation & Authorization)
- ایجاد: 
```bash
php artisan make:d-request StorePostRequest --domain=Blog
```
- در Request: rules و authorize را تنظیم کنید:
```php
public function rules() { return ['title' => 'required|string']; }
public function authorize() { return Auth::check(); }
```
- استفاده در controller method signature 

**<p align="center">[👨‍🏫 Full documentation](D-REQUEST.md)</p>**

---

### گام 13 — Action (Business logic)
- ایجاد Action: 
```bash
php artisan make:d-action CreatePost --domain=Blog
```
- اکشن باید single‑responsibility باشد و با Eloquent تعامل کند:
```php
class CreatePost { public function handle(array $data): Post { return Post::create($data); } }
```
- Inject action into controller method (method injection) to keep controller thin.

**<p align="center">[👨‍🏫 Full documentation](D-ACTION.md)</p>**

---

### گام 14 — Resource (API response shaping)
- ساخت resource: 
```bash
php artisan make:d-resource PostResource --domain=Blog --model=Post
```
- استفاده: 
```php
return new PostResource($post);
```

**<p align="center">[👨‍🏫 Full documentation](D-RESOURCE.md)</p>**

---

### گام 15 — Policy (Authorization)
- ساخت: 
```bash
php artisan make:d-policy PostPolicy --domain=Blog --model=Post
```
- ثبت: در `AuthServiceProvider` یا domain provider.
- استفاده: 
```php
$this->authorize('update', $post);
```

**<p align="center">[👨‍🏫 Full documentation](D-POLICY.md)</p>**

---

### گام 16 — Routes و Controller scaffolding
- ساخت controller: 
```bash
php artisan make:d-controller PostController --domain=Blog --model=Post --resource --requests
```
- بررسی و ویرایش متدها جهت استفاده از Actionها و Resourceها.

**<p align="center">[👨‍🏫 Full documentation](D-CONTROLLER.md)</p>**

---

### گام 17 — Factories و Seeders
- بررسی factory: ensure 
```php
protected $model = App\Domain\Blog\Models\Post::class;
```
- ایجاد seeder و درج مثال استفاده از factory درون آن.


---

## نکات جمع‌بندی و توصیه‌های کاربردی
- همیشه از FormRequest برای validation استفاده کنید؛ خطاها و پیام‌ها را متمرکز نگه می‌دارد.
- از Policyها برای authorization سطح‌بالا و از authorize() در FormRequest برای checks قبل از validation هم می‌توان بهره برد (مثلاً اگر کاربر باید مالک باشد برای create یا update).
- Actions را تمرکز منطق تجاری قرار دهید؛ آن‌ها قابل تست و باز‌استفاده هستند.
- از naming convention و قرارگیری فایل‌ها پیروی کنید تا autoload و generatorها بدون خطا کار کنند.

---
