# <p align="center"> 👮 Security `cheat sheet` </p>

> این سند به‌صورت مختصر و کاربردی توضیح می‌دهد که کد مربوط به Authentication (احراز هویت)، Authorization (مجوزدهی) و Validation (اعتبارسنجی) در یک پروژه Laravel با ساختار Domain-Oriented کجا قرار بگیرد، چگونه با بقیهٔ اجزا (Model, Controller, Action, Request, Policy, Provider, Route) تعامل کند و چه الگوهایی برای قابل‌توسعه و قابل‌پشتیبانی بودن مناسب‌اند.

> <p>This file explains—practically and without fluff—where to keep authentication, authorization and validation code in a Laravel project organized by Domains. It shows how these pieces interact with Models, Controllers, Actions, FormRequests, Policies, Providers and Routes.</p>


---

## 🎭 Authentication 

<div dir="rtl">

**مکان:** تنظیمات احراز هویت مرکزی در `config/auth.php` باقی می‌ماند. Guards را همانجا تعریف کنید. این فایل را در سطح پروژه نگه دارید، نه در داخل دامنه.

**<p align="center">[⚠️ Authentication](https://laravel.com/docs/12.x/authentication)</p>** </br>


**اجرای واقعی:** middlewareها در روت‌ها یا گروه‌های روت دامنه اعمال می‌شوند؛ برای کنترل دسترسی به منابع دامین، در `DomainServiceProvider` می‌توانید گروه‌های route را با middlewareبندی مشخص بارگذاری کنید.

**<p align="center">[🚧 HTTP Basic Authentication](https://laravel.com/docs/12.x/authentication#http-basic-authentication)</p>** </br>


**کنترلرهای ورود/ثبت‌نام:** می‌توانند در `App\Http\Controllers\Auth` یا در `App\Domain\{Domain}\Http\Controllers\Auth` قرار گیرند.

**<p align="center">[💂 Manually Authenticating Users](https://laravel.com/docs/12.x/authentication#authenticating-users)</p>** </br>


**پیاده‌سازی واقعی (login, register, password reset, 2FA):** از **Fortify** یا starter kits استفاده کنید یا برای APIها از **Sanctum/Passport**. این بسته‌ها در سطح پروژه نصب و پیکربندی می‌شوند، ولی نقاط اتصال (controllers / actions) در داخل Domain قابل فراخوانی‌اند. </br></br>

**اگر هر دامنه استفادهٔ متفاوتی از guardها دارد (مثلاً domain مخصوص users و domain مخصوص admins):**  در `config/auth.php` باید guards مربوط را تعریف کنید و در `DomainServiceProvider` آن‌ها را در middleware route group انتخاب کنید.

**<p align="center">[📝 Adding Custom Guards](https://laravel.com/docs/12.x/authentication#adding-custom-guards)</p>** 

</div>


**Example:**
```php
// app/Domain/Blog/Routes/web.php
Route::middleware(['auth:web'])->group(function(){
    Route::resource('posts', \App\Domain\Blog\Http\Controllers\PostController::class);
});
```

---

## 🤝 Authorization 


**مکان:** Policyها در `App\Domain\{Domain}\Policies` قرار می‌گیرند.

**استفاده:** 
- در کنترلرها : **[🛠️ Via the User Model](https://laravel.com/docs/12.x/authorization#authorizing-actions-using-policies)**
- در روت‌ها : **[🕵️ Via Middleware](https://laravel.com/docs/12.x/authorization#via-middleware)**


---

## 🔬 Validation 

**مکان:** FormRequestها در `App\Domain\{Domain}\Http\Requests` قرار می‌گیرند.

**کاربرد:** از آنها برای rules و authorize استفاده کنید. کنترلرها type‑hint کنند تا لاراول قبل از اجرای متد درخواست را validate کند.

**چرا داخل domain؟** چون rules و authorization معمولا وابسته به منطق دامنه است و نگهداری در  آنجا بهتر است.

**منطق پیچیده‌تر مجوزدهی :** `authorize()` در FormRequest می‌تواند برای کنترل دسترسی اولیه استفاده شود (اگر صرفا validation نباشد). اما منطق پیچیده‌تر مجوزدهی را در Policy نگه دارید.



**Example:**
```php
class StorePostRequest extends FormRequest
{
    public function authorize() { return true; }
    public function rules() { return ['title' => 'required|string']; }
}

// controller
public function store(StorePostRequest $request, CreatePost $action) {
    $data = $request->validated();
    $post = $action->handle($data);
}
```

- Always prefer typed FormRequest in controller method signatures: `public function store(StorePostRequest $request)` and then `$data = $request->validated();`.
- Use `prepareForValidation()` to normalize inputs (cast dates, merge defaults) before rules run.
- Put FormRequests in `App/Domain/{Domain}/Http/Requests`. Use `$this->authorize()` for quick per-request permission checks, but prefer Policies for model rules.

---

## 🔁 How they interact `Controller / Action flow` 


**<p align="center">➡️Route receives request</p>**
**<p align="center">⬇️</p>**
**<p align="center">Middleware authenticates user (`auth` or `auth:sanctum`)</p>**
**<p align="center">⬇️</p>**
**<p align="center">Route points to Controller action (thin controller)</p>**
**<p align="center">⬇️</p>**
**<p align="center">Controller method type-hints a FormRequest → validation & (optional) authorization happen.</p>**
**<p align="center">⬇️</p>**
**<p align="center">In the controller method, use Policy before the main action (`$this->authorize('update', $post)`), or if you used `authorize()` in the FormRequest, that step has already been done</p>**
**<p align="center">⬇️</p>**
**<p align="center">Action returns model/DTO; Controller wraps it with Resource and returns response</p>**
**<p align="center">⬇️</p>**
**<p align="center">Controller returns the result to the user (via Resources) 🔚</p>**


---


## 💡 Notes 

- **Configuration (auth.php) is global.** Don’t scatter guard/provider settings across domains.
- **Authorization vs Validation:** `authorize()` in FormRequest is for request-level quick checks; for model logic choose Policies.
- **Actions keep business logic testable.** Put DB operations in Actions, not in controllers.
- **Don't duplicate rules/policies** across domains; if two domains share models, consider a shared package or a global `App\Policies` namespace.
- **For API error format** unify validation and auth error responses in `app/Exceptions/Handler.php` to return consistent JSON.
- Use a domain provider to load domain routes and register policies: `Gate::policy(Model::class, Policy::class)`.
- Register domain providers in `bootstrap/providers.php`.

---

## 🚨 API vs Web specifics

- For SPAs/API use **Sanctum** (tokens) or **Passport** (OAuth) depending on needs. Enforce `auth:sanctum` middleware on API routes. Domain controllers for API should return `JsonResource`s.
- For web with session auth, continue to use `auth` middleware and server-rendered views.

---



## <p align="center"> 💾 Code snippets — practical examples </p>

### a) Route (api.php / domain route file)

```php
// app/Domain/Blog/Routes/api.php
Route::middleware(['auth:sanctum'])->group(function(){
    Route::apiResource('posts', \App\Domain\Blog\Http\Controllers\PostController::class);
});
```

### b) Controller (thin)

```php
namespace App\Domain\Blog\Http\Controllers;

use App\Domain\Blog\Http\Requests\StorePostRequest;
use App\Domain\Blog\Actions\CreatePost;
use App\Http\Controllers\Controller;

class PostController extends Controller
{
    public function store(StorePostRequest $request)
    {
        $data = $request->validated();
        $post = (new CreatePost())->handle($data);
        return new \App\Domain\Blog\Http\Resources\PostResource($post);
    }
}
```

### c) FormRequest

```php
namespace App\Domain\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize()
    {
        // quick check — or keep true and use Policies
        return auth()->check();
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
        ];
    }
}
```

### d) Policy registration (DomainServiceProvider::boot)

```php
use Illuminate\Support\Facades\Gate;
use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Policies\PostPolicy;

public function boot()
{
    Gate::policy(Post::class, PostPolicy::class);
}
```

### e) Use in controller

```php
public function update(UpdatePostRequest $request, Post $post)
{
    $this->authorize('update', $post);
    // or UpdatePostRequest::authorize() returns true only if user can update
}
```

### f) Custom Rule location

- Put custom reusable validation rules in `app/Rules/` or `App/Domain/{Domain}/Rules/`.

---

