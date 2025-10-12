<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;

/**
 * MakeDomainAction
 *
 * Production-ready Artisan command to scaffold Action classes inside a Domain:
 *   app/Domain/{Domain}/Actions/{Name}.php
 *
 * Conventions & behavior:
 * - Requires --domain (domain folder must exist).
 * - `name` argument required (class name). If you prefer, you can append "Action" or skip it.
 * - By default creates a non-invokable action with a `handle` method.
 * - Use `--invokable` to create an invokable (__invoke) action.
 * - `--method` to customize method name (default: handle).
 * - `--model` to add model import/type-hint (accepts FQCN or short name -> resolves to App\Domain\{Domain}\Models\{Model}).
 * - `--request` to add FormRequest import/type-hint (accepts name or infer 'Store{Name}Request'/'Update{Name}Request' if value 'auto').
 * - `--queued` to scaffold ShouldQueue signature (adds imports and implements ShouldQueue).
 * - `--tests` to scaffold a simple unit test in tests/Unit/Domain/{Domain}/Actions.
 * - `--stub` to point to custom stub file (optional).
 * - `--force` to overwrite existing files.
 *
 * Notes:
 * - We don't redeclare $files property (inherited from GeneratorCommand).
 * - getStub() returns a valid path to satisfy parent API but file content is built via buildActionContent().
 */
class MakeDomainAction extends GeneratorCommand
{
    protected $name = 'make:d-action';
    protected $description = 'Create an Action class inside a Domain (domain required)';
    protected $type = 'Action';

    public function __construct(Filesystem $files)
    {
        parent::__construct($files);
    }

    /**
     * Execute the console command.
     *
     * Flow:
     *  - validate domain & name
     *  - normalize class name (optionally append 'Action')
     *  - resolve model/request FQCNs if provided
     *  - prepare directory and write files (main action, optional test)
     *  - rollback on write failure
     */
    public function handle()
    {
        // 1) domain validation
        $domainOption = $this->option('domain');
        if (! $domainOption) {
            $this->error('The --domain option is required. Example: --domain=Blog');
            return 1;
        }
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $domainOption)) {
            $this->error('Invalid domain name. Allowed characters: letters, numbers, underscore, dash.');
            return 1;
        }
        $domain = Str::studly($domainOption);
        $domainPath = app_path("Domain/{$domain}");
        if (! $this->files->isDirectory($domainPath)) {
            $this->error("Domain directory not found: app/Domain/{$domain}. Create it first.");
            return 1;
        }

        // 2) name argument & normalization
        $nameArg = trim((string) $this->argument('name'));
        if ($nameArg === '') {
            $this->error('You must provide an action name. Example: CreatePost or CreatePostAction');
            return 1;
        }
        $className = Str::studly($nameArg);
        // optional convention: append 'Action' if not present (keeps names explicit)
        if (! Str::endsWith($className, 'Action')) {
            $className = $className; // keep raw; not force-appending to give flexibility
            // NOTE: we don't forcibly append "Action" — developer choice. If you prefer forcing, change here.
        }

        // 3) options
        $invokable = (bool) $this->option('invokable');
        $method = $this->option('method') ?: 'handle';
        $queued = (bool) $this->option('queued');
        $force = (bool) $this->option('force');
        $createTests = (bool) $this->option('tests');
        $customStub = $this->option('stub');

        // 4) resolve model (optional)
        $modelOption = $this->option('model');
        $modelFQCN = null;
        $modelBasename = null;
        if ($modelOption) {
            $modelOption = trim($modelOption);
            if (Str::startsWith($modelOption, '\\')) {
                $modelOption = ltrim($modelOption, '\\');
            }
            if (Str::contains($modelOption, '\\') || Str::startsWith($modelOption, 'App\\')) {
                $modelFQCN = Str::start($modelOption, 'App\\');
                $modelBasename = class_basename($modelFQCN);
            } else {
                $modelBasename = Str::studly($modelOption);
                $modelFQCN = "App\\Domain\\{$domain}\\Models\\{$modelBasename}";
            }
            // warn if model file not found (non-blocking)
            $mPath = base_path('app') . '/' . str_replace('\\', '/', Str::after($modelFQCN, 'App\\')) . '.php';
            if (! $this->files->exists($mPath)) {
                $this->warn("Model file not found at expected path: {$mPath}. Action will still be created.");
            }
        }

        // 5) resolve request (optional)
        $requestOption = $this->option('request');
        $requestFQCN = null;
        $requestBasename = null;
        if ($requestOption) {
            $requestOption = trim($requestOption);
            if (Str::lower($requestOption) === 'auto') {
                // infer Store{Base}Request by model or name
                $base = $modelBasename ?: (Str::endsWith($className, 'Action') ? Str::substr($className, 0, -6) : $className);
                $requestBasename = "Store" . Str::studly($base) . "Request";
                $requestFQCN = "App\\Domain\\{$domain}\\Http\\Requests\\{$requestBasename}";
            } else {
                $requestBasename = Str::studly($requestOption);
                $requestBasename = Str::endsWith($requestBasename, 'Request') ? $requestBasename : $requestBasename . 'Request';
                $requestFQCN = "App\\Domain\\{$domain}\\Http\\Requests\\{$requestBasename}";
            }
            // warn if not present (non-blocking)
            $rPath = app_path(str_replace('\\', '/', Str::after($requestFQCN, 'App\\'))) . '.php';
            if (! $this->files->exists($rPath)) {
                $this->warn("Request file not found at expected path: {$rPath}. You may create it or adjust --request option.");
            }
        }

        // 6) prepare target path
        $qualified = $this->qualifyClass($className); // uses getDefaultNamespace
        $targetPath = $this->getPath($qualified);
        $dir = dirname($targetPath);

        if ($this->files->exists($targetPath) && ! $force) {
            $this->error("Action already exists at: {$targetPath}. Use --force to overwrite.");
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

        // 7) build content (prefer custom stub -> project stubs -> inline)
        try {
            $content = $this->buildActionContent(
                $domain,
                $className,
                $invokable,
                $method,
                $modelFQCN,
                $requestFQCN,
                $queued,
                $customStub
            );
        } catch (\Throwable $e) {
            $this->error('Failed to build action content: ' . $e->getMessage());
            return 1;
        }

        // 8) write file(s) with rollback
        $created = [];
        try {
            $this->files->put($targetPath, $content);
            $created[] = $targetPath;
            $this->info("Action created: {$targetPath}");
        } catch (\Throwable $e) {
            foreach ($created as $f) {
                try { $this->files->delete($f); } catch (\Throwable $_) {}
            }
            $this->error("Failed to write action file: " . $e->getMessage());
            return 1;
        }

        // 9) optionally scaffold a basic unit test
        if ($createTests) {
            $testDir = base_path("tests/Unit/Domain/{$domain}/Actions");
            $testClass = $className . 'Test';
            $testPath = base_path("tests/Unit/Domain/{$domain}/Actions/{$testClass}.php");
            if (! $this->files->isDirectory($testDir)) {
                $this->files->makeDirectory($testDir, 0755, true);
            }
            if ($this->files->exists($testPath) && ! $force) {
                $this->comment("Test already exists, skipping: {$testPath}");
            } else {
                $testContent = $this->buildTestContent($domain, $className, $modelBasename);
                $this->files->put($testPath, $testContent);
                $this->info("Test created: {$testPath}");
            }
        }

        $this->info('Done. Use the Action from controllers, jobs or CLI. Keep controllers thin.');

        return 0;
    }

    /**
     * Default namespace for actions inside the domain.
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        $domain = Str::studly($this->option('domain') ?: 'Unknown');
        return "{$rootNamespace}\\Domain\\{$domain}\\Actions";
    }

    /**
     * Build action file content.
     *
     * Order of preference for stub:
     * 1) --stub argument if provided and exists
     * 2) project-level stubs: stubs/action.invokable.stub or stubs/action.stub
     * 3) inline fallback template
     *
     * @return string
     */
    protected function buildActionContent(
        string $domain,
        string $className,
        bool $invokable,
        string $method,
        ?string $modelFQCN,
        ?string $requestFQCN,
        bool $queued,
        ?string $customStub
    ): string {
        // 1) custom stub
        if ($customStub && $this->files->exists($customStub)) {
            $stub = $this->files->get($customStub);
            return $this->replaceActionPlaceholders($stub, $domain, $className, $invokable, $method, $modelFQCN, $requestFQCN, $queued);
        }

        // 2) project stubs
        $projectInvokable = base_path('stubs/action.invokable.stub');
        $projectAction = base_path('stubs/action.stub');

        if ($invokable && $this->files->exists($projectInvokable)) {
            $stub = $this->files->get($projectInvokable);
            return $this->replaceActionPlaceholders($stub, $domain, $className, $invokable, $method, $modelFQCN, $requestFQCN, $queued);
        }

        if (! $invokable && $this->files->exists($projectAction)) {
            $stub = $this->files->get($projectAction);
            return $this->replaceActionPlaceholders($stub, $domain, $className, $invokable, $method, $modelFQCN, $requestFQCN, $queued);
        }

        // 3) inline fallback
        $namespace = "App\\Domain\\{$domain}\\Actions";

        $uses = [];
        $extraTraits = [];
        $implements = [];

        if ($queued) {
            $uses[] = 'Illuminate\\Contracts\\Queue\\ShouldQueue';
            $uses[] = 'Illuminate\\Bus\\Queueable';
            $implements[] = 'implements ShouldQueue';
            $extraTraits[] = 'use Queueable;';
        }

        if ($modelFQCN) {
            $uses[] = $modelFQCN;
        }
        if ($requestFQCN) {
            $uses[] = $requestFQCN;
        }

        // unique & format uses
        $uses = array_unique($uses);
        $useLines = '';
        foreach ($uses as $u) {
            $useLines .= "use {$u};\n";
        }

        $implStr = $implements ? ' ' . implode(' ', $implements) : '';
        $traitsStr = ! empty($extraTraits) ? "    " . implode("\n    ", $extraTraits) . "\n\n" : '';

        // build method signature/type-hints
        $param = $requestFQCN ? (class_basename($requestFQCN) . ' $request') : ($modelFQCN ? (class_basename($modelFQCN) . ' $model') : '');
        $returnType = ''; // leave flexible; developer can add

        if ($invokable) {
            $methodDecl = "public function __invoke(" . ($param ?: '') . "){$returnType}";
            $methodBody = $this->invokableMethodBody($className, $param, $modelFQCN);
        } else {
            $methodDecl = "public function {$method}(" . ($param ?: '') . "){$returnType}";
            $methodBody = $this->handleMethodBody($className, $method, $param, $modelFQCN);
        }

        $modelComment = $modelFQCN ? " * Model: " . class_basename($modelFQCN) . "\n" : '';

        $class = <<<PHP
<?php

namespace {$namespace};

{$useLines}use Illuminate\\Support\\Facades\\Log;

class {$className}{$implStr}
{
{$traitsStr}    /**
     * Action: {$className}
     *
     {$modelComment}     * Keep Actions single-responsibility and framework-agnostic.
     */
    {$methodDecl}
    {
{$methodBody}    }
}

PHP;

        return $class;
    }

    /**
     * Replace placeholders in a stub content.
     * Supported placeholders (example): {{NAMESPACE}}, {{CLASS}}, {{METHOD}}, {{INVOKABLE}}, {{MODEL_IMPORT}}, {{REQUEST_IMPORT}}, {{QUEUED}}
     */
    protected function replaceActionPlaceholders(
        string $stub,
        string $domain,
        string $className,
        bool $invokable,
        string $method,
        ?string $modelFQCN,
        ?string $requestFQCN,
        bool $queued
    ): string {
        $namespace = "App\\Domain\\{$domain}\\Actions";

        $modelImport = $modelFQCN ? "use {$modelFQCN};" : '';
        $requestImport = $requestFQCN ? "use {$requestFQCN};" : '';
        $queuedImport = $queued ? "use Illuminate\\Contracts\\Queue\\ShouldQueue;\\nuse Illuminate\\Bus\\Queueable;" : '';

        $replacements = [
            '{{NAMESPACE}}' => $namespace,
            '{{CLASS}}' => $className,
            '{{METHOD}}' => $method,
            '{{INVOKABLE}}' => $invokable ? 'true' : 'false',
            '{{MODEL_IMPORT}}' => $modelImport,
            '{{REQUEST_IMPORT}}' => $requestImport,
            '{{QUEUED_IMPORT}}' => $queued ? "use Illuminate\\Contracts\\Queue\\ShouldQueue;\nuse Illuminate\\Bus\\Queueable;" : '',
            '{{QUEUED_IMPL}}' => $queued ? 'implements ShouldQueue' : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Build default body for invokable method (string with indentation).
     */
    protected function invokableMethodBody(string $className, string $param, ?string $modelFQCN): string
    {
        $lines = [];
        if ($param !== '') {
            $lines[] = "        // Use validated request or model parameter. Transform DTO here if needed.";
            $lines[] = "        // Example: \$data = \$request->validated();";
            $lines[] = "";
        } else {
            $lines[] = "        // Business logic here.";
        }

        if ($modelFQCN) {
            $lines[] = "        // Example: update the model or perform action-related operations.";
            $lines[] = "        // \$model->update([...]);";
        } else {
            $lines[] = "        // Return result (model, DTO or primitive).";
        }

        $lines[] = "";
        $lines[] = "        return null; // replace with actual return value";

        return implode("\n", $lines) . "\n\n";
    }

    /**
     * Build default body for handle method (string with indentation).
     */
    protected function handleMethodBody(string $className, string $method, string $param, ?string $modelFQCN): string
    {
        $lines = [];
        $lines[] = "        // Business logic for {$method} method.";
        if ($param !== '') {
            $lines[] = "        // Example: if this is a FormRequest: \$data = \$request->validated();";
        }
        if ($modelFQCN) {
            $lines[] = "        // Example: operate on model: \$model->doSomething();";
        }
        $lines[] = "";
        $lines[] = "        return null; // return model/DTO/etc.";
        return implode("\n", $lines) . "\n\n";
    }

    /**
     * Build a simple PHPUnit test scaffold for the action.
     */
    protected function buildTestContent(string $domain, string $className, ?string $modelBasename): string
    {
        $namespace = "Tests\\Unit\\Domain\\{$domain}\\Actions";
        $actionFQCN = "App\\Domain\\{$domain}\\Actions\\{$className}";
        $modelImport = $modelBasename ? ("use App\\Domain\\{$domain}\\Models\\{$modelBasename};\n") : '';

        return <<<PHP
<?php

namespace {$namespace};

use PHPUnit\\Framework\\TestCase;
use {$actionFQCN};
{$modelImport}

class {$className}Test extends TestCase
{
    public function test_handle_or_invoke_runs()
    {
        // Basic smoke test skeleton. Replace with actual asserts and mocks.
        \$action = new {$className}();
        \$this->assertTrue(method_exists(\$action, '__invoke') || method_exists(\$action, 'handle'));
    }
}

PHP;
    }

    /**
     * Provide command options.
     */
    protected function getOptions()
    {
        return [
            ['domain', null, InputOption::VALUE_REQUIRED, 'Domain name (app/Domain/{Domain})'],
            ['invokable', 'i', InputOption::VALUE_NONE, 'Create an invokable action (use __invoke)'],
            //['method', null, InputOption::VALUE_REQUIRED, 'Method name for non-invokable action (default: handle)'],
            ['model', null, InputOption::VALUE_OPTIONAL, 'Optional model FQCN or short name to import and type-hint'],
            ['request', null, InputOption::VALUE_OPTIONAL, "Optional FormRequest name or 'auto' to infer Store{Base}Request"],
            //['queued', null, InputOption::VALUE_NONE, 'Make action queueable (implements ShouldQueue)'],
            //['tests', null, InputOption::VALUE_NONE, 'Generate a basic unit test scaffold for the action'],
            //['stub', null, InputOption::VALUE_OPTIONAL, 'Path to custom stub to use for action generation'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite files if they exist'],
        ];
    }

    /**
     * Define the name argument.
     */
    protected function getArguments()
    {
        return [
            ['name', 2, 'The Action class name (e.g. CreatePost or CreatePostAction)'],
        ];
    }

    /**
     * Minimal stub path getter to satisfy parent signature (we write files directly).
     *
     * @return string
     */
    protected function getStub(): string
    {
        // Prefer project-level stubs (not used directly but parent may expect a path)
        $projectInvokable = base_path('stubs/action.invokable.stub');
        $projectAction = base_path('stubs/action.stub');

        if ($this->files->exists($projectInvokable)) {
            return $projectInvokable;
        }
        if ($this->files->exists($projectAction)) {
            return $projectAction;
        }

        // fallback to this file (harmless)
        return __FILE__;
    }
}
