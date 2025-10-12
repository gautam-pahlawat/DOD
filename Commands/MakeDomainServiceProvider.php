<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * MakeDomainProvider
 *
 * Create a DomainServiceProvider (or custom-named provider) inside a Domain.
 *
 * Behavior:
 * - Validates domain and provider name.
 * - Writes provider into app/Domain/{Domain}/Providers/{Name}.php
 * - Optionally uses a custom stub file.
 * - Optionally registers provider in config/app.php (creates a backup first).
 * - Supports --force to overwrite existing provider.
 *
 * Usage examples:
 *  php artisan make:d-provider Blog
 *  php artisan make:d-provider Blog CustomProvider --with-register
 *  php artisan make:d-provider Blog --stub=/full/path/to/stub --force
 */
class MakeDomainServiceProvider extends Command
{
    protected $name = 'make:d-provider';
    protected $description = 'Create a DomainServiceProvider (or named provider) inside a Domain';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function handle(): int
    {
        $rawDomain = (string) $this->argument('domain');
        $providerName = (string) $this->argument('name') ?: 'DomainServiceProvider';
        $force = (bool) $this->option('force');
        $register = (bool) $this->option('register');
        $stubPath = $this->option('stub') ? (string) $this->option('stub') : null;

        // Validate domain name
        if (! $this->isValidDomainName($rawDomain)) {
            $this->error("Invalid domain name '{$rawDomain}'. Allowed chars: letters, numbers, underscore (_) and dash (-).");
            return 1;
        }

        $domain = Str::studly($rawDomain);
        $basePath = app_path("Domain/{$domain}");
        if (! $this->files->isDirectory($basePath)) {
            $this->error("Domain '{$domain}' does not exist. Create it first (e.g. php artisan make:domain {$domain}).");
            return 1;
        }

        // Normalize provider class name
        $providerName = Str::studly($providerName);
        // Ensure it ends with "Provider"
        if (! Str::endsWith($providerName, 'Provider')) {
            $providerName .= 'Provider';
        }

        // Basic validation for class name
        if (! preg_match('/^[A-Za-z0-9_]+$/', $providerName)) {
            $this->error("Invalid provider name '{$providerName}'. Use only letters, numbers and underscore.");
            return 1;
        }

        $providerDir = "{$basePath}/Providers";
        $providerPath = "{$providerDir}/{$providerName}.php";
        $created = [];

        try {
            // Ensure Providers directory exists
            if (! $this->files->isDirectory($providerDir)) {
                $this->files->makeDirectory($providerDir, 0755, true);
                $created[] = $providerDir;
                $this->info("Created directory: {$providerDir}");
            }

            // If file exists and not forcing -> abort
            if ($this->files->exists($providerPath) && ! $force) {
                $this->error("Provider already exists at: {$providerPath}. Use --force to overwrite.");
                return 1;
            }

            // If forcing and exists -> remove existing file first
            if ($this->files->exists($providerPath) && $force) {
                $this->files->delete($providerPath);
                $this->info("Overwriting existing provider: {$providerPath}");
            }

            // Build content (custom stub > project stub > inline)
            $content = $this->buildProviderContent($domain, $providerName, $stubPath);

            // Write file
            $this->files->put($providerPath, $content);
            $created[] = $providerPath;
            $this->info("Provider created: {$providerPath}");

            // Optionally register in config/app.php
            if ($register) {
                $providerFQCN = "App\\Domain\\{$domain}\\Providers\\{$providerName}";
                $registered = $this->registerProviderInConfig($providerFQCN);

                if ($registered) {
                    $this->info("Provider registered in config/app.php: {$providerFQCN}::class");
                } else {
                    $this->warn("Unable to register provider automatically. Please add `{$providerFQCN}::class` to the 'providers' array in config/app.php manually.");
                }
            }

            $this->info("Done. You can now register bindings, load routes and policies inside the provider.");
            return 0;
        } catch (\Throwable $e) {
            // Attempt rollback of created files
            $this->error("An error occurred: " . $e->getMessage());
            $this->warn("Rolling back created files...");

            foreach (array_reverse($created) as $path) {
                try {
                    if ($this->files->isDirectory($path)) {
                        $this->files->deleteDirectory($path);
                    } else {
                        $this->files->delete($path);
                    }
                } catch (\Throwable $_) {
                    // ignore rollback errors
                }
            }

            $this->error("Rollback finished. Inspect filesystem and try again.");
            return 1;
        }
    }

    /**
     * Validate domain name (simple safe check).
     */
    protected function isValidDomainName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $name);
    }

    /**
     * Build provider file content.
     * Preference:
     *  - use --stub parameter if provided and file exists
     *  - else use project-level stub at stubs/provider.stub
     *  - else build inline minimal provider template
     */
    protected function buildProviderContent(string $domain, string $providerName, ?string $customStub): string
    {
        // 1) custom stub
        if ($customStub && $this->files->exists($customStub)) {
            $stub = $this->files->get($customStub);
            return $this->replaceProviderPlaceholders($stub, $domain, $providerName);
        }

        // 2) project-level stub
        $projectStub = base_path('stubs/provider.stub');
        if ($this->files->exists($projectStub)) {
            $stub = $this->files->get($projectStub);
            return $this->replaceProviderPlaceholders($stub, $domain, $providerName);
        }

        // 3) vendor fallback or inline (we'll use inline here)
        $namespace = "App\\Domain\\{$domain}\\Providers";
        $routesPath = "app/Domain/{$domain}/Routes";

        $template = <<<PHP
<?php

namespace {$namespace};

use Illuminate\\Support\\ServiceProvider;

class {$providerName} extends ServiceProvider
{
    /**
     * Bootstrap services for the domain.
     *
     * Use this method to load domain-specific routes, publish resources,
     * register event listeners, or perform other bootstrapping tasks.
     */
    public function boot(): void
    {
        // Load domain web routes if present
        if (file_exists(base_path('{$routesPath}/web.php'))) {
            \$this->loadRoutesFrom(base_path('{$routesPath}/web.php'));
        }

        // Load domain api routes if present
        if (file_exists(base_path('{$routesPath}/api.php'))) {
            \$this->loadRoutesFrom(base_path('{$routesPath}/api.php'));
        }

        // Register any domain-specific resources, policies, observers here.
    }

    /**
     * Register bindings and singletons for the domain.
     *
     * Use this method to bind interfaces to implementations,
     * register container singletons, or merge configuration.
     */
    public function register(): void
    {
        //
    }
}

PHP;

        return $template;
    }

    /**
     * Replace placeholders in a custom stub.
     * Supported placeholders:
     *   {{NAMESPACE}}, {{CLASS}}, {{DOMAIN}}, {{ROUTES_PATH}}
     */
    protected function replaceProviderPlaceholders(string $stub, string $domain, string $providerName): string
    {
        $namespace = "App\\Domain\\{$domain}\\Providers";
        $routesPath = "app/Domain/{$domain}/Routes";

        $replacements = [
            '{{NAMESPACE}}' => $namespace,
            '{{CLASS}}' => $providerName,
            '{{DOMAIN}}' => $domain,
            '{{ROUTES_PATH}}' => $routesPath,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Attempt to insert provider into config/app.php providers array.
     * Returns true on success, false otherwise.
     *
     * Note: We create a timestamped backup before modifying config/app.php.
     */
    protected function registerProviderInConfig(string $providerFQCN): bool
    {
        $configPath = config_path('app.php');

        if (! $this->files->exists($configPath) || ! is_writable($configPath)) {
            $this->warn("Cannot modify config/app.php (missing or not writable).");
            return false;
        }

        $content = $this->files->get($configPath);

        // If already present, nothing to do
        if (Str::contains($content, $providerFQCN)) {
            $this->comment("Provider already appears to be registered in config/app.php.");
            return true;
        }

        // Create a backup
        $backup = $configPath . '.bak.' . date('YmdHis');
        try {
            $this->files->copy($configPath, $backup);
            $this->info("Backup created: {$backup}");
        } catch (\Throwable $e) {
            $this->warn("Could not create backup of config/app.php: " . $e->getMessage());
            return false;
        }

        // Try to locate the 'providers' array and append provider (best-effort regex)
        $pattern = '/(\'providers\'\s*=>\s*\[)([\\s\\S]*?)(\\n\\s*\\],)/m';
        if (! preg_match($pattern, $content, $matches)) {
            $this->warn("Could not locate providers array in config/app.php to insert provider automatically.");
            return false;
        }

        $providersBlock = $matches[2];

        // Append the new provider line (indented)
        $newProvidersBlock = $providersBlock . "\n        {$providerFQCN}::class,";

        $newContent = str_replace($providersBlock, $newProvidersBlock, $content);

        try {
            $this->files->put($configPath, $newContent);
            return true;
        } catch (\Throwable $e) {
            $this->warn("Failed to write config/app.php: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Command arguments and options
     */
    protected function getArguments()
    {
        return [
            ['domain', 1, 'The domain name (app/Domain/{Domain})'],
            ['name', 2, 'The provider class name (optional). Example: DomainServiceProvider or CustomProvider'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing provider if present'],
            ['register', null, InputOption::VALUE_NONE, 'Try to register the provider into config/app.php (creates backup)'],
            ['stub', null, InputOption::VALUE_OPTIONAL, 'Path to a custom stub file to use for provider generation'],
        ];
    }
}
