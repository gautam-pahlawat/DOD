# <p align="center">📘 Domain‑Oriented Design (DOD) in Laravel</p>
**<p align="center">[Digging Deeper into Security](Docs/Security.md) | [Development Path](Docs/README.md)</p>**
---

## 🤔 What is DOD ?

**توضیح :**
Domain‑Oriented Design (DOD) روشی ساده و عملی برای سازمان‌دهی کد در پروژه‌های لاراول است که به جای تمرکز صرف روی لایه‌های سنتی (Controllers, Models, Views) کل کد را حول "دامین" یا حوزهٔ کسب‌وکار (مثلاً `Blog`, `Order`, `User`) قرار می‌دهد. هر دامنه شامل همهٔ اجزای مرتبط است: مدل‌ها، کنترلرها، ری‌کوئست‌ها، منابع، اکشن‌ها، سیاست‌ها و غیره.


Domain‑Oriented Design (DOD) is a practical way to organize Laravel code by grouping everything around business domains (e.g., `Blog`, `Order`, `User`) instead of technical layers. Each domain contains related pieces: models, controllers, requests, resources, actions, policies, etc.

---

## 📁 Recommended Folder Layout


مثال برای دامنهٔ `Blog`:

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
```

هر زیرپوشه هدف مشخصی دارد:
- `Actions` — واحدهای کوچک، با مسئولیت واحد (use‑cases) **— small single‑responsibility units (use‑cases).**
- `Http/Controllers` — کنترلر که اکشن‌ها را صدا می‌زنند **— thin controllers that call actions.**
- `Http/Requests` — فرم‌ریکوئست‌ها (اعتبارسنجی / authorization) **— validation and authorization.**
- `Http/Resources` — API Resources و Collections **— API Resources and Collections.**
- `Models` — Eloquent models و collectionها **— Eloquent models and custom collections.**
- `Policies` — دسترسی‌ها (authorization) **— authorization logic.**
- `Providers` — Domain-specific service providers (بارگذاری routes، ثبت bindings) **— domain service providers (load routes, register bindings).**
- `Routes` — فایل‌های `web.php` و `api.php` **— domain routes like `web.php` and `api.php`.**




---

## 🤨 Why use DOD ?


 **کوپه شدن کد بر اساس دامنه:** همهٔ فایل‌های مرتبط با یک حوزه کنار هم‌اند؛ پیدا کردن و تغییر دادن ساده‌تر می‌شود. <br>
 **قابلیت توسعه و نگهداری بهتر:** هر دامنه می‌تواند مستقلاٌ تکامل یابد (bounded contexts). <br>
 **تست‌پذیری بهتر:** اکشن های کوچک و مستقل قابل نوشتن تست واحد راحتی دارند. <br>
 **انعطاف در تیم‌های بزرگ:** تیم‌ها می‌توانند روی دامنه‌ها جداگانه کار کنند. <br>

- **Cohesion by domain:** All files related to a business domain are together; easier to locate and change.
- **Better maintainability and evolution:** Domains can evolve independently (bounded contexts).
- **Improved testability:** Small actions and clear boundaries facilitate unit testing.
- **Team scalability:** Teams can own domains instead of layers.

---

## 🩺 How pieces relate

- **Action:** a single use‑case (e.g., `CreatePost`) containing the core business operation.
- **Controller:** thin — receives request, may call Request for validation, invokes Action, returns Resource/Response.
- **Request:** validation and authorization; controllers or actions type‑hint them to get `$request->validated()`.
- **Model:** Eloquent models handle persistence and queries. A separate Repository is only useful for multiple data sources or swapping ORM.
- **Policy:** authorization rules for models/domain.
- **Resource:** transforms model/collection to array/JSON.
- **Provider:** load domain routes, register bindings, observers, policies.

---

## ⚖️ Practical conventions

- Models namespace: `App\Domain\{Domain}\Models`.
- Requests namespace: `App\Domain\{Domain}\Http\Requests`.
- Resources namespace: `App\Domain\{Domain}\Http\Resources`.
- Actions namespace: `App\Domain\{Domain}\Actions` — each action has `handle()` or `__invoke()`.
- Keep controllers thin; put business logic in Actions or Services.
- Use a DomainServiceProvider to load routes and register bindings.
- Write tests for Actions and for any custom make:* commands.

---

# <p align="center">👨‍💻 Quick usage example </p>

_این بخش از سند یک مرجع سریع و کاربردی برای استفاده از دستورات شخصی‌سازی‌شده پروژه (Domain‑Oriented Design) است. هر بخش شامل توضیح کوتاه، یک مثال عملی و لینک شده به مستند کامل است._

---


### 0️⃣ `make:command`
Generate commands and then copy the command code and paste it into the created files.
```
php artisan make:command MakeDomain
php artisan make:command MakeDomainAction
php artisan make:command MakeDomainController
php artisan make:command MakeDomainModel
php artisan make:command MakeDomainPolicy
php artisan make:command MakeDomainRequest
php artisan make:command MakeDomainResource
php artisan make:command MakeDomainServiceProvider
```
---


### 1️⃣ `make:domain`

Scaffold domain folders under `app/Domain/{Name}` (Models, Http, Actions, Policies, Providers, Routes).

**مثال کاربردی:**
```bash
php artisan make:domain Blog
```
ایجاد: `app/Domain/Blog/{Models,Actions,Http,Providers,Policies,Routes}`

**<p align="center">[👨‍🏫 Full documentation](Docs/DOMAIN.md)</p>**

---
### 2️⃣ `make:d-provider`

Create a Domain ServiceProvider.

**مثال کاربردی:**
```bash
php artisan make:d-provider Blog 
```

**<p align="center">[👨‍🏫 Full documentation](Docs/D-PROVIDER.md)</p>**
**<p align="center">[🤓 More about providers in laravel](https://laravel.com/docs/12.x/providers)</p>**

---
### 3️⃣ `make:d-model`

Create a domain model and optionally migration/factory/seeder.

**مثال کاربردی:**
```bash
php artisan make:d-model Post --domain=Blog -m -f -s
```
**<p align="center">[👨‍🏫 Full documentation](Docs/D-MODEL.md)</p>**
**<p align="center">[🤓 More about eloquent models in laravel](https://laravel.com/docs/12.x/eloquent)</p>**


---

### 4️⃣ `make:d-action`

Scaffold an Action class (invokable or custom method) with optional model/request imports.

**مثال کاربردی:**
```bash
php artisan make:d-action CreatePost --domain=Blog --invokable --model=Post 
```

**<p align="center">[👨‍🏫 Full documentation](Docs/D-ACTION.md)</p>**
**<p align="center">[🤓 More about actions in DOD](Docs/MoreAboutActions.md)</p>**

---

### 5️⃣ `make:d-request`

Generate FormRequest classes placed inside the domain (not global `app/Http/Requests`).

**مثال کاربردی:**
```bash
php artisan make:d-request StorePost --domain=Blog
```

**<p align="center">[👨‍🏫 Full documentation](Docs/D-REQUEST.md)</p>**
**<p align="center">[🤓 More about form request validation in laravel](https://laravel.com/docs/12.x/validation#form-request-validation)</p>**


---

### 6️⃣ `make:d-controller`

Create controllers inside the domain. Supports resource controllers and rewrites imports to domain Requests and Models.

**مثال کاربردی:**
```bash
php artisan make:d-controller PostController --domain=Blog --model=Post --resource --requests
```

**<p align="center">[👨‍🏫 Full documentation](Docs/D-CONTROLLER.md)</p>**
**<p align="center">[🤓 More about controllers in laravel](https://laravel.com/docs/12.x/controllers)</p>**


---

### 7️⃣ `make:d-resource`

Create API Resources placed inside domain; supports collection mode and model association.

**مثال کاربردی:**
```bash
php artisan make:d-resource PostResource --domain=Blog --model=Post
php artisan make:d-resource PostCollection --domain=Blog --collection --model=Post
```

**<p align="center">[👨‍🏫 Full documentation](Docs/D-RESOURCE.md)</p>**
**<p align="center">[🤓 More about resources in laravel(in eloquent)](https://laravel.com/docs/12.x/eloquent-resources)</p>**

---

### 8️⃣ `make:d-policy`

Create a domain policy; optionally scaffold methods with model type hints and show how to register it.

**مثال کاربردی:**
```bash
php artisan make:d-policy Post --domain=Blog --model=Post
```

**<p align="center">[👨‍🏫 Full documentation](Docs/D-POLICY.md)</p>**
**<p align="center">[🤓 More about policies in laravel(in authorization)](https://laravel.com/docs/12.x/authorization#creating-policies)</p>**


---

# <p align="center">👩‍❤️‍💋‍👨  Deployment & Integration Guide </p>
> این بخش از سند نحوهٔ ثبت Provider، نگاشت مدل به جدول، استفاده از Action و Request در Controller، تعریف و ثبت Policy، بارگذاری Routeها، مدیریت دامنه‌ها و دیگر تنظیمات لازم برای استفادهٔ عملی از ساختار Domain‑Oriented Design (DOD) را به صورت گام‌به‌گام توضیح می‌دهد.

---

## 🔔 introduction

**توضیح :**
DOD به شما کمک می‌کند همهٔ فایل‌های مرتبط با یک حوزهٔ کسب‌وکار (Domain) را کنار هم نگه دارید. برای اینکه Laravel به درستی با این ساختار کار کند باید: namespaceها صحیح باشند، ServiceProviderهای دامنه را ثبت کنید (یا آن‌ها را از طریق یک مکانیزم auto‑discover/loader بارگذاری کنید)، و در زمان تولید فایل‌ها مسیرها را به namespace دامنه هدایت کنید.

DOD groups everything by business domains. To integrate with Laravel: make sure namespaces are consistent, register Domain ServiceProviders (or load them automatically), and ensure generated files use the domain namespaces.

---

## 9️⃣ Registration of Domain ServiceProvider 🔗

**چرا و چگونه؟**
هر دامنه می‌تواند یک Provider مخصوص به خود داشته باشد که وظیفهٔ بارگذاری فایل‌های route دامنه، ثبت bindingها، register کردن policyها، یا mount کردن امکانات domain را برعهده دارد. بهترین روش این است که هر دامنه یک `Providers/DomainServiceProvider.php` داشته باشد و شما آن را در `bootstrap/providers.php` یا از طریق auto‑registration ثبت کنید.

**<p align="center">[Registering Providers in laravel](https://laravel.com/docs/12.x/providers#registering-providers)</p>**


**Provider (app/Domain/Blog/Providers/DomainServiceProvider.php):**
```php
<?php

namespace App\Domain\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bind domain interfaces here
        // $this->app->bind(Contract::class, Impl::class);
    }

    public function boot(): void
    {
        // load domain routes (if exist)
        if (file_exists(base_path('app/Domain/Blog/Routes/web.php'))) {
            $this->loadRoutesFrom(base_path('app/Domain/Blog/Routes/web.php'));
        }

        if (file_exists(base_path('app/Domain/Blog/Routes/api.php'))) {
            $this->loadRoutesFrom(base_path('app/Domain/Blog/Routes/api.php'));
        }

        // register policies here or via AuthServiceProvider
    }
}
```

**Manual registration:**
- فایل `bootstrap/providers.php` را باز کنید و در آرایهٔ `providers` خط زیر را اضافه کنید (با رعایت فاصله و کاما):
```php
App\Domain\Blog\Providers\DomainServiceProvider::class,
```
- **نکته مهم:** قبل از ویرایش فایل bootstrap/providers.php بهتر است از آن یک backup بگیرید.

```bash
cp bootstrap/providers.php bootstrap/providers.php.bak
```

**Why & How?**
Registering a DomainServiceProvider lets you load domain routes, register bindings and policies. You can register it manually in `bootstrap/providers.php` or via a central auto-loader.

**Manual registration snippet (in `bootstrap/providers.php` providers array):**
```php
App\Domain\Blog\Providers\DomainServiceProvider::class,
```

---


## 🔟 Model-to-Table mapping 🔩

در DOD مدل‌ها در `App\Domain\{Domain}\Models` قرار می‌گیرند. 

- نام جدول: اگر از convention لاراول (`snake_case` جمع) پیروی کنید نیازی به تغییر نیست. مثال: مدل `Post` به جدول `posts` نگاشت می‌شود. برای override جدول از `protected $table = 'my_table'` در مدل استفاده کنید.
**<p align="center">[Table names in eloquent models](https://laravel.com/docs/12.x/eloquent#table-names)</p>**

**app/Domain/Blog/Models/Post.php:**
```php
<?php

namespace App\Domain\Blog\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts'; // optional
    protected $fillable = ['title','body','user_id'];

    public function author()
    {
        return $this->belongsTo(\App\Domain\Blog\Models\User::class, 'user_id');
    }
}
```

**note:**
- Models live under `App\Domain\{Domain}\Models`.
- Table name follows Laravel conventions; override with `$table` if needed. Use `$connection`, `$fillable`, `$casts`, etc.
- Keep migrations in `database/migrations` unless you have a specific reason.

---

## 1️⃣1️⃣ Using Action and FormRequest in Controller 📦

- Controllers should be thin: validate and delegate to Actions. Inject Action and FormRequest in method signature. Return Resources.

**اصول کلی:**
- کنترلرها کد کمی داشته باشند: فقط وظیفهٔ فراخوانی Action و برگرداندن Response را دارند.
- منطق بیزینسی داخل Actionها قرار می‌گیرد. Action می‌تواند invokable (`__invoke`) یا دارای متد `handle()` باشد.
- برای اعتبارسنجی از FormRequestها استفاده کنید و آن‌ها را در signature متد کنترلر type‑hint کنید تا لاراول خودکار validation را اجرا کند.

**Controller :**
```php
<?php

namespace App\Domain\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Blog\Actions\CreatePost; // action
use App\Domain\Blog\Http\Requests\StorePostRequest; // form request
use App\Domain\Blog\Http\Resources\PostResource;

class PostController extends Controller
{
    public function store(StorePostRequest $request, CreatePost $action)
    {
        // $request is validated automatically
        $data = $request->validated();

        // Business logic encapsulated inside Action
        $post = $action->handle($data);

        return new PostResource($post);
    }
}
```
```php
public function store(StorePostRequest $request, CreatePost $createPost)
{
    $post = $createPost->handle($request->validated());
    return new PostResource($post);
}
```
**Action :**
```php
<?php

namespace App\Domain\Blog\Actions;

use App\Domain\Blog\Models\Post;

class CreatePost
{
    public function handle(array $data): Post
    {
        return Post::create($data);
    }
}
```

**شرح:**
- با `CreatePost` به عنوان یک کلاس Action که `handle(array $data)` را پیاده می‌کند، منطق ایجاد یک پست در یک مکان متمرکز قرار می‌گیرد.
- `StorePostRequest` مسئول validation و authorization است.
- نتیجه توسط `PostResource` برگردانده می‌شود.

---

## 1️⃣2️⃣ Defining, registering and using policies 🛡️

Create policy, register it (AuthServiceProvider or Domain provider), use `authorize()` or `can` middleware.

**روند کار:**
1. ایجاد Policy داخل `app/Domain/{Domain}/Policies` توسط دستور `make:d-policy` یا دستی.
2. ثبت Policy ها در داخل `DomainServiceProvider` با `Gate::policy()`.
3. در کنترلرها یا مسیرها از `can` یا `authorize()` استفاده کنید.

**`boot()`:**
```php
use Illuminate\Support\Facades\Gate;

public function boot()
{
    Gate::policy(\App\Domain\Blog\Models\Post::class, \App\Domain\Blog\Policies\PostPolicy::class);
}
```

**usage in controller:**
```php
public function update(StorePostRequest $request, Post $post)
{
    $this->authorize('update', $post); // uses PostPolicy::update
    // ...
}
```

---

## 1️⃣3️⃣ Loading Routes and Domain Routing Structure 📍

Place domain routes in domain-specific route files and load them from the DomainServiceProvider.

**روند کار:**
- قرار دادن routeهای مربوط به دامنه در `app/Domain/{Domain}/Routes/web.php` و `api.php` معمولاً بهترین روش است.
- `DomainServiceProvider::boot()` باید این فایل‌ها را با `loadRoutesFrom()` بارگذاری کند.

**example routes file (app/Domain/Blog/Routes/api.php):**
```php
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/blog')->middleware('api')->group(function () {
    Route::apiResource('posts', \App\Domain\Blog\Http\Controllers\PostController::class);
});
```

---

**<p align="center">[Digging Deeper into Security](Docs/Security.md) | [Development Path](Docs/README.md)</p>**
