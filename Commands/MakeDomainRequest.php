<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;

/**
 * MakeDomainRequest
 *
 * Production-ready command to generate FormRequest classes inside a Domain:
 *   app/Domain/{Domain}/Http/Requests
 *
 * Behavior:
 * - Requires --domain option; domain folder must already exist.
 * - By default creates two requests:
 *     Store{Base}Request and Update{Base}Request
 *   where {Base} is from --model (class basename) or the name argument.
 * - If the provided name already targets a specific request (starts with Store/Update),
 *   only that request will be created.
 * - If a created file exists and --force is not provided, the command skips it and warns.
 * - If any file write fails, any files created during this run are rolled back (deleted).
 *
 * Notes for maintainers:
 * - We don't rely on parent's automatic stub generation; we write the request files directly.
 * - getStub() returns a path to a harmless stub path to satisfy GeneratorCommand API.
 * - Keep messages simple and actionable.
 */
class MakeDomainRequest extends GeneratorCommand
{
    protected $name = 'make:d-request';
    protected $description = 'Create domain-scoped FormRequest classes (Store/Update) inside a Domain';
    protected $type = 'Request';

    /**
     * Constructor: pass Filesystem to parent.
     *
     * Note: we intentionally do not redeclare $files property (parent already has it).
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct($files);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Validate domain option
        $domainOption = $this->option('domain');
        if (! $domainOption) {
            $this->error('The --domain option is required. Example: --domain=Blog');
            return 1;
        }

        // Basic domain name validation (letters, numbers, dash, underscore)
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $domainOption)) {
            $this->error('Invalid domain name. Only letters, numbers, underscore and dash are allowed.');
            return 1;
        }

        $domain = Str::studly($domainOption);
        $domainPath = app_path("Domain/{$domain}");

        if (! $this->files->isDirectory($domainPath)) {
            $this->error("Domain directory not found: app/Domain/{$domain}. Create it first.");
            return 1;
        }

        // Determine base name: prefer --model if supplied, otherwise use name argument
        $modelOption = $this->option('model');
        $nameArg = trim((string) $this->argument('name'));

        if ($modelOption) {
            // Accept FQCN or simple name
            $base = class_basename($modelOption);
            if ($base === '') {
                $this->error('Invalid --model value. Provide a valid class name or FQCN.');
                return 1;
            }
        } else {
            if ($nameArg === '') {
                $this->error('You must provide a name (e.g. ContactForm) or pass --model=Post.');
                return 1;
            }
            // sanitize name: prevent traversal and invalid chars
            if (strpos($nameArg, '..') !== false || strpos($nameArg, '/') !== false || strpos($nameArg, '\\') !== false) {
                $this->error('Invalid name. Do not include path separators or traversal patterns.');
                return 1;
            }

            // If user passed a name like "StorePostRequest" or "UpdatePostRequest",
            // we will treat that as a specific target. Otherwise base is the given name.
            // Remove trailing "Request" if present to get base.
            $tmp = Str::endsWith($nameArg, 'Request') ? Str::substr($nameArg, 0, -7) : $nameArg;
            $base = Str::studly($tmp);
            if ($base === '') {
                $this->error('Invalid name argument.');
                return 1;
            }
        }

        // Decide which request classes to generate:
        // - If nameArg starts with "Store" or "Update" (case-insensitive), create only that target.
        // - Otherwise create both Store{Base}Request and Update{Base}Request.
        $requestsToMake = [];

        if (! empty($nameArg)) {
            $normalized = Str::studly($nameArg);
            if (Str::startsWith($normalized, 'Store')) {
                $requestsToMake[] = Str::finish($normalized, 'Request');
            } elseif (Str::startsWith($normalized, 'Update')) {
                $requestsToMake[] = Str::finish($normalized, 'Request');
            } else {
                // Not a specific Store/Update name — create both
                $requestsToMake[] = "Store{$base}Request";
                $requestsToMake[] = "Update{$base}Request";
            }
        } else {
            // No nameArg (user used --model only) -> create Store/Update for model base
            $requestsToMake[] = "Store{$base}Request";
            $requestsToMake[] = "Update{$base}Request";
        }

        // Path for requests
        $requestsPath = app_path("Domain/{$domain}/Http/Requests");
        // Ensure directory exists
        if (! $this->files->isDirectory($requestsPath)) {
            try {
                $this->files->makeDirectory($requestsPath, 0755, true);
                $this->info("Created directory: {$requestsPath}");
            } catch (\Throwable $e) {
                $this->error("Failed to create requests directory: {$e->getMessage()}");
                return 1;
            }
        }

        $force = (bool) $this->option('force');
        $createdFiles = [];

        foreach ($requestsToMake as $className) {
            // Ensure suffix and studly class name
            $className = Str::studly($className);
            $className = Str::endsWith($className, 'Request') ? $className : $className . 'Request';

            // Validate className characters
            if (! preg_match('/^[A-Za-z0-9_]+$/', Str::replace('\\', '', $className))) {
                $this->warn("Skipping invalid request class name: {$className}");
                continue;
            }

            $qualified = $this->qualifyClass($className); // uses getDefaultNamespace
            $path = $this->getPath($qualified);

            // If file exists and not forcing -> skip
            if ($this->files->exists($path) && ! $force) {
                $this->comment("Request already exists, skipping: {$path}");
                continue;
            }

            // Build stub contents (we prefer project-level stubs if available in stubs/)
            $stub = $this->buildRequestContent($domain, $className, $base);

            // Try write file; if error -> rollback created files and fail
            try {
                $this->files->put($path, $stub);
                $createdFiles[] = $path;
                $this->info("Created Request: {$path}");
            } catch (\Throwable $e) {
                // Rollback what we created
                foreach ($createdFiles as $f) {
                    try { $this->files->delete($f); } catch (\Throwable $_) {}
                }
                $this->error("Failed to write request {$className}: {$e->getMessage()}");
                return 1;
            }
        }

        if (empty($createdFiles)) {
            $this->info('No requests were created.');
        } else {
            $this->info('Requests generation completed successfully.');
        }

        return 0;
    }

    /**
     * Determine the default namespace for generated classes.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        $domain = Str::studly($this->option('domain') ?: 'Unknown');
        return "{$rootNamespace}\\Domain\\{$domain}\\Http\\Requests";
    }

    /**
     * Build request file content.
     *
     * This tries to prefer a project stub if present:
     * - stubs/form-request.stub
     * If not found, uses an internal minimal template.
     *
     * The returned content uses the domain namespace and the target class name.
     *
     * @param string $domain  Studly domain name
     * @param string $className  Final class name (ending with Request)
     * @param string $baseName  Base model/name (for comments)
     * @return string
     */
    protected function buildRequestContent(string $domain, string $className, string $baseName): string
    {
        // Check project stub first
        $projectStub = base_path('stubs/form-request.stub');
        if ($this->files->exists($projectStub)) {
            $stub = $this->files->get($projectStub);
            // replace Laravel-style placeholders if any
            $namespace = "App\\Domain\\{$domain}\\Http\\Requests";
            $stub = str_replace('DummyNamespace', $namespace, $stub);
            $stub = str_replace('DummyClass', $className, $stub);
            return $stub;
        }

        // Vendor stub fallback
        $vendorStub = base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/form-request.stub');
        if ($this->files->exists($vendorStub)) {
            $stub = $this->files->get($vendorStub);
            $namespace = "App\\Domain\\{$domain}\\Http\\Requests";
            $stub = str_replace('DummyNamespace', $namespace, $stub);
            $stub = str_replace('DummyClass', $className, $stub);
            return $stub;
        }

        // Minimal inline stub
        $namespace = "App\\Domain\\{$domain}\\Http\\Requests";
        $modelComment = $baseName ? " * Associated base name: {$baseName}\n" : '';

        return <<<PHP
<?php

namespace {$namespace};

use Illuminate\\Foundation\\Http\\FormRequest;

class {$className} extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * {$modelComment}
     * @return bool
     */
    public function authorize()
    {
        // Return true if the request is allowed. Add ACL checks if necessary.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Add your validation rules here.
        return [];
    }
}

PHP;
    }

    /**
     * Return the command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['domain', null, InputOption::VALUE_REQUIRED, 'The domain name where the request will be created.'],
            ['model', null, InputOption::VALUE_OPTIONAL, 'Optional model class (FQCN or short) to derive base name.'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing request files if they exist.'],
        ];
    }

    /**
     * Define the name argument (optional if --model is provided).
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', 2, 'The base name for the request(s) (e.g. ContactForm) or a specific request class.'],
        ];
    }

    /**
     * Minimal stub path getter to satisfy parent signature.
     * We do not actually use parent's stub mechanism for file writes.
     *
     * @return string
     */
    protected function getStub(): string
    {
        // prefer project-level stub if present, else vendor path else return this file path
        $projectStub = base_path('stubs/form-request.stub');
        if ($this->files->exists($projectStub)) {
            return $projectStub;
        }

        $vendorStub = base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/form-request.stub');
        if ($this->files->exists($vendorStub)) {
            return $vendorStub;
        }

        // fallback to this file (never used)
        return __FILE__;
    }
}
