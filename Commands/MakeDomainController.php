<?php

namespace App\Console\Commands;

use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * MakeDomainController
 *
 * Creates a controller inside a domain (app/Domain/{Domain}/Http/Controllers).
 * Optionally creates domain-scoped FormRequest classes and patches controller imports/typehints.
 */
class MakeDomainController extends ControllerMakeCommand
{
    protected $name = 'make:d-controller';
    protected $description = 'Create a controller inside a specific Domain (domain is required)';
    protected $type = 'Controller';

    /**
     * When true, we will patch the controller stub to use domain Requests.
     * This becomes true if both FormRequest files exist in the domain.
     *
     * @var bool
     */
    protected $createdRequests = false;

    /**
     * Execute the console command.
     *
     * - validate domain & controller name
     * - compute controller target path and abort if exists and --force not given
     * - if --requests && --model => create domain requests (track created files)
     * - temporarily remove 'requests' option so parent::handle doesn't create global requests
     * - call parent::handle() to generate controller
     * - on failure -> rollback created request files
     *
     * @return int|null
     */
    public function handle()
    {
        // --- validate domain option presence & characters
        $domainOption = $this->option('domain');
        if (! $domainOption) {
            $this->error('* You must provide a valid domain using --domain option.');
            return 1;
        }

        if (! preg_match('/^[A-Za-z0-9_\-]+$/', $domainOption)) {
            $this->error('* Invalid domain name. Only letters, numbers, underscore and dash are allowed.');
            return 1;
        }

        $domain = Str::studly($domainOption);
        $domainPath = app_path("Domain/{$domain}");
        if (! $this->files->isDirectory($domainPath)) {
            $this->error("* The domain '{$domain}' does not exist in app/Domain. Create it first.");
            return 1;
        }

        // --- validate controller name (prevent traversal)
        $nameInput = $this->getNameInput(); // e.g. "UserController"
        if (strpos($nameInput, '..') !== false || strpos($nameInput, '/') !== false || strpos($nameInput, '\\') !== false) {
            $this->error('* Invalid controller name.');
            return 1;
        }

        // --- determine final controller path & avoid creating requests if controller already exists
        $controllerClass = $this->qualifyClass($nameInput); // uses getDefaultNamespace internally
        $controllerPath = $this->getPath($controllerClass);

        if ($this->files->exists($controllerPath) && ! $this->option('force')) {
            $this->error("* Controller already exists at [{$controllerPath}]. Use --force to overwrite.");
            return 1;
        }

        // --- normalize and set model option to domain FQCN if needed
        $modelOpt = $this->option('model');
        if ($modelOpt) {
            $modelClass = Str::startsWith($modelOpt, 'App\\') ? $modelOpt : "App\\Domain\\{$domain}\\Models\\" . class_basename($modelOpt);
            $this->input->setOption('model', $modelClass);
        }

        // --- create domain form requests only if controller will actually be created
        $createdFiles = [];
        if ($this->option('requests') && $this->option('model')) {
            $createdFiles = $this->createDomainFormRequests($domain, $this->option('model'));
        } elseif ($this->option('requests') && ! $this->option('model')) {
            // clear guidance: requests without model is ambiguous
            $this->warn('- The --requests option typically requires --model to generate Store/Update requests.');
        }

        // If createdRequests is false but files existed, createDomainFormRequests has already set it.
        // Prevent parent from creating global requests in App\Http\Requests
        $this->input->setOption('requests', false);

        // --- call parent to generate controller
        $result = parent::handle();

        // --- on failure, rollback any files we created
        $success = ($result === 0 || $result === null);
        if (! $success && ! empty($createdFiles)) {
            $this->warn('- Controller creation failed — rolling back created FormRequest files.');
            foreach ($createdFiles as $file) {
                try {
                    if ($this->files->exists($file)) {
                        $this->files->delete($file);
                        $this->info("Rolled back: deleted {$file}");
                    }
                } catch (\Throwable $e) {
                    $this->error("* Failed to delete created file during rollback: {$file}. Error: {$e->getMessage()}");
                }
            }
            // try to remove requests directory if we created it and it's now empty
            $requestsDir = app_path("Domain/{$domain}/Http/Requests");
            if ($this->files->isDirectory($requestsDir) && count($this->files->files($requestsDir)) === 0) {
                // only remove if empty
                try {
                    $this->files->deleteDirectory($requestsDir);
                    $this->info("Rolled back: removed empty directory {$requestsDir}");
                } catch (\Throwable $e) {
                    // non-fatal
                }
            }

            return $result;
        }

        // --- on success, inform if we created/used requests
        if ($success) {
            if ($this->createdRequests) {
                $this->info("+ FormRequest files are ready under app/Domain/{$domain}/Http/Requests and imported in the controller.");
            }
        }

        return $result;
    }

    /**
     * Default namespace for controllers in a domain.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        $domain = $this->option('domain') ? Str::studly($this->option('domain')) : 'Unknown';
        return "{$rootNamespace}\\Domain\\{$domain}\\Http\\Controllers";
    }

    /**
     * Modify the controller stub before writing.
     *
     * If we created (or have) FormRequest classes inside the domain, replace imports
     * and type-hints so the controller uses App\Domain\{Domain}\Http\Requests\StoreXRequest, etc.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);
        $domainOpt = $this->option('domain') ? Str::studly($this->option('domain')) : null;

        // Adjust model FQCN in stub if --model provided
        if ($model = $this->option('model') && $domainOpt) {
            // note: $this->option('model') already normalized in handle()
        }
        $modelOption = $this->option('model');
        if ($modelOption && $domainOpt) {
            $modelClass = Str::startsWith($modelOption, 'App\\') ? $modelOption : "App\\Domain\\{$domainOpt}\\Models\\" . class_basename($modelOption);
            $stub = str_replace(
                $this->rootNamespace() . 'Models\\' . class_basename($modelOption),
                $modelClass,
                $stub
            );
        }

        // If we created / have Requests in domain, patch imports and type hints
        if ($this->createdRequests && $modelOption && $domainOpt) {
            $modelBasename = class_basename($modelOption);
            $storeReq = "Store{$modelBasename}Request";
            $updateReq = "Update{$modelBasename}Request";
            $reqNamespace = "App\\Domain\\{$domainOpt}\\Http\\Requests";

            // Remove generic Illuminate\Http\Request import to avoid conflicts
            $stub = preg_replace('/use\s+Illuminate\\\\Http\\\\Request\s*;\s*/', '', $stub);

            // Ensure our FormRequest imports exist right after namespace declaration
            $stub = preg_replace(
                '/(namespace\s+[^\;]+;\s*)/',
                "$1\nuse {$reqNamespace}\\{$storeReq};\nuse {$reqNamespace}\\{$updateReq};\n",
                $stub,
                1
            );

            // Replace function parameter typehints for store/update methods
            $stub = preg_replace(
                '/public function\s+store\(\s*Request\s+\$request\s*\)/',
                "public function store({$storeReq} \$request)",
                $stub
            );

            $stub = preg_replace_callback(
                '/public function\s+update\(\s*Request\s+\$request\s*,\s*([^\)]+)\)/',
                function ($m) use ($updateReq) {
                    return "public function update({$updateReq} \$request, {$m[1]})";
                },
                $stub
            );

            // Also replace fully-qualified App\Http\Requests\X with domain namespace (defensive)
            $stub = str_replace(
                ['App\\Http\\Requests\\'.$storeReq, 'App\\Http\\Requests\\'.$updateReq],
                [$reqNamespace.'\\'.$storeReq, $reqNamespace.'\\'.$updateReq],
                $stub
            );
        }

        return $stub;
    }

    /**
     * Merge parent options with domain option (avoid duplicates).
     *
     * @return array
     */
    protected function getOptions()
    {
        $options = parent::getOptions();

        $exists = collect($options)->pluck(0)->contains('domain');
        if (! $exists) {
            $options[] = ['+ domain', null, InputOption::VALUE_REQUIRED, 'The domain name where the controller will be created.'];
        }

        return $options;
    }

    /**
     * Create domain-scoped FormRequest classes.
     *
     * Returns an array of the file paths that were actually created by this method.
     * Leaves pre-existing files untouched.
     *
     * @param string $domain
     * @param string $modelFQCN  fully-qualified model class (or bare name); will use class_basename()
     * @return array<string> created file paths
     */
    protected function createDomainFormRequests(string $domain, string $modelFQCN): array
    {
        $modelName = class_basename($modelFQCN);
        $requests = [
            'Store' . $modelName . 'Request',
            'Update' . $modelName . 'Request',
        ];

        $requestsPath = app_path("Domain/{$domain}/Http/Requests");
        $createdFiles = [];
        $createdDir = false;

        if (! $this->files->isDirectory($requestsPath)) {
            // create directory and remember to possibly cleanup if rollback is needed
            $this->files->makeDirectory($requestsPath, 0755, true);
            $this->info("+ Created directory: {$requestsPath}");
            $createdDir = true;
        }

        foreach ($requests as $req) {
            $filePath = $requestsPath . DIRECTORY_SEPARATOR . $req . '.php';
            if (! $this->files->exists($filePath)) {
                $stub = $this->formRequestStub($domain, $req);
                $this->files->put($filePath, $stub);
                $this->info("Created Request: {$filePath}");
                $createdFiles[] = $filePath;
            } else {
                $this->comment("- Request already exists, skipping: {$filePath}");
            }
        }

        // if after this both files exist (created or pre-existing), mark createdRequests true
        $bothExist = $this->files->exists($requestsPath . DIRECTORY_SEPARATOR . $requests[0] . '.php')
            && $this->files->exists($requestsPath . DIRECTORY_SEPARATOR . $requests[1] . '.php');

        $this->createdRequests = $bothExist;

        // return only files we actually created (caller uses this to rollback on failure)
        return $createdFiles;
    }

    /**
     * Return a simple FormRequest stub contents for the given domain and class.
     *
     * Note: we keep this minimal and framework-compatible so Laravel versions behave.
     *
     * @param string $domain
     * @param string $class
     * @return string
     */
    protected function formRequestStub(string $domain, string $class): string
    {
        return <<<PHP
<?php

namespace App\\Domain\\{$domain}\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

class {$class} extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
}

PHP;
    }
}
