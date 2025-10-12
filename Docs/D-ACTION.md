# 🧩 Laravel Domain Action Generator (`make:d-action`)


دستور: `php artisan make:d-action {Name} --domain={Domain} [options]`

این سند کامل و کاربردی توضیح می‌دهد که این دستور چه می‌سازد، چه آپشن‌هایی دارد، چطور در ساختار DOD (Domain-Oriented Design) کار می‌کند و چه نکاتی برای استفاده و توسعهٔ آن باید رعایت شود.
---

## چرا این دستور وجود دارد؟
هدف: سریع و سازگار با الگوی DOD، کلاس‌های «Action» را بسازد که منطق کسب‌وکار (use-case) را نگه دارند. Action جای مناسبی است برای قرار دادن منطق تجاری به‌طوری که قابل تست، قابل فراخوانی از کنترلر/Job/CLI و قابل نگهداری باشد. این دستور کمک می‌کند که تیم از ساختار یکسانی استفاده کند و موارد تکراری را خودکار بسازد (و نه بیشتر).

---

## محل قرارگیری فایل و نامگذاری
- مسیر تولید‌شده: `app/Domain/{Domain}/Actions/{Name}.php`
-  پیشنهادات برای اسم‌گذاری: فعل‌محور و خلاصه — `CreatePost`, `ImportUsers`, `PublishArticle` یا با پسوند `Action` اگر تیم دوست دارد (`CreatePostAction`). کد فعلی اختیار نام‌گذاری را به توسعه‌دهنده می‌دهد (عدم اجباری‌سازی پسوند Action).

---

## خروجی‌های اصلی دستور
- کلاس Action : `app/Domain/{Domain}/Actions/{Name}.php`
- محتوا: importهای لازم (model/request) و اسکلت متد (`__invoke` یا `handle` یا نام دلخواه).

---

## آپشن‌ها (کوتاه و دقیق)

| Option | Description(Fa) | Description(En) |
|--------|--------------|----------|
| `--domain=` | **(Required)**  نام دامنه؛ پوشه `app/Domain/{Domain}` باید وجود داشته باشد. | Domain name (app/Domain/{Domain}) |
| `-i, --invokable` |  کلاس invokable با متد `__invoke` بساز. | `Create an invokable action (use __invoke)` |
| `--model=Model` |  مدل مرتبط (قبول می‌کند FQCN یا اسم کوتاه). اسم کوتاه به `App\Domain\{Domain}\Models\{Model}` تبدیل می‌شود. | Optional model FQCN or short name to import and type-hint |
| `--request=NAME,auto` |  عمل import و type-hint فرم‌ریکوئست. اگر `auto` باشد، `Store{Base}Request` برای actionهای create inferred می‌شود. | Optional FormRequest name or 'auto' to infer Store{Base}Request |
| `--force` | بازنویسی فایل در صورت وجود. | Overwrite the model if it already exists. |

---

## مثال‌های کاربردی (دستورها)
```bash
# 1) Action پایه (non-invokable, handle)
php artisan make:d-action CreatePost --domain=Blog

# 2) Invokable با request و model و تست
php artisan make:d-action CreatePost --domain=Blog --invokable --model=Post --request=auto 

# 3) متد سفارشی و queued
php artisan make:d-action ImportUsers --domain=Admin --method=execute 

# 4) بازنویسی فایل
php artisan make:d-action CreatePost --domain=Blog --force
```

---

## جزئیات تولید کد (چه چیزی داخل کلاس قرار می‌گیرد)
- بخش importها: اگر `--model` یا `--request` داده شده باشند، `use` مربوطه به بالای فایل اضافه می‌شود (unique).
- بخش متد: اگر `--invokable`، `public function __invoke(RequestOrModel $param)` ایجاد می‌شود؛ در غیر این‌صورت متد `public function handle(...)` .
- بخش body: اسکلت متنی با توضیحات داخل (commented examples) برای تبدیل داده، استفاده از مدل، و return مقدار نوشته می‌شود — توسعه‌دهنده رابط نهایی را پر می‌کند.

---

## هماهنگی با بقیه اجزا در ساختار DOD (عملی و شفاف)
- تعامل با **Controller**: کنترلر باید thin باشد؛ وظیفهٔ آن: دریافت `FormRequest`، تبدیل به DTO (یا ارسال مستقیم)، authorize با Policy و فراخوانی Action. مثال:
```php
public function store(StorePostRequest $request, CreatePost $action)
{
    $this->authorize('create', Post::class);
    $post = $action($request);
    return new PostResource($post);
}
```
- تعامل با **Request**: اعتبارسنجی در FormRequest انجام می‌شود. Action نباید اعتبارسنجی کند (ورودی باید آماده باشد). در صورت استفاده از `--request=auto`، نام پیشنهادی ساخته یا استفاده می‌شود ولی فایل request ساخته نمی‌شود — این نقطه قابل توسعه است.
- تعامل با **Model**: اکشن مستقیماً با مدل کار می‌کند یا repository را تزریق می‌گیرد (بهتر). اگر `--model` به صورت کوتاه داده شود، command تنها import را اضافه می‌کند — وجود فیزیکی فایل مدل بررسی و در صورت عدم وجود تنها هشدار داده می‌شود.
- تعامل با **Policy**: عمل authorization معمولا در Controller انجام می‌شود. اگر Action در CLI/Job استفاده می‌شود و نیاز به authorization دارد، بهتر است داخل Action نیز Authorization یا پارامتر Actor قرار گیرد — تصمیم گیری بر عهده تیم شما است.
- تعامل با **Route**: این مورد معمولا به Controller اشاره دارد؛ می‌توان مستقیم به Action invokable هم اشاره کرد:
```php
Route::post('posts', CreatePost::class);
```

## مواردی که الان **انجام نمی‌شود** ولی می‌توان اضافه کرد (پیشنهادات آتی برای شما)
- خودکار ساختن FormRequest وقتی `--request=auto` انتخاب شود (الان فقط نام فرضی ایجاد می‌شود و هشدار داده می‌شود اگر فایل موجود نباشد). می‌توان این را اضافه کرد تا requestها هم خودکار ساخته شوند.
- ثبت خودکار Policy یا Route (شامل تغییر فایل‌های provider یا route group) — پیچیده است و بهتر میباشد که با تائید تیم انجام دهید.
- تولید DTOها/Result objects همراه با action برای strict typing و تست‌پذیری بالاتر.
- ثبت خودکار تست کامل‌تر (mocking repository) به‌جای smoke-test ساده.

---

Developed by [Hadi HassanZadeh]  