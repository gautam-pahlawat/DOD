<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;

/**
 * MakeDomainPolicy
 *
 * Production-ready Artisan command to generate Policy classes inside a Domain:
 *   app/Domain/{Domain}/Policies/{Name}Policy.php
 *
 * Features:
 * - Requires --domain (domain folder must exist).
 * - Ensures policy class name ends with "Policy".
 * - Optional --model to scaffold model-aware methods and imports.
 * - Prefers project-level stub: stubs/policy.stub, then vendor stub; falls back to inline template.
 * - Writes files atomically and rolls back on write-errors.
 * - Prints clear, human-friendly messages and guidance about registering the policy.
 *
 * Usage examples:
 *   php artisan make:d-policy PostPolicy --domain=Blog
 *   php artisan make:d-policy Post --domain=Blog --model=Post
 *   php artisan make:d-policy PostPolicy --domain=Blog --force
 *
 * Notes for maintainers:
 * - Do not redeclare $files property (parent GeneratorCommand already defines it).
 * - getStub() returns a valid stub path to satisfy GeneratorCommand signature.
 */
class MakeDomainPolicy extends GeneratorCommand
{
    protected $name = 'make:d-policy';
    protected $description = 'Create a Policy class inside a specific Domain (domain is required)';
    protected $type = 'Policy';

    /**
     * Constructor.
     *
     * We pass Filesystem to parent. Do NOT redeclare $files property (avoid type collision with parent).
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct($files);
    }

    /**
     * Execute the console command.
     *
     * High-level flow:
     *  - validate --domain
     *  - normalize class name (ensure "Policy" suffix)
     *  - prepare target path
     *  - optionally process --model (derive FQCN or domain model path)
     *  - write file(s) atomically; rollback on failure
     *  - print helpful guidance for registering the policy
     *
     * @return int
     */
    public function handle()
    {
        // 1) Validate domain
        $domainOption = $this->option('domain');
        if (! $domainOption) {
            $this->error('The --domain option is required. Example: --domain=Blog');
            return 1;
        }

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $domainOption)) {
            $this->error('Invalid domain name. Allowed chars: letters, numbers, underscore, dash.');
            return 1;
        }

        $domain = Str::studly($domainOption);
        $domainPath = app_path("Domain/{$domain}");
        if (! $this->files->isDirectory($domainPath)) {
            $this->error("Domain directory not found: app/Domain/{$domain}. Create it first.");
            return 1;
        }

        // 2) Determine policy class name
        $nameArg = trim((string) $this->argument('name'));
        if ($nameArg === '') {
            $this->error('You must provide a policy name (e.g. PostPolicy) or a base name (Post) plus --model.');
            return 1;
        }

        // Normalize class name and ensure Policy suffix
        $className = Str::studly($nameArg);
        $className = Str::endsWith($className, 'Policy') ? $className : $className . 'Policy';

        // Basic validation
        if (! preg_match('/^[A-Za-z0-9_]+$/', $className)) {
            $this->error("Invalid policy class name: {$className}");
            return 1;
        }

        // 3) Prepare model option (optional)
        $modelOption = $this->option('model');
        $modelFQCN = null;
        $modelBasename = null;
        if ($modelOption) {
            // If user provided FQCN (contains backslash or starts with App\), accept it.
            $modelOption = trim($modelOption);
            if (Str::startsWith($modelOption, '\\')) {
                $modelOption = ltrim($modelOption, '\\');
            }

            if (Str::contains($modelOption, '\\') || Str::startsWith($modelOption, 'App\\')) {
                // accept FQCN
                $modelFQCN = Str::start($modelOption, 'App\\');
                $modelBasename = class_basename($modelFQCN);
            } else {
                // assume domain-local model: App\Domain\{Domain}\Models\{Model}
                $modelBasename = Str::studly($modelOption);
                $modelFQCN = "App\\Domain\\{$domain}\\Models\\{$modelBasename}";
            }

            // Check existence (optional): warn if model file not found (but do not abort)
            $modelPath = base_path('app') . '/' . str_replace('\\', '/', Str::after($modelFQCN, 'App\\')) . '.php';
            if (! $this->files->exists($modelPath)) {
                $this->warn("Model class file not found at expected path: {$modelPath}. You may register or create the model later.");
            }
        }

        // 4) Prepare target path and ensure directory
        $qualified = $this->qualifyClass($className); // uses getDefaultNamespace
        $targetPath = $this->getPath($qualified);
        $dir = dirname($targetPath);

        if ($this->files->exists($targetPath) && ! $this->option('force')) {
            $this->error("Policy already exists at: {$targetPath}. Use --force to overwrite.");
            return 1;
        }

        if (! $this->files->isDirectory($dir)) {
            try {
                $this->files->makeDirectory($dir, 0755, true);
                $this->info("Created directory: {$dir}");
            } catch (\Throwable $e) {
                $this->error("Failed to create directory {$dir}: " . $e->getMessage());
                return 1;
            }
        }

        // 5) Build content (prefer project stub -> vendor stub -> inline)
        try {
            $content = $this->buildPolicyContent($domain, $className, $modelFQCN, $modelBasename);
        } catch (\Throwable $e) {
            $this->error("Failed to build policy content: " . $e->getMessage());
            return 1;
        }

        // 6) Write file atomically with rollback on failure
        $created = [];
        try {
            $this->files->put($targetPath, $content);
            $created[] = $targetPath;
            $this->info("Policy created: {$targetPath}");
        } catch (\Throwable $e) {
            // rollback
            foreach ($created as $f) {
                try { $this->files->delete($f); } catch (\Throwable $_) {}
            }
            $this->error("Failed to write policy file: " . $e->getMessage());
            return 1;
        }

        // 7) Guidance: remind developer to register policy in AuthServiceProvider
        $this->line('');
        $this->info('Next steps: register the policy in your AuthServiceProvider if needed.');
        $this->line('  In app/Providers/AuthServiceProvider.php add to $policies:');
        if ($modelFQCN) {
            $this->line("    {$modelFQCN}::class => App\\Domain\\{$domain}\\Policies\\{$className}::class,");
        } else {
            $this->line("    // Example: App\\Domain\\{$domain}\\Models\\Model::class => App\\Domain\\{$domain}\\Policies\\{$className}::class,");
        }
        $this->line('');
        $this->info('Or register dynamically using Gate::policy(...) in boot().');

        return 0;
    }

    /**
     * Default namespace for policies inside domain.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        $domain = Str::studly($this->option('domain') ?: 'Unknown');
        return "{$rootNamespace}\\Domain\\{$domain}\\Policies";
    }

    /**
     * Build policy content by preferring stubs:
     *  - project: stubs/policy.stub
     *  - vendor: vendor/laravel/framework/src/Illuminate/Auth/Console/stubs/policy.stub
     *  - fallback: inline minimal template
     *
     * The stub placeholders replaced: DummyNamespace, DummyClass
     * If $modelFQCN provided, the content will import the model and type-hint model parameters.
     *
     * @param string $domain
     * @param string $className
     * @param string|null $modelFQCN
     * @param string|null $modelBasename
     * @return string
     */
    protected function buildPolicyContent(string $domain, string $className, ?string $modelFQCN = null, ?string $modelBasename = null): string
    {
        $projectStub = base_path('stubs/policy.stub');
        if ($this->files->exists($projectStub)) {
            $stub = $this->files->get($projectStub);
            $ns = "App\\Domain\\{$domain}\\Policies";
            $stub = str_replace('DummyNamespace', $ns, $stub);
            $stub = str_replace('DummyClass', $className, $stub);

            // If model present, attempt replace occurrences of model placeholders (if any)
            if ($modelFQCN) {
                $stub = str_replace('DummyModel', $modelBasename, $stub);
                $stub = str_replace('DummyFullModel', $modelFQCN, $stub);
            }
            return $stub;
        }

        $vendorStub = base_path('vendor/laravel/framework/src/Illuminate/Auth/Console/stubs/policy.stub');
        if ($this->files->exists($vendorStub)) {
            $stub = $this->files->get($vendorStub);
            $ns = "App\\Domain\\{$domain}\\Policies";
            $stub = str_replace('DummyNamespace', $ns, $stub);
            $stub = str_replace('DummyClass', $className, $stub);

            if ($modelFQCN) {
                $stub = str_replace('DummyModel', $modelBasename, $stub);
                $stub = str_replace('DummyFullModel', $modelFQCN, $stub);
            }

            return $stub;
        }

        // Inline fallback template similar to Laravel's policy stub, adapted for domain and optional model
        $namespace = "App\\Domain\\{$domain}\\Policies";
        $modelUse = $modelFQCN ? "use {$modelFQCN};\n" : '';
        $modelParam = $modelBasename ? "{$modelBasename} \$model" : 'mixed $model';

        $methods = $this->buildPolicyMethods($modelBasename);

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\\Auth\\Access\\HandlesAuthorization;
{$modelUse}
class {$className}
{
    use HandlesAuthorization;

{$methods}
}

PHP;
    }

    /**
    * Build policy method stubs. If $modelBasename is provided, include model type hint.
    *
    * @param string|null $modelBasename
    * @return string
    */
    protected function buildPolicyMethods(?string $modelBasename = null): string
    {
    // If a model basename is provided, use it as type hint; otherwise use 'mixed $model'
        $modelHint = $modelBasename ? "{$modelBasename} \$model" : 'mixed $model';

        // Template with %s placeholders where model param should appear
        $template = <<<'PHP'
    /**
    * Determine whether the user can view any models.
    *
    * @param  mixed  $user
    * @return bool
    */
    public function viewAny($user)
    {
        return false;
    }

    /**
    * Determine whether the user can view the model.
    *
    * @param  mixed  $user
    * @param  %s
    * @return bool
    */
    public function view($user, %s)
    {
        return false;
    }

    /**
    * Determine whether the user can create models.
    *
    * @param  mixed  $user
    * @return bool
    */
    public function create($user)
    {
        return false;
    }

    /**
    * Determine whether the user can update the model.
    *
    * @param  mixed  $user
    * @param  %s
    * @return bool
    */
    public function update($user, %s)
    {
        return false;
    }

    /**
    * Determine whether the user can delete the model.
    *
    * @param  mixed  $user
    * @param  %s
    * @return bool
    */
    public function delete($user, %s)
    {
        return false;
    }

    /**
    * Determine whether the user can restore the model.
    *
    * @param  mixed  $user
    * @param  %s
    * @return bool
    */
    public function restore($user, %s)
    {
        return false;
    }

    /**
    * Determine whether the user can permanently delete the model.
    *
    * @param  mixed  $user
    * @param  %s
    * @return bool
    */
    public function forceDelete($user, %s)
    {
        return false;
    }
PHP;

        // Count how many '%s' placeholders exist in template
        $count = substr_count($template, '%s');

        // Prepare replacements array (all entries = $modelHint)
        $replacements = array_fill(0, $count, $modelHint);

        // Use vsprintf to substitute placeholders robustly
        // If $count is 0, vsprintf will simply return the template unchanged.
        return vsprintf($template, $replacements);
    }


    /**
     * Return the command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['domain', null, InputOption::VALUE_REQUIRED, 'The domain name where the policy will be created.'],
            ['model', null, InputOption::VALUE_OPTIONAL, 'Optional model class (FQCN or short) to scaffold policy methods.'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing policy file if present.'],
        ];
    }

    /**
     * Define the command arguments.
     *
     * name: required — policy name or base name (will append Policy suffix if missing)
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', 2, 'The name of the policy class (e.g. PostPolicy) or a base name (Post).'],
        ];
    }

    /**
     * Provide a stub path to satisfy GeneratorCommand API.
     *
     * We return project/vendor stub path if exists; otherwise return this file path.
     * Parent may call getStub(), so keep it accurate.
     *
     * @return string
     */
    protected function getStub(): string
    {
        $projectStub = base_path('stubs/policy.stub');
        if ($this->files->exists($projectStub)) {
            return $projectStub;
        }

        $vendorStub = base_path('vendor/laravel/framework/src/Illuminate/Auth/Console/stubs/policy.stub');
        if ($this->files->exists($vendorStub)) {
            return $vendorStub;
        }

        return __FILE__;
    }
}
