# 🧩 Laravel Domain Request Generator (`make:d-request`)

A production-ready custom Artisan command to generate **FormRequest** classes inside a Domain-oriented Laravel project (DOD).  
This command creates domain-scoped requests under `app/Domain/{Domain}/Http/Requests` and supports creating `Store` and `Update` requests automatically.

---

## 📦 Command Overview (Usage)

```bash
php artisan make:d-request {Name?} --domain={DomainName} [options]
```

- `Name` (optional): the base name or a specific request class. If omitted, `--model` must be provided.
- If `Name` does not end with `Request`, the command appends `Request` to the final class names according to conventions.

### Short behaviour summary
- If `Name` starts with `Store` or `Update`, only that specific request class will be created (e.g. `StorePostRequest`).  
- Otherwise, the command creates **both** `Store{Name}Request` and `Update{Name}Request` (e.g. `StorePostRequest` and `UpdatePostRequest`).  
- If `--model` is supplied, the base name is taken from the model's class basename (FQCN or short name).

---

## ⚙️ Options

| Option | Alias | Description |
|--------|-------|-------------|
| `--domain=` |  | **(Required)** Target domain folder where requests will be created (`app/Domain/{Domain}/Http/Requests`). |
| `--model=` |  | Optional model (FQCN or short) to derive the base name (e.g. `Post`). |
| `--force` | `-f` | Overwrite existing request files if they exist. |

---

## 🧾 Examples

### 1) Create Store+Update requests from a name
```bash
php artisan make:d-request ContactForm --domain=Test
```
Creates:
```
app/Domain/Test/Http/Requests/StoreContactFormRequest.php
app/Domain/Test/Http/Requests/UpdateContactFormRequest.php
```

### 2) Create only a specific Store request
```bash
php artisan make:d-request StoreContactFormRequest --domain=Test
```
Creates only `StoreContactFormRequest.php`.

### 3) Create requests from a model name
```bash
php artisan make:d-request --model=Post --domain=Blog
```
Creates:
```
app/Domain/Blog/Http/Requests/StorePostRequest.php
app/Domain/Blog/Http/Requests/UpdatePostRequest.php
```

### 4) Overwrite existing files
```bash
php artisan make:d-request ContactForm --domain=Test --force
```

---

## 💡 نکات مهم (Implementation notes)

- The command prefers a project-level stub `stubs/form-request.stub` if present, then falls back to Laravel vendor stub. If none exists, a minimal inline template is used.  
- Created files use namespace: `App\Domain\{Domain}\Http\Requests`.  
- The command validates domain name characters and prevents path traversal in names.  
- If any file write fails, the command rolls back files created during the run to avoid partial state.  
- Messages are printed with clear status (info, comment, warn, error).

- گزینه `--domain` **الزامی** است؛ قبل از اجرا مطمئن شوید شاخه `app/Domain/{Domain}` وجود دارد.  
- اگر `Name` را وارد کنید و آن با `Store` یا `Update` شروع کند، فقط همان فایل ساخته می‌شود.  
- در غیر این صورت دو فایل `Store{Name}Request` و `Update{Name}Request` ساخته می‌شوند.  
- اگر فقط `--model` را بدید (بدون نام)، نام پایه از مدل استخراج می‌شود و دو فایل ساخته خواهد شد.  
- اگر فایلی از قبل وجود داشته باشد و `--force` را نزنید، آن فایل را skip می‌کند و پیغام می‌دهد.  
- فایل‌های ساخته‌شده توسط این دستور به‌صورت آماده برای پر کردن `authorize()` و `rules()` هستند.

---

## ✅ Test commands (comprehensive)

Run these to test different scenarios:

```bash
# 1) Create Store+Update from name
php artisan make:d-request ContactForm --domain=Test

# 2) Create only Store (explicit name)
php artisan make:d-request StoreContactFormRequest --domain=Test

# 3) Create only Update (explicit name)
php artisan make:d-request UpdateContactFormRequest --domain=Test

# 4) Create from model name (no 'name' argument)
php artisan make:d-request --model=Post --domain=Blog

# 5) Force overwrite existing requests
php artisan make:d-request ContactForm --domain=Test --force

# 6) Expect error: missing domain
php artisan make:d-request ContactForm
```

---

## ⚠️ Common troubleshooting

- **"Domain directory not found"** — create the domain folder first: `mkdir -p app/Domain/Test` (or use your OS equivalent).  
- **"Invalid domain name"** — only letters, numbers, `_` and `-` are allowed.  
- **Partial creation (unexpected)** — this command rolls back on write errors; if you see partial files, check filesystem permissions.

---

## 🧰 Requirements & compatibility

- Laravel 10/11/12 compatible (uses no breaking framework internals)  
- PHP 8.1+ recommended  
- Place the command class in `app/Console/Commands` and register it in `app/Console/Kernel.php` if not auto-discovered.

---

## ✨ Credits & Notes

Developed by [Hadi HassanZadeh]  
Inspired by Laravel’s `make:request` command, extended for Domain-Oriented Design.

---
