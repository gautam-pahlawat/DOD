<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ResourceMakeCommand;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;

/**
 * MakeDomainResource - production-ready (model support fixed)
 */
class MakeDomainResource extends ResourceMakeCommand
{
    protected $name = 'make:d-resource';
    protected $description = 'Create an API Resource (or Collection) inside a Domain (domain required)';
    protected $type = 'Resource';

    public function __construct(Filesystem $files)
    {
        parent::__construct($files);
    }

    public function handle()
    {
        // validate input first — short-circuit on error
        if (! $this->validateInput()) {
            return 1; // stop execution, proper error messages already printed
        }
        // 1) validate domain
        $domainOpt = $this->option('domain');
        if (! $domainOpt) {
            $this->error('The --domain option is required. Example: --domain=Blog');
            return 1;
        }
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $domainOpt)) {
            $this->error('Invalid domain name. Allowed characters: letters, numbers, underscore, dash.');
            return 1;
        }

        $domain = Str::studly($domainOpt);
        $domainPath = app_path("Domain/{$domain}");
        if (! $this->files->isDirectory($domainPath)) {
            $this->error("Domain directory not found: app/Domain/{$domain}. Create it first (php artisan make:domain {$domain}).");
            return 1;
        }

        // 2) compute target path and prevent accidental overwrite
        $nameInput = $this->getNameInput();
        $qualifiedClass = $this->qualifyClass($nameInput);
        $targetPath = $this->getPath($qualifiedClass);
        $force = (bool) $this->option('force');

        if ($this->files->exists($targetPath) && ! $force) {
            $this->error("Resource already exists at: {$targetPath}. Use --force to overwrite.");
            return 1;
        }

        // 3) normalize model option robustly (works whether user used --model or -m)
        $rawModel = $this->input->getParameterOption(['--model', '-m'], null);
        if ($rawModel !== null) {
            $model = trim((string)$rawModel);
            if ($model !== '') {
                // clean leading backslash
                if (Str::startsWith($model, '\\')) {
                    $model = ltrim($model, '\\');
                }

                if (Str::contains($model, '\\') || Str::startsWith($model, 'App\\')) {
                    $modelFQCN = Str::start($model, 'App\\');
                } else {
                    $modelFQCN = "App\\Domain\\{$domain}\\Models\\" . Str::studly($model);
                }

                // set normalized FQCN into input option so parent can use it
                $this->input->setOption('model', $modelFQCN);

                // explicit warning if model file missing (non-blocking)
                $modelPath = base_path('app') . '/' . str_replace('\\', '/', Str::after($modelFQCN, 'App\\')) . '.php';
                if (! $this->files->exists($modelPath)) {
                    $this->warn("Model file not found at expected path: {$modelPath}. Resource will still be created, but consider creating the model first.");
                } else {
                    $this->info("Model resolved: {$modelFQCN}");
                }
            }
        }

        // 4) detect collection robustly and ensure option set for parent
        $isCollection = $this->input->hasParameterOption(['--collection', '-c']) || (bool) $this->option('collection') || Str::endsWith($nameInput, 'Collection');
        if ($isCollection && ! $this->option('collection')) {
            $this->input->setOption('collection', true);
        }

        // 5) call parent to do generation using our getStub() and buildClass()
        $result = parent::handle();

        // 6) final message
        if ($result === 0 || $result === null) {
            $name = Str::studly($this->argument('name'));
            $createdType = $isCollection ? 'Resource Collection' : 'Resource';
            $this->info("✅ {$createdType} [{$name}] created successfully inside Domain [{$domain}].");
            return 0;
        }

        return $result;
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        $domain = Str::studly($this->option('domain') ?: 'Unknown');
        return $rootNamespace . "\\Domain\\{$domain}\\Http\\Resources";
    }

    protected function getStub()
    {
        $isCollection = $this->input->hasParameterOption(['--collection','-c']) || (bool) $this->option('collection');

        // project stub check
        $projectStub = base_path($isCollection ? 'stubs/resource.collection.stub' : 'stubs/resource.stub');
        if ($this->files->exists($projectStub) && $this->files->isFile($projectStub)) {
            $this->line("Using stub: {$projectStub}");
            return $projectStub;
        }

        // vendor candidate stubs
        $vendorCandidates = [
            base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/' . ($isCollection ? 'collection.stub' : 'resource.stub')),
            base_path('vendor/laravel/framework/src/Illuminate/Http/Resources/' . ($isCollection ? 'collection.stub' : 'resource.stub')),
            base_path('vendor/laravel/framework/src/Illuminate/Http/Resources/json/' . ($isCollection ? 'collection.stub' : 'resource.stub')),
        ];

        foreach ($vendorCandidates as $cand) {
            if ($this->files->exists($cand) && $this->files->isFile($cand)) {
                $this->line("Using vendor stub: {$cand}");
                return $cand;
            }
        }

        // fallback: write safe stub in storage and return it
        $tempDir = storage_path('framework/stubs');
        if (! $this->files->isDirectory($tempDir)) {
            $this->files->makeDirectory($tempDir, 0755, true);
        }
        $tempFile = $tempDir . '/' . ($isCollection ? 'resource.collection.stub' : 'resource.stub');

        if (! $this->files->exists($tempFile) || ! $this->files->isFile($tempFile)) {
            $this->files->put($tempFile, $this->defaultStubContent($isCollection));
        }

        $this->line("Using temp stub: {$tempFile}");
        return $tempFile;
    }

    protected function defaultStubContent(bool $collection): string
    {
        if ($collection) {
            return <<<'PHP'
<?php

namespace DummyNamespace;

use Illuminate\Http\Resources\Json\ResourceCollection;

class DummyClass extends ResourceCollection
{
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
PHP;
        }
        return <<<'PHP'
<?php

namespace DummyNamespace;

use Illuminate\Http\Resources\Json\JsonResource;

class DummyClass extends JsonResource
{
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
PHP;
    }

    /**
     * Ensure model import is added even if parent didn't inject it.
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        // If user provided --model, ensure domain import exists and docblock hint
        if ($modelOption = $this->option('model')) {
            $modelBasename = class_basename($modelOption);
            $domain = Str::studly($this->option('domain'));
            $domainModelFQCN = "App\\Domain\\{$domain}\\Models\\{$modelBasename}";

            // add use statement after namespace if not already there
            if (! Str::contains($stub, "use {$domainModelFQCN};")) {
                $stub = preg_replace(
                    '/(namespace\s+[^\;]+;\s*)/',
                    "$1\nuse {$domainModelFQCN};\n",
                    $stub,
                    1
                );
            }

            // Optionally add a short docblock example inside toArray for clarity (non-intrusive)
            // If toArray exists and currently returns parent::toArray($request), we can add a commented example
            $stub = preg_replace_callback(
                '/public function toArray\(\s*\$request\s*\)\s*\{\s*return\s+parent::toArray\(\$request\);\s*\}/s',
                function ($m) use ($modelBasename) {
                    $example = <<<PHP
public function toArray(\$request)
    {
        // Example: return new {$modelBasename}Resource(\$this->resource);
        return parent::toArray(\$request);
    }
PHP;
                    return $example;
                },
                $stub,
                1
            );
        }

        return $stub;
    }

    protected function getOptions()
    {
        $options = parent::getOptions();

        $existsDomain = collect($options)->pluck(0)->contains('domain');
        if (! $existsDomain) {
            $options[] = ['domain', null, InputOption::VALUE_REQUIRED, 'The domain name where the resource will be created.'];
        }

        $existsModel = collect($options)->pluck(0)->contains('model');
        if (! $existsModel) {
            $options[] = ['model', 'm', InputOption::VALUE_OPTIONAL, 'The model class (FQCN) or short model name (domain-local) to associate with this resource.'];
        }

        $existsForce = collect($options)->pluck(0)->contains('force');
        if (! $existsForce) {
            $options[] = ['force', null, InputOption::VALUE_NONE, 'Overwrite existing files'];
        }

        return $options;
    }
    /**
 * Validate CLI inputs (domain, name, model).
 * Returns true if OK, otherwise prints error and returns false.
 */
protected function validateInput(): bool
{
    // domain
    $domain = (string) $this->option('domain');
    if ($domain === '') {
        $this->error('The --domain option is required. Example: --domain=Blog');
        return false;
    }
    // domain: must start with letter, allow letters/numbers/_/-, length limit (80)
    if (! preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,79}$/', $domain)) {
        $this->error('Invalid --domain. Use letters/numbers/_/- only, must start with a letter, max length 80.');
        return false;
    }

    // name (class/resource name)
    $nameRaw = trim((string) $this->argument('name'));
    if ($nameRaw === '') {
        $this->error('You must provide a resource name (argument). Example: PostResource or PostCollection');
        return false;
    }
    // prevent path-like or names with slashes/backslashes
    if (Str::contains($nameRaw, ['/', '\\'])) {
        $this->error('Invalid name: do not include path separators (/, \\). Provide a class name only.');
        return false;
    }
    $className = Str::studly($nameRaw);
    if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $className)) {
        $this->error("Invalid class name '{$nameRaw}'. Use a valid PHP class identifier (letters, numbers, underscore), cannot start with a number.");
        return false;
    }
    // basic reserved-words check (small list)
    $reserved = ['class','trait','interface','extends','implements','namespace','function','return','const','public','protected','private','null','true','false'];
    if (in_array(strtolower($className), $reserved, true)) {
        $this->error("Invalid class name '{$className}' — reserved word.");
        return false;
    }

    // model option (if provided) — prevent directory traversal or suspicious input
    $rawModel = $this->input->getParameterOption(['--model','-m'], null);
    if ($rawModel !== null && trim((string)$rawModel) !== '') {
        $rawModel = trim((string) $rawModel);
        if (strpos($rawModel, '..') !== false) {
            $this->error('Invalid --model value: contains ".." (not allowed).');
            return false;
        }
        if (preg_match('#[\/]#', $rawModel) && ! Str::contains($rawModel, ['\\'])) {
            // slash present but not backslash — suspicious for this usage
            $this->error('Invalid --model value: use FQCN (App\\Domain\\...\\Model) or short name (Post). Do not pass file paths.');
            return false;
        }
        // allow either FQCN chars or short name:
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $rawModel)) {
            $this->error('Invalid --model value. Use a short name (Post) or a valid FQCN (App\\Domain\\Blog\\Models\\Post).');
            return false;
        }
    }

    // all checks passed
    return true;
}

}
