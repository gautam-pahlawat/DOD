<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeDomain extends Command
{
    /**
     * Command signature.
     *
     * name: domain name (required)
     * --with-provider : also create DomainServiceProvider inside the domain
     * --register      : attempt to register generated provider in config/app.php (creates backup)
     * --force         : overwrite existing files when applicable
     */

    
    //{--register : Register the DomainServiceProvider in config/app.php (creates backup)}
    protected $signature = 'make:domain
                            {name : The name of the domain (letters, numbers, -, _)}
                            {--with-provider : Create a DomainServiceProvider inside the domain}
                            {--force : Overwrite files if they already exist}';

    protected $description = 'Create Laravel Domain skeleton (Models, Actions, Policies, Http, Requests, Resources, Routes, etc.)';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function handle(): int
    {
        $rawName = (string) $this->argument('name');
        $force = (bool) $this->option('force');
        $withProvider = (bool) $this->option('with-provider');
        //$register = (bool) $this->option('register');

        // 1) Validate domain name
        if (! $this->isValidDomainName($rawName)) {
            $this->error("Invalid domain name '{$rawName}'. Allowed characters: letters, numbers, underscore (_), dash (-).");
            return 1;
        }

        $domain = Str::studly($rawName);
        $basePath = app_path("Domain/{$domain}");

        // 2) If base dir exists and not forcing -> abort
        if ($this->files->isDirectory($basePath) && ! $force) {
            $this->error("Domain '{$domain}' already exists at: {$basePath}. Use --force to overwrite.");
            return 1;
        }

        $created = []; // track created paths for rollback

        try {
            // If forcing and exists, remove the directory first (careful)
            if ($this->files->isDirectory($basePath) && $force) {
                $this->warn("Overwriting existing domain directory: {$basePath}");
                $this->files->deleteDirectory($basePath);
            }

            // 3) Create directory structure
            $directories = [
                'Models',
                'Actions',
                'Policies',
                'Http/Controllers',
                'Http/Requests',
                'Http/Resources',
                'Routes',
                'Providers',
            ];

            foreach ($directories as $dir) {
                $path = "{$basePath}/{$dir}";
                if (! $this->files->isDirectory($path)) {
                    $this->files->makeDirectory($path, 0755, true);
                    $created[] = $path;
                    $this->info("Folder created: {$path}");
                }

                // put a .gitkeep so folder is never empty in VCS
                $gitkeep = $path . '/.gitkeep';
                if (! $this->files->exists($gitkeep)) {
                    $this->files->put($gitkeep, '');
                    $created[] = $gitkeep;
                }
            }

            // 4) Create routes files (Routes/web.php and Routes/api.php)
            $routesDir = "{$basePath}/Routes";
            $webRoutes = "{$routesDir}/web.php";
            $apiRoutes = "{$routesDir}/api.php";

            if (! $this->files->exists($webRoutes) || $force) {
                $this->files->put($webRoutes, $this->webRoutesStub($domain));
                $created[] = $webRoutes;
                $this->info("Route file created: {$webRoutes}");
            } else {
                $this->comment("Route file already exists: {$webRoutes}");
            }

            if (! $this->files->exists($apiRoutes) || $force) {
                $this->files->put($apiRoutes, $this->apiRoutesStub($domain));
                $created[] = $apiRoutes;
                $this->info("Route file created: {$apiRoutes}");
            } else {
                $this->comment("Route file already exists: {$apiRoutes}");
            }

            // 5) Optionally create DomainServiceProvider
            $providerFQCN = "App\\Domain\\{$domain}\\Providers\\DomainServiceProvider";
            $providerPath = "{$basePath}/Providers/DomainServiceProvider.php";
            if ($withProvider) {
                if (! $this->files->exists($providerPath) || $force) {
                    $this->files->put($providerPath, $this->domainProviderStub($domain));
                    $created[] = $providerPath;
                    $this->info("DomainServiceProvider created: {$providerPath}");
                } else {
                    $this->comment("Provider already exists: {$providerPath}");
                }

                // 6) Optionally register provider in config/app.php
                /** 
                *if ($register) {
                *    $registered = $this->registerProviderInConfig($providerFQCN);
                *    if ($registered) {
                *        $this->info("Provider registered in config/app.php: {$providerFQCN}");
                *    } else {
                *        $this->warn("Could not register provider automatically. Please add '{$providerFQCN}::class' to 'providers' in config/app.php manually.");
                *    }
                *}
                */
            }

            $this->info("Domain '{$domain}' created successfully at: {$basePath}");
            $this->line('');
            $this->info('Next steps:');
            $this->line(" - Add your Models, Actions, Controllers, Requests and Resources under app/Domain/{$domain}");
            if ($withProvider) {
                $this->line(" - If you registered provider, it is available; otherwise add `App\\\\Domain\\\\{$domain}\\\\Providers\\\\DomainServiceProvider::class` to config/app.php or register it via Composer auto discovery.");
            } else {
                $this->line(" - Consider adding a DomainServiceProvider with --with-provider for bindings, route loading and policy registration.");
            }

            return 0;
        } catch (\Throwable $e) {
            // rollback created files/directories
            $this->error("An error occurred: " . $e->getMessage());
            $this->warn('Attempting rollback of created files/directories...');

            foreach (array_reverse($created) as $path) {
                try {
                    if ($this->files->isDirectory($path)) {
                        $this->files->deleteDirectory($path);
                    } else {
                        $this->files->delete($path);
                    }
                } catch (\Throwable $_) {
                    // ignore individual rollback errors
                }
            }

            $this->error('Rollback finished. Please inspect your filesystem and try again.');
            return 1;
        }
    }

    /**
     * Validate domain name (no path traversal, allowed chars)
     */
    protected function isValidDomainName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $name);
    }

    /**
     * Stub for web routes inside the domain.
     */
    protected function webRoutesStub(string $domain): string
    {
        $controllerPlaceholder = "ExampleController";
        return <<<PHP
<?php
// Domain: {$domain} - web routes
// You can add domain-specific routes here and load them in your DomainServiceProvider.
use Illuminate\\Support\\Facades\\Route;

Route::middleware('web')
    ->group(function () {
        // Route::get('/example', [App\\Domain\\{$domain}\\Http\\Controllers\\{$controllerPlaceholder}::class, 'index']);
    });

PHP;
    }

    /**
     * Stub for api routes inside the domain.
     */
    protected function apiRoutesStub(string $domain): string
    {
        $controllerPlaceholder = "ExampleController";
        return <<<PHP
<?php
// Domain: {$domain} - api routes
use Illuminate\\Support\\Facades\\Route;

Route::prefix('api')
    ->middleware('api')
    ->group(function () {
        // Route::get('{$domain}', [App\\Domain\\{$domain}\\Http\\Controllers\\{$controllerPlaceholder}::class, 'index']);
    });

PHP;
    }

    /**
     * DomainServiceProvider stub content.
     */
    protected function domainProviderStub(string $domain): string
    {
        $namespace = "App\\Domain\\{$domain}\\Providers";
        $routesPath = "app/Domain/{$domain}/Routes";
        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\\Support\\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services for the domain.
     */
    public function boot(): void
    {
        // Load domain routes
        if (file_exists(base_path('{$routesPath}/web.php'))) {
            \$this->loadRoutesFrom(base_path('{$routesPath}/web.php'));
        }
        if (file_exists(base_path('{$routesPath}/api.php'))) {
            \$this->loadRoutesFrom(base_path('{$routesPath}/api.php'));
        }

        // Register policies or other domain-specific bootstrapping here.
    }

    /**
     * Register bindings and singletons for the domain.
     */
    public function register(): void
    {
        // Put domain bindings here, e.g.
        // \$this->app->bind(RepositoryInterface::class, RepositoryImplementation::class);
    }
}

PHP;
    }

    /**
     * Attempt to register provider in config/app.php by inserting provider FQCN into providers array.
     * Creates a backup of config/app.php before modification.
     *
     * Returns true on success, false otherwise.
     */
    protected function registerProviderInConfig(string $providerFQCN): bool
    {
        $configPath = config_path('app.php');
        if (! $this->files->exists($configPath) || ! is_writable($configPath)) {
            $this->warn("Cannot modify config/app.php automatically (file missing or not writable).");
            return false;
        }

        $content = $this->files->get($configPath);

        // If already registered, skip
        if (Str::contains($content, $providerFQCN)) {
            $this->comment('Provider already appears to be registered in config/app.php.');
            return true;
        }

        // Backup
        $backup = $configPath . '.bak.' . date('YmdHis');
        try {
            $this->files->copy($configPath, $backup);
            $this->info("Backup of config/app.php created at: {$backup}");
        } catch (\Throwable $e) {
            $this->warn("Could not create a backup of config/app.php: " . $e->getMessage());
            return false;
        }

        // Attempt to insert provider before closing bracket of 'providers' array.
        $pattern = '/(\'providers\'\s*=>\s*\[)([\\s\\S]*?)(\\n\\s*\\],)/m';
        if (! preg_match($pattern, $content, $matches)) {
            $this->warn('Could not locate providers array in config/app.php to insert provider automatically.');
            return false;
        }

        $providersBlock = $matches[2];

        // append new provider line
        $newProvidersBlock = $providersBlock . "\n        {$providerFQCN}::class,";

        $newContent = str_replace($providersBlock, $newProvidersBlock, $content);

        try {
            $this->files->put($configPath, $newContent);
            return true;
        } catch (\Throwable $e) {
            $this->warn('Failed to write modified config/app.php: ' . $e->getMessage());
            return false;
        }
    }
}
