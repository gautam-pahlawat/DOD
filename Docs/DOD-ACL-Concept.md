# **<p align="center">Access Controlling System (ACL) with DOD Structure - Concept</p>**

> این سند دقیق و کاربردی توضیح می‌دهد چطور دامنهٔ Security (ACL) را طوری پیاده‌سازی و مصرف کنیم که به‌عنوان منطق مرجعِ تصمیم‌گیری‌های authorization (policies) در معماری Domain‑Oriented Design عمل کند. در این سند تمام تمرکز بر منطق ACL، قراردادها (interface)، ارتباطات درون دامنه و بین دامنه‌ها، و جریان کامل درخواست از زمان دریافت و تحویل به route تا صدور پاسخ است.

---

## مقدمهٔ خیلی کوتاه و شفاف
- **هدف:** **Security domain** مرجعِ تصمیمات authorization باشد. هیچ دامنهٔ دیگری نباید مستقیماً کوئری‌های authorization را روی جداول ACL اجرا کند یا منطقِ تصمیم‌گیری را پراکنده کند.  
- **راهکار عملی:** یک **Contract (interface)** قابل‌اعتماد و پایدار تعریف کن، پیاده‌سازی بهینه (مثلاً CachedAuthorizationService) داخل domain بنویس، و policyها/فرم‑ریکوئست‌ها/اکشن‌های سایر دامنه‌ها فقط از آن interface استفاده کنند.  
- **قاعدهٔ ایمن:** **fail-closed** — اگر سرویس authorization در دسترس نباشد، مجوز نده (به‌جز موارد خاص که واضحاً document شده).
- **مالکیت داده و منطق ACL متعلق به `Domain/Security` است.** سایر دامنه‌ها فقط از طریق قرارداد (interface/service) به آن رجوع کنند و هرگونه تغییر در ACL فقط از طریق Actions یا Services همان دامنه انجام شود. وابستگی یک‌طرفه باشد: Consumer → Security (Interface).
- **به عبارت دیگر:** *اجازه دهید دادهٔ ACL متمرکز بماند، اما دسترسی به آن از طریق یک Service/Contract رسمی و بهینه‌شده صورت گیرد.*

---

## چرا؟ (دلایل فنی)
1. **یک منبع حقیقت (single source of truth)** برای سطوح دسترسی لازم است تا سیاست‌ها (policies) در هرجا یک‌صدا تصمیم بگیرند.  
2. **امنیت**: نگهداری جدول‌های ACL در یک Domain مشخص باعث می‌شود ثبت تغییرات (audit), invalidation cache و policies متمرکز شوند.  
3. **قابلیت تست و traceability**: وقتی checks از طریق service انجام شوند، می‌توانآن‌ها را mock، unit test و trace کرد.  
4. **اجتناب از تکرار**: اگر هر Domain خودش کوئری‌های متفاوت و پراکنده برای permissions بنویسد، احتمال خطا و ناسازگاری زیاد می‌شود.  

---


## چطور؟ (به‌صورت کلی)
1. **دامنه احرازکننده :** شامل مجموعهٔ internal services است که می‌داند «چه کسی چه کاری می‌تواند انجام دهد». این domain یک یا چند interface تعریف می‌کند (مثلاً `AuthorizationServiceInterface`) و پیاده‌سازی‌هایش را پوشش می‌دهد (درون‌حافظه، کش‌شده، یا محاسبه‌شونده).  
2. سایر دامنه‌ها **تنها** به آن interface وابسته می‌شوند (constructor injection در policy یا action). آن‌ها **حق ندارند** از جدول‌های ACL مستقیم بخوانند یا آن‌ها را تغییر دهند. تغییرات باید توسط Actions یا APIهای داخل Security domain انجام شوند.  
3. Security domain مسئول cache, invalidation, auditing، و محاسبات context‑aware است. سایر دامنه‌ها مسئول ارزیابی use‑case‑level (مثلاً 'آیا کاربر X می‌تواند پست را update کند؟') با ارسال context مناسب به `check()`.




---

## اصول کلیدی که باید رعایت شوند (بدون این‌ها نرو جلو)
1. **وابستگی‌ها را یک‌طرفه نگه دار** — مصرف‌کننده → ارائه‌دهنده.  
2. **تعاریف قراردادی (Interface / Contract)** بین Domainها داشته باش (نه دسترسی مستقیم به مدل‌ها در هر کجا).  
3. **Policyها نقش قضاوت نهایی را دارند، اما نباید خودشان DB-heavy کوئری کنند** — آن‌ها باید از یک service یا repository استفاده کنند.  
4. **UI-level checks فقط برای UX**؛ امنیت واقعی باید در Policy/Request/Controller انجام شود.  
5. **کشینگ permissions** را برای performance پیاده کن و در تغییرات ACL cache را invalid کن.  
6. **Audit و logging** برای هر تغییر دسترسی و هر ردّ صلاحیت (authorization denial) گزارش گیری داشته باش.

---

## ساختار پیشنهادی پوشه‌ها 
دامنهٔ Security باید دقیقاً همان زیرساخت را داشته باشد تا سازگاری و انتظارات تیمی حفظ شود:

```
app/
 └── Domain/
      └── Security/
           ├── Actions/
           │    ├── AssignRoleAction.php
           │    ├── RevokeRoleAction.php
           │    └── UpdatePermissionAction.php
           ├── Http/
           │    ├── Controllers/
           │    │    ├── Admin/
           │    │    │    └── RoleController.php
           │    │    └── Api/
           │    │         └── PermissionController.php
           │    ├── Requests/
           │    │    └── StorePermissionRequest.php
           │    └── Resources/
           │         └── PermissionResource.php
           ├── Models/
           │    ├── Role.php
           │    ├── Permission.php
           │    ├── UserPermissionLimit.php
           │    └── BlockedUser.php
           ├── Policies/
           │    └── PermissionPolicy.php
           ├── Providers/
           │    └── SecurityServiceProvider.php
           ├── Routes/
           │    └── web.php
           └── Contracts/
                └── AuthorizationServiceInterface.php
```

> توضیح: پوشهٔ `Contracts` برای قراردادها/اینترفیس‌ها اضافه شده است. این هم‌راستا با سبک DOD است — چون سایر دامنه‌ها نیاز به واردات اینترفیس خواهند داشت. اگر می‌خواهی قراردادها را در یک مکان مشترک‌تر نگه دارید (مثلاً `app/Domain/Contracts`) هم می‌توانی؛ اما نگه داشتنش داخل `Domain/Security/Contracts` مالکیت را شفاف‌تر می‌کند.

---

## طرح جدول‌های پیشنهادی 

|   |       Table       | Columns | index |             note            |
|:-:|:-----------------:|:-------:|:-----:|:---------------------------:|
| 1 |       roles       | `id`, `name` (unique), `guard_name`, `description`, `created_at`, `updated_at` | `name` unique |  |
| 2 |    permissions    | `id`, `name` (unique), `action` (e.g., `'post.update'`), `resource` (nullable), `description`, `created_at`, `updated_at` | `name`, `action` |  |
| 3 |  role_permission  | `role_id`, `permission_id`, `created_at` | composite PK or unique constraint (role_id, permission_id) | pivot |
| 4 |     user_role     | `user_id`, `role_id`, `expires_at` (nullable) | `user_id` |            pivot            |
| 5 |  user_permissions | `id`, `user_id`, `permission_id`, `allowed` (boolean), `context_json` (json nullable), `created_at` |  | optional per-user overrides |
| 6 | permission_limits | `id`, `permission_id`, `limit_type` (e.g., monthly_count), `limit_value`, `created_at` |  |  |
| 7 |   blocked_users   | `user_id`, `reason`, `blocked_until`, `created_at`  |  |  |

---

## پیاده‌سازی (نحوه عملی و نکات مهم)

### الف) تعریف Contract (مهم)
- قرار دادن این interface در یک مکان مشترک (مثلاً `app/Domain/Security/Contracts` یا اگر strict separation می‌خواهی در `app/Common/Contracts`) تا سایر domainها فقط به interface وابسته باشند و نه به پیاده‌سازی.
- متدهای پیشنهادی:

```php
public function check(User $user, string $ability, array $context = []): bool;
```
```php
public function getAll(User $user): array;
```
```php
public function hasRole(User $user, string $role): bool;
```
```php
public function invalidate(User $user): void;
```

**چرا مهم است؟** این کار جلوی circular dependencies را می‌گیرد و سرویس امنیت را قابل mock و تست می‌کند.

### ب) پیاده‌سازی Service بهینه
- **وظیفه `CachedAuthorizationService` :** گرفتن permissions از DB یا cache، محاسبات rules/constraints، و برگرداندن bool برای check.  
- **نکته عملیات‌پذیری:** اگر نیاز به context (مثلاً asset id یا domain id) هست، آن را به متد check پاس بده تا rules context-aware باشند (مثلاً permission فقط برای asset متعلق به org X اعمال شود).

### ج) استفاده در Policyها
- Policy constructor:
```php
public function __construct(AuthorizationServiceInterface $authService) { $this->auth = $authService; }
```
- policy Method:
```php
public function update(User $user, Post $post) { return $this->auth->check($user, 'post.update', ['post' => $post]); }
```
- **نکته :** Laravel container خودش policy class را می‌سازد و dependencyها را تزریق می‌کند. مستندات authorization توضیح داده‌اند که policyها کلاس هستند و می‌توان آن‌ها را رجیستر و استفاده کرد.

### د) UI (استفاده از Inertia)
- **سمت سرور :** هر endpoint یا FormRequest باید `authorize()` را چک کند (یا در controller قبل از اجرای action از کد زیر استفاده شود). این امنیت منطقی را تضمین می‌کند.
```php
$this->authorize(...)
```
- **سمت کلاینت :** قبل از render لینک یا دکمه از `@can` یا prop که توسط server-side page payload فرستاده شده استفاده کن. یعنی برای هر صفحه Inertia، server یک مجموعهٔ minimal از permissions برای کاربر ارسال کند (مثال زیر) تا UI تصمیم بگیرد. این مجموعه باید از AuthorizationService گرفته شود و کش نشود تا stale نباشد.
```js
can:['post.create' => true, 'asset.view' => false]
```

### ه) Cache invalidation
- وقتی permission/role تغییر می‌کند:
  - از event استفاده کن که cache entry مربوط به کاربران متاثر را بسوزاند.  
  - یا از تغییر در DB timestamp/version استفاده کن و وقتی client requests payload می‌گیرد، این نسخه را بررسی و در صورت عدم تطابق cache محلی را refresh کن.

### و) Performance concerns
- **اجتناب از N+1 queries مثل**: `pre-load relations` - `use eager-loading in service`.  
- **Policy calls ممکن است چند بار در یک درخواست اجرا شوند** (مثلاً UI و سپس action) — از memoization (در داخل request) استفاده کن تا برای هر user یک بار permission set خوانده شود. به این منظور Laravel cache memo driver مفید است. 

---

## جریان داده: وقتی کاربر درخواستی می‌فرستد که نیاز به اعتبارسنجی دارد (Request → Response)

### **مرحله اول : Route درخواست را دریافت میکند**  
   - درخواست وارد لاراول می‌شود، route انتخاب و middleware (=auth, throttle, etc.) اجرا می‌شود. (اینها را درمی‌گذریم؛ فرض می‌کنیم user authenticated است یا هویت معلوم شده.)

### **مرحله دوم: کنترلر (یا اکشن) وارد عمل میشوند**  
   - کنترلر یا action مربوطه orchestration انجام می‌دهد. قبل از اجرای منطق حساس، باید authorization انجام شود. دو مسیر معمول وجود دارد:  
     - الف : **FormRequest.authorize()** اجرا می‌شود (اگر از FormRequest استفاده شده).  
     - ب : **Controller** با `$this->authorize()` یا با صدا زدن policy متد مربوطه، اجرا می‌کند.

### **مرحله سوم: Policy فراخوانده میشود**  
   - در این مرحله policy (که متعلق به همان domain‌ی است که use‑case را اجرا می‌کند) ساخته می‌شود توسط container. در constructor آن، `AuthorizationServiceInterface` تزریق شده است. (یعنی policy نمی‌داند پیاده‌سازی چگونه است؛ فقط می‌داند interface را دارد.)

### **مرحله چهارم: Policy برسی را به دامنه امنیت واگذار میکند**  
   - در policy فراخوانی انجام میشود:  
     ```php
     $this->auth->check($user, 'ability.name', ['resource' => $resource, 'extra' => $value]);
     ```  
   - **توجه مهم:** policy مسئول ساخت context (آنچه برای تصمیم نیاز است) است؛ منطق قضاوت واقعی داخل AuthorizationService است.

### **مرحله پنجم: دامنه امنیت cache را برسی میکند**  
   - در این مرحله CachedAuthorizationService ابتدا دنبال cached permission set برای user (یا batch checks) می‌گردد.
     - اگر cache hit : نتایج برگشت داده می‌شود.
     -  اگر cache miss:  آنگاه internal evaluator فراخوانی می‌شود تا مجموعهٔ permissionها محاسبه شود (با ترکیب نقش‌ها، per-user overrides، محدودیت‌ها و rulesِ context‑aware). سپس نتیجه در cache ذخیره می‌شود.

### **مرحله ششم: دامنه امنیت درخواست دسترسی را ارزیابی میکند**  
   - در این مرحله هنگام evaluation کردن دامنه امنیت می‌تواند قوانین پیچیده را اعمال کند: نقش‌ها، overrideها، محدودیت‌های زمانی/تخصیصی، و شرایط resource-specific (مثلاً owner_id match، product status).
   - خروجی: boolean (یا در برخی حالات، structured result برای explainability: allowed, reason, metadata).

### **مرحله هفتم: Policy نتیجه را به کنترلر برمیگرداند**  
   - در این مرحلهpolicy مقدار boolean را برمی‌گرداند. controller بر اساس آن ادامه می‌دهد یا abort(403) می‌کند.

### **مرحله هشتم: پاسخ ارسال میشود**  
   - کنترلر در صورت موفقیت logic را اجرا و پاسخ را ارسال می‌کند. UI می‌تواند از همین الگو برای show/hide استفاده کند اما باید همیشه server-side check را نیز انجام دهد.

**نکتهٔ اجرایی:** هر چه context دقیق‌تر فرستاده شود (IDs، owner, tenant, action metadata)، تصمیم دقیق‌تر و امن‌تر خواهد بود.

---

## جریان منطق: شرح داخلی Security domain و نحوهٔ عملکرد اجزا در مقابل سایر domainها

> اجزا و مسئولیت‌شان (داخل Security domain، خلاصه و عملی)
### الف) **Contracts (Interfaces)**  
  - تعریف مجموعهٔ متدهای قابل اعتماد که سایر domainها با آن صحبت می‌کنند (`check`, `getPermissionsFor`, `getPermissionMapFor`, `invalidateUserCache`).  
  - قراردادها باید فقط نوع‌ها و تعاریفِ pure‑behavior را مشخص کنند؛ جزئیات پیاده‌سازی نباید در interface باشد.

### ب) **AuthorizationService (Eloquent + Cached variants)**  
  - کارکرد `EloquentAuthorizationService`: محاسباتِ canonical؛ بدون cache؛ ترکیب role/permission/overrides و اعمال constraints.  
  - کارکرد `CachedAuthorizationService`: wrapper که cache read/write انجام می‌دهد و در cache miss به Eloquent می‌سپارد. همچنین شامل memoization در طول یک request است.

### ج) **Actions (mutations)**  
  - عملیات تغییر ACL (assign role, revoke role, set per-user override) با transaction اجرا شوند و در پایان event مربوطه را dispatch کنند (e.g. `PermissionsChanged`). Actions مسئول تولید atomic changes و emit event هستند.

### د) **Listeners**  
  - کارکرد `InvalidatePermissionCache` listener برای شنیدن eventها و پاک‌سازی/افزایش version cache. این listener معمولاً queueable است.

### ه) **Policy adapters**  
  - در واقع Policyهای سایر دامنه‌ها که interface را consume می‌کنند؛ آن‌ها context می‌سازند و delegate می‌کنند.

### و) تعاملات در مقابل سایر domainها
- **خواندن**: تمام خواندنها از طریق interface انجام می‌شود.  
```bash
consuming domain → policy → AuthorizationServiceInterface → cached service → (maybe) Eloquent evaluator → return

```
- **نوشتن** (تغییر ACL): فقط از طریق Actions داخل Security domain انجام شود؛ consuming domains نمی‌توانند مستقیم mutate کنند. اگر مدت زمان عملیات طولانی است، action می‌تواند sync یا queued باشد؛ اما همواره event انشار یابد تا cache invalidation انجام شود.  
- **خطاها و fallback**: اگر authorization service unavailable (مثلاً Redis down)، رفتار پیش‌فرض باید deny (fail‑closed). اما برای بعضی flows که نیاز به availability بالا دارند، می‌توان fallback به Eloquent evaluator با degraded performance داشت؛ این fallback باید قابل پیکربندی باشد.

---

## توضیح کامل و کاربردی دربارهٔ Interface:

### چرا interface مهم است (به‌زبان ساده)
> در واقع interface حکم «قرارداد رسمیِ ارتباط» بین Security domain و بقیه را دارد. با یک interface، می‌توان پیاده‌سازی‌ها را تغییر داد (مثلاً switch to external auth service)، بدون اینکه سایر دامنه‌ها تغییر کنند.

### توصیه‌های دقیق برای طراحی interface
1. **کوچک و مشخص نگهش دار**: متدها نباید بیش از حد general باشند. نمونه متدهای پیشنهادی:  
```php
public function check(User $user, string $ability, array $context = []): bool;
```
```php
public function getPermissionsFor(User $user): array;
```
```php
public function getPermissionMapFor(User $user, array $abilities): array;
```
```php
public function invalidateUserCache(int $userId): void;
``` 
2. **نوع‌دهی (type-hinting) صریح**: در PHP 8 از نوع‌های scalar و object استفاده کن، و کد زیر. همیشه return types مشخص کن.  
```php
declare(strict_types=1);
``` 
3. **طراحی Context-aware**: پارامتر context باید آرایه‌ای با کلیدهای مستند باشد؛ هر کلید معنی مشخص داشته باشد (مثلاً `resource_id`, `tenant_id`). مستندسازی این keys در ریدمی الزامی است.  
4. **متدهای Batch-friendly**: متدهایی برای چندچک همزمان (`getPermissionMapFor`) قرار بده تا برای UI و صفحات که نیاز به چندین can-check دارند، کارآیی بالا برود.  
5. **تعیین واضخ Side-effect ها**: نباید interface عملیات mutate انجام دهد (مثل assign role) — اینها Actionهای جدا هستند. interface صرفاً برای خواندن/ارزیابی باشد؛ mutate باید از Actions باشد.  
6. **مدیریت خطا**: باید interface مشخص کند در صورت خطای داخلی آیا exception پرتاب می‌شود یا false برگردانده می‌شود؛ پیشنهادمان: در حالت عادی boolean برگردان، ولی خطاهای سیستمی با استثنا (e.g. AuthorizationServiceUnavailableException) مشخص شوند تا caller بتواند آن‌ها را لاگ/متریک کند و مطابق policy رفتار کند (معمولاً deny).

### نسخه‌بندی interface
- وقتی interface تغییر می‌کند، از semantic versioning و adapter pattern استفاده کن. اگر تغییر backward‑incompatible لازم است، یک interface جدید منتشر کن (مثلاً `AuthorizationServiceInterfaceV2`) و پیاده‌سازی‌های قدیمی را همچنان support کن تا هنگام refactor domainها فرصت داشته باشند.

### محل قرارگیری و autoloading
- فایل‌ها تحت `app/Domain/Security/Contracts` قرار گیرند؛ composer پیکربندی موجود PSR‑4 این را load می‌کند. مستندسازی namespace و مسیرها ضروری است.



---

## سناریوی جایگزین
اگر به هر دلیل نمی‌خواهی مدل‌ها یا Domain/Security را وابسته کنی، گزینه‌های جایگزین:

|                  معایب                  |                          مزیت                         | توضیح |                   نام روش                   |
|:---------------------------------------:|:-----------------------------------------------------:|:-----:|:-------------------------------------------:|
|           eventual consistency          |                      performance                      |   <p align="center">یک جدول کش read-only یا materialized view برای permissions بساز که هر X دقیقه sync می‌شود یا با events به‌روزرسانی می‌شود.</p>   | Replication read-only (copy of permissions) |
| latency، need to retry/timeout، network | مناسب در معماری میکروسرویس یا در صورت وجود تیم‌های جدا |   <p align="center">ساختن یک internal HTTP/gRPC service در Security domain که سایر دامنه‌ها برای تصمیم‌گیری authorization به آن درخواست می‌زنند.</p>   |        internal API for authorization       |
|               مرکزی‌تر است               |                شبیه الگوی توصیه‌شده است                |   <p align="center">در AppServiceProvider یا AuthServiceProvider، تعریف gateهایی که delegate می‌کنند به SecurityService</p>   |               Delegated Gates               |

---

**<p align="center">[let's go to implement ](DOD-ACL-Implementation.md)</p>**