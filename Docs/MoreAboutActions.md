# Action in Domain-Oriented (DOD) — راهنمای سریع و کاربردی

این فایل کوتاه، عملی و بدون حاشیه توضیح می‌دهد که **Action** در معماری Domain‑Oriented (DOD) چه کار می‌کند، کجا قرار می‌گیرد، و چطور با اجزای لاراول (Model, Controller, Request, Resource, Policy, Route و...) تعامل می‌کند. این راهنما برای برنامه‌نویسان لاراولی نوشته شده که می‌خواهند کد خواناتر، قابل تست‌تر و توسعه‌پذیرتری بسازند.

---

## خلاصه در ۲ خط (اگر عجله داری)
**اکشن (Action):** کلاسِ واحدی است که یک use‑case یا یک واحد از منطق کسب‌وکار را اجرا می‌کند.  
قرار است کنترلرها «باریک» (thin) و خوانا بمانند؛ کنترلر فقط داده‌ها را آماده می‌کند و نتیجه را از Action می‌گیرد.

---

## نقش Action — چرا لازم است؟
- تفکیک وظایف: منطق کسب‌وکار از لایهٔ HTTP جدا می‌شود.  
- تست‌پذیری: Actionها را بدون نیاز به HTTP یا framework می‌توان unit تست کرد.  
- بازاستفاده: همان Action می‌تواند از CLI, Jobs, Events یا Controller فراخوانی شود.  
- خوانایی: هر Action یک وظیفهٔ مشخص (مثلاً `CreatePost`, `PublishPost`) دارد و کوتاه نگه داشته می‌شود.

---

## محل قرار‌گیری و نامگذاری (convention)
```
app/
└── Domain/
    └── {DomainName}/
        ├── Actions/
        │   ├── CreatePost.php
        │   └── PublishPost.php
        ├── Models/
        ├── Http/
        │   ├── Controllers/
        │   ├── Resources/
        │   └── Requests/
        └── Policies/
```
- namespace: `App\Domain\{Domain}\Actions`  
- فایل‌ها: اسم کلاس باید توضیحی و فعل‌محور باشد یا اگر ترجیح می‌دهی پسوند اضافه کن مثل موارد زیر
-  `CreatePost`, `UpdateUser`, `DeleteComment`, `CreatePostAction`
---

## ساختار نمونه (Invokable — روش پیشنهادی)
```php
namespace App\Domain\Blog\Actions;

use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Http\Requests\StorePostRequest;

class CreatePost
{
    public function __invoke(StorePostRequest $request): Post
    {
        // validate/transform request already done by FormRequest
        $data = $request->validated();

        // business logic: could call repositories, services, etc.
        $post = Post::create($data);

        return $post;
    }
}
```
- اگر از DTO استفاده می‌کنی، متد `__invoke(CreatePostDto $dto)` بنویس.  
- Invokable ساده است، مناسب وقتی فقط یک متد نیاز داری.

## ساختار نمونه (non-invokable)
```php
class CreatePostAction
{
    public function handle(array $data): Post
    {
        // logic
    }
}
```

تابع پیشفرض (`handle`) یا متد دلخواه مشخص کن. این روش وقتی نیاز به چند متد کمکی یا DI پیچیده داری مناسب است.

---

## نحوه ارتباط با بقیه اجزا

### Controller
- کنترلر فقط مسئول آماده‌سازی ورودی (مثلاً گرفتن `FormRequest` و تبدیل به DTO) و فراخوانی Action است.
```php
public function store(StorePostRequest $request, CreatePost $action)
{
    $post = $action($request);
    return new PostResource($post);
}
```

### Request (FormRequest)
- وظیفهٔ اعتبارسنجی و authorize را دارد
- نکته : تبدیل `FormRequest` به DTO در کنترلر یا یک small mapper انجام شود. Action نباید کار اعتبارسنجی را انجام دهد بلکه ورودی آماده را تحویل میگیرد و.

### Model
- تعامل مدل با Action : از مدل برای دسترسی به داده استفاده می‌کند (یا از repository در صورت استفاده از abstraction).  
- اگر منطقی است، اکشن  تغییرات را روی مدل انجام می‌دهد و مدل را برمی‌گرداند.

### Resource (API Resource)
- اکشن خروجی را می‌دهد (معمولاً مدل یا مجموعه‌ای از مدل‌ها). Controller آن را در `Resource` پیچ می‌کند:
```php
return new PostResource($post);
```

### Policy / Authorization
دو رویکرد:
1. رویکرد اول : Authorization در Controller با استفاده از Policies:
```php
$this->authorize('create', Post::class);
```
2. رویکرد دوم : Authorization در خود Action (اگر می‌خواهی منطق دسترسی نزدیک به use-case باشد):
```php
if (! Gate::allows('create', Post::class)) {
    throw new AuthorizationException;
}
```
- معمولاً توصیه می‌شود authorize را در Controller انجام دهی تا Action ساده باقی بماند؛ اما برای reuse داخل CLI یا Jobs، Action می‌تواند خودش authorize کند یا یک پارامتر `Actor` دریافت نماید.

### Route
- تعامل : Route به Controller اشاره می‌کند؛ یا مستقیماً به Action (invokable) اشاره کن:
```php
// controller
Route::post('posts', [PostController::class, 'store']);

// invokable action directly
Route::post('posts', CreatePost::class);
```

### Jobs / Queues
- اگر Action باید در صف اجرا شود، scaffold کردن یک Job که Action را اجرا می‌کند منطقی است. Action باید pure-ish باشد تا قابل فراخوانی در هر محیط باشد.

---

## قواعد عملی و بهترین شیوه‌ها (بدون شعاری‌گری)
1. **یک Action = یک use case**. کوچک و دقیق نگهش دار.  
2. **Side-effects ترتیب‌مند**: هر چیزی که بیرون از سیستم است (IO, mail, external API) در boundary انجام شود یا با service/adapter جداگانه فراخوانی شود.  
3. **Transaction**: اگر action چند عملیات دیتابیسی مرتبط دارد، داخل تراکنش بگیر (`DB::transaction`) — تنها اگر لازم است.  
4. **DI قابل تست**: وابستگی‌ها (repository, mailer) را از constructor بگیر تا به سادگی mock کنی.  
5. **خطاها را پرتاب کن**: به جای `return false` خطا مشخص (Exceptions) پرتاب کن. این باعث می‌شود در controller یا middleware بتوان خطاها را یک‌جا هندل کرد.  
6. **فرمت خروجی**: Action مدل/DTO برگرداند، نه Response. Controller خروجی را به Resource یا پاسخ HTTP تبدیل کند.  
7. **لاگ و Metrics**: در Action نقاط کلیدی را لاگ کن، اما از پر کردن با لاگ‌های بی‌ربط خودداری کن.  
8. **تست واحدی**: برای Actionها تست واحد بنویس بدون نیاز به boot لاراول (mock repository, assert on returned entity).

---

## مثال ساده جریان کامل (store)
1. Request: `StorePostRequest` — اعتبارسنجی ورودی  
2. Route: `POST /posts` → `PostController@store`  
3. Controller: `$post = $createPostAction($request); return new PostResource($post);`  
4. Action: دریافت داده، منطق (repository/model) -> برگرداندن مدل  
5. Policy: قبل از فراخوانی Action، `authorize('create', Post::class)` در کنترلر فراخوانی شود.  

---

## نکات عملی برای تیم و نگهداری
- یک : **Stubs**: برای consistency از stub برای Actionها استفاده کن (invokable, handle).  
- دو : **Naming**: از فعل/اسم واضح استفاده کن (`Create`, `Update`, `Publish`, `Archive`).  
- سه : **Docs**: هر Action بالای کلاس یک توضیح کوتاه بنویس: چه می‌کند، ورودی مورد انتظار، خروجی.  
- چهار : **DRY**: منطق shared را به Services یا Repositories منتقل کن، نه اینکه Actionها را شلوغ کنی.  

---

## نتیجه‌گیری (به زبان آدمیزاد)
اکشن (Action) یعنی «جایی که کار واقعی انجام می‌شود». کنترلر در نقش رابط HTTP بماند، Requestها اعتبارسنجی کنند، Resourceها خروجی را شکل دهند، و Action مسئول منطق کسب‌وکار باشد — این ترکیب پروژه را قابل تست، قابل نگهداری و خوانا می‌کند. ساده و قابل اعتماد: همین کافیه ;)

---

Developed by [Hadi HassanZadeh]  