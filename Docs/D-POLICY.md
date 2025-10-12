# 🧩 Laravel Domain Policy Generator (`make:d-policy`)

A production-ready custom Artisan command to generate **Policy** classes inside a Domain-oriented Laravel project (DOD).  
This command creates domain-scoped policies under `app/Domain/{Domain}/Policies` and optionally scaffolds model-aware methods.

---

## 📦 Command Overview (Usage)

```bash
php artisan make:d-policy {Name} --domain={DomainName} [options]
```

- `Name` (required): policy class name or base name. If it doesn't end with `Policy`, the command will append `Policy` automatically.  
- If you pass `--model`, the command will scaffold methods with model type-hints and will suggest register entries for `AuthServiceProvider`.

### Short behaviour summary
- Ensures the domain folder exists: `app/Domain/{Domain}`.  
- Prefers project stub `stubs/policy.stub` if present, otherwise vendor stub, otherwise a minimal inline template.  
- Writes files atomically and rolls back on write-failure.  
- Validates inputs and prints clear CLI messages.  

---

## ⚙️ Options

| Option | Alias | Description |
|--------|-------|-------------|
| `--domain=` |  | **(Required)** Target domain folder where the policy will be created (`app/Domain/{Domain}/Policies`). |
| `--model=` |  | Optional model (FQCN or short) to scaffold methods and add imports (e.g. `Post` or `App\\Models\\Post`). |
| `--force` | `-f` | Overwrite existing policy file if it exists. |

---

## 🧾 Examples

### 1) Create a policy by full name
```bash
php artisan make:d-policy PostPolicy --domain=Blog
```
Creates:
```
app/Domain/Blog/Policies/PostPolicy.php
```

### 2) Create a policy by base name (Policy suffix added automatically)
```bash
php artisan make:d-policy Post --domain=Blog
```
Creates `PostPolicy.php` as above.

### 3) Create a policy scaffolded for a domain-local model
```bash
php artisan make:d-policy PostPolicy --domain=Blog --model=Post
```
- Adds `use App\\Domain\\Blog\\Models\\Post;` import in the policy (if model exists), and type-hints model parameters in methods.  
- Prints suggested registration line for `AuthServiceProvider`.

### 4) Force overwrite existing file
```bash
php artisan make:d-policy PostPolicy --domain=Blog --force
```

---

## 🔍 Implementation notes (concise)

- The command uses `GeneratorCommand` API but writes files directly (prefers project stubs then vendor).  
- Validates domain and name to avoid path traversal.  
- If the model path is not physically present at expected location, the command warns but still creates the policy.  
- Created policy includes common methods: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`.  
- If any file write fails, the command removes any files it created during the run to avoid partial state.

---

## 💡 نکات مهم (خلاصه و کاربردی)

- گزینه `--domain` **الزامی** است؛ قبل از اجرا مطمئن شوید شاخه `app/Domain/{Domain}` وجود دارد.  
- اگر نام شامل `Policy` نباشد، پسوند `Policy` اضافه می‌شود.  
- اگر `--model` بدهید، دستور سعی می‌کند import و type-hintها را قرار دهد؛ اگر فایل مدل در مسیر مورد انتظار نباشد هشدار می‌دهد ولی ایجاد policy را متوقف نمی‌کند.  
- پس از ایجاد، فراموش نکنید Policy را در `app/Providers/AuthServiceProvider.php` ثبت کنید (مثال در خروجی فرمان چاپ می‌شود).

---

## ✅ Test commands (recommended)

```bash
# 1) create policy by name
php artisan make:d-policy PostPolicy --domain=Test

# 2) create policy by base name (Policy suffix added automatically)
php artisan make:d-policy Post --domain=Test

# 3) create policy scaffolded for a model (domain-local)
php artisan make:d-policy PostPolicy --domain=Test --model=Post

# 4) provide FQCN model (external)
php artisan make:d-policy PostPolicy --domain=Test --model=App\\Models\\Post

# 5) overwrite existing file
php artisan make:d-policy PostPolicy --domain=Test --force

# 6) expect error: missing domain
php artisan make:d-policy PostPolicy
```

---

## ⚠️ Common troubleshooting

- **"Domain directory not found"** — create the domain folder first: `mkdir -p app/Domain/Test` (or use OS equivalent).  
- **"Invalid policy class name"** — only letters, numbers and underscore allowed (no path separators).  
- **Policy shows wrong imports** — check the provided `--model` value and whether the file exists at expected path.  
- **Partial creation** — this command rolls back on write errors; if you see partial files, check filesystem permissions and disk space.

---

## 🧰 Requirements & compatibility

- Laravel 10/11/12 compatible.  
- PHP 8.1+ recommended.  
- Place the command class in `app/Console/Commands` and register it in `app/Console/Kernel.php` if not auto-discovered.

---

## ✨ Credits & Notes

Developed by [Hadi HassanZadeh]  
Inspired by Laravel’s `make:policy` command, extended for Domain-Oriented Design.