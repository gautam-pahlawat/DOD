<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * MakeDomainModel
 *
 * Create an Eloquent model inside a Domain (app/Domain/{Domain}/Models).
 *
 * Behavior highlights:
 * - Requires --domain and domain folder must exist.
 * - If model file exists and --force not given, abort early and do not create auxiliaries.
 * - Respects Laravel's model flags (-m, -f, -s, -p).
 * - Uses project stub if available (stubs/model.stub), otherwise vendor fallback.
 * - Adds HasFactory trait when factory requested.
 * - Post-processes factory/seeder to bind to domain model.
 */
class MakeDomainModel extends ModelMakeCommand
{
    protected $name = 'make:d-model';
    protected $description = 'Create a model inside a specific Domain (domain is required)';
    protected $type = 'Model';

    // store original intent flags (no type hints to avoid parent collision)
    protected $origMigration = false;
    protected $origFactory = false;
    protected $origSeed = false;
    protected $origPivot = false;

    /**
     * Prefer project-level stub if present, otherwise fallback to framework stub.
     *
     * @return string
     */
    protected function getStub()
    {
        $projectStub = base_path('stubs/model.stub');

        if ($this->files->exists($projectStub)) {
            return $projectStub;
        }

        return base_path('vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/model.stub');
    }

    /**
     * Default namespace for models inside the domain.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        $domain = $this->option('domain');

        if (! $domain) {
            // fallback; handle() will block if domain missing
            return $rootNamespace . '\\Domain\\Unknown\\Models';
        }

        $domain = Str::studly($domain);
        return $rootNamespace . "\\Domain\\{$domain}\\Models";
    }

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle()
    {
        $nameInput = $this->getNameInput();
        $domainOption = $this->option('domain');

        if (! $domainOption) {
            $this->error('* The --domain option is required.');
            return 1;
        }

        $domain = Str::studly($domainOption);
        $domainPath = app_path("Domain/{$domain}");

        if (! $this->files->isDirectory($domainPath)) {
            $this->error("* Domain folder not found: app/Domain/{$domain}. Create it first.");
            return 1;
        }

        // Pre-compute final qualified class and filesystem path
        $qualifiedClass = $this->qualifyClass($nameInput);
        $targetPath = $this->getPath($qualifiedClass);

        // If target exists and not forcing, abort early (do not create auxiliaries)
        if ($this->files->exists($targetPath) && ! $this->option('force')) {
            $this->error("* Model already exists at: {$targetPath}. Use --force to overwrite.");
            return 1;
        }

        // Save original user intent flags
        $this->origMigration = (bool) $this->option('migration');
        $this->origFactory   = (bool) $this->option('factory');
        $this->origSeed      = (bool) $this->option('seed');
        $this->origPivot     = (bool) $this->option('pivot');

        // Laravel-like behavior: if seed requested without factory, create factory implicitly
        if ($this->origSeed && ! $this->origFactory) {
            $this->comment('! --seed requested without --factory. Factory will be created implicitly.');
            $this->origFactory = true;
        }

        // Temporarily disable parent's auto-creation flags to avoid global/default locations creation
        $this->input->setOption('migration', false);
        $this->input->setOption('factory', false);
        $this->input->setOption('seed', false);
        $this->input->setOption('pivot', false);

        // Generate the model file via parent (buildClass will use $this->orig* flags)
        $parentResult = parent::handle();

        if ($parentResult !== 0 && $parentResult !== null) {
            $this->error('* Model generation failed.');
            return $parentResult;
        }

        // After model generated, perform auxiliary actions domain-aware
        $name = Str::studly($this->argument('name'));
        $modelFQCN = "App\\Domain\\{$domain}\\Models\\{$name}";

        // Migration
        if ($this->origMigration) {
            $this->info("> Creating migration...");
            $migrationCode = $this->callSilent('make:migration', [
                'name' => 'create_' . Str::snake(Str::pluralStudly($name)) . '_table',
                '--create' => Str::snake(Str::pluralStudly($name)),
            ]);
            if ($migrationCode === 0) {
                $this->info("+ Migration created.");
            } else {
                $this->error("* Failed to create migration.");
            }
        }

        // Factory
        if ($this->origFactory) {
            $factoryName = "{$name}Factory";
            $this->info("> Creating factory: {$factoryName} ...");

            // Pass model FQCN to try to get Laravel to write it; we'll post-process defensively
            $factoryCode = $this->callSilent('make:factory', [
                'name' => $factoryName,
                '--model' => $modelFQCN,
            ]);

            if ($factoryCode === 0) {
                $this->info("+ Factory generated: database/factories/{$factoryName}.php");
                $this->postProcessFactory($factoryName, $modelFQCN);
            } else {
                $this->error("* Failed to create factory: {$factoryName}");
            }
        }

        // Seeder
        if ($this->origSeed) {
            $seederName = "{$name}Seeder";
            $this->info("> Creating seeder: {$seederName} ...");

            $seederCode = $this->callSilent('make:seeder', [
                'name' => $seederName,
            ]);

            if ($seederCode === 0) {
                $this->info("+ Seeder created: database/seeders/{$seederName}.php");
                $this->postProcessSeeder($seederName, $modelFQCN, $domain);
            } else {
                $this->error("* Failed to create seeder: {$seederName}");
            }
        }

        $this->info("+ Model [{$name}] created successfully inside Domain [{$domain}].");

        return 0;
    }

    /**
     * Build the class contents of model.
     * Uses original user flags (not parent's temporarily disabled options) to decide what to inject.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        // Ensure class basename
        $classBasename = class_basename($name);

        // If factory requested by user originally, add HasFactory trait and import
        if ($this->origFactory) {
            if (! Str::contains($stub, 'HasFactory')) {
                $stub = preg_replace(
                    '/(namespace\s+[^\;]+;\s*)/',
                    "$1\nuse Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\n",
                    $stub,
                    1
                );
            }

            $stub = preg_replace(
                '/(class\s+' . preg_quote($classBasename, '/') . '\s+extends\s+Model\s*\{\s*)/',
                "$1\n    use HasFactory;\n",
                $stub,
                1
            );

            // Remove factory placeholders if present (we use HasFactory approach)
            $stub = str_replace(['{{ factoryImport }}', '{{ factory }}'], ['', ''], $stub);
        } else {
            // remove placeholders if not used
            $stub = str_replace(['{{ factoryImport }}', '{{ factory }}'], ['', ''], $stub);
        }

        // If pivot requested originally, insert incrementing flag safely
        if ($this->origPivot) {
            $stub = preg_replace(
                '/(class\s+' . preg_quote($classBasename, '/') . '\s+extends\s+Model\s*\{\s*)/',
                "$1\n    public \$incrementing = false;\n",
                $stub,
                1
            );
        }

        // Cleanup stray single-line comment left by stub replacements (if any)
        $stub = preg_replace('/\n\s*\/\/\s*\n/', "\n", $stub, 1);

        return $stub;
    }

    /**
     * Post-process a factory file to ensure it references the domain model class via protected $model
     * and imports the model class.
     *
     * @param string $factoryName e.g. "PostFactory"
     * @param string $modelFQCN e.g. "App\Domain\Test\Models\Post"
     * @return void
     */
    protected function postProcessFactory(string $factoryName, string $modelFQCN): void
    {
        $factoryPath = database_path("factories/{$factoryName}.php");

        if (! $this->files->exists($factoryPath)) {
            $this->comment("- Factory file not found to post-process: {$factoryPath}");
            return;
        }

        $content = $this->files->get($factoryPath);

        // If protected $model already present, assume good
        if (Str::contains($content, 'protected $model')) {
            $this->info("! Factory already contains protected \$model. No patch needed.");
            return;
        }

        $modelBasename = class_basename($modelFQCN);

        // Add model import after namespace if missing
        if (! Str::contains($content, "use {$modelFQCN};")) {
            $content = preg_replace(
                '/(namespace\s+Database\\\\Factories;\s*)/',
                "$1\nuse {$modelFQCN};\n",
                $content,
                1
            );
        }

        // Insert protected $model property after class opening
        $content = preg_replace(
            '/(class\s+' . preg_quote($factoryName, '/') . '\s+extends\s+Factory\s*\{\s*)/',
            "$1\n    /**\n     * The name of the factory's corresponding model.\n     *\n     * @var string\n     */\n    protected \$model = {$modelBasename}::class;\n",
            $content,
            1
        );

        $this->files->put($factoryPath, $content);
        $this->info("+ Factory patched to reference model: {$modelBasename}.");
    }

    /**
     * Post-process seeder: add model import and example factory usage if run() body is empty.
     * This method is resilient to presence/absence of return type (e.g. ": void").
     *
     * @param string $seederName
     * @param string $modelFQCN
     * @param string $domainStudly
     * @return void
     */
    protected function postProcessSeeder(string $seederPath, string $modelFQCN): void
    {
        $fullSeederPath = database_path("seeders/{$seederPath}.php");
        if (! $this->files->exists($fullSeederPath)) {
            $this->warn("- Seeder not found at: {$fullSeederPath}");
            return;
        }

        $content = $this->files->get($fullSeederPath);
        $modelBasename = class_basename($modelFQCN);

        // Ensure 'use Model' import exists
        if (! Str::contains($content, "use {$modelFQCN};")) {
            $content = preg_replace(
                '/(namespace\s+Database\\\\Seeders;\s*)/',
                "$1\nuse {$modelFQCN};\n",
                $content,
                1
            );
        }

        // Find run() method body
        $pattern = '/public\s+function\s+run\s*\(\s*\)\s*(?::\s*([\\\\\w]+))?\s*\{\s*([\s\S]*?)\n\s*\}/m';
        if (preg_match($pattern, $content, $matches)) {
            $returnType = isset($matches[1]) && $matches[1] ? $matches[1] : null;
            $inner = trim($matches[2] ?? '');

            // Only patch if run() is empty or has just comments
            if ($inner === '' || preg_match('/^\s*(\/\/|\/\*)/', $inner)) {
                $ret = $returnType ? ": {$returnType}" : '';
                $example  = "        // Example: create 10 {$modelBasename} records via factory\n";
                $example .= "        {$modelBasename}::factory()->count(10)->create();\n";

                $replacement = "public function run(){$ret}\n    {\n{$example}    }";

                $content = preg_replace($pattern, $replacement, $content, 1);
                $this->files->put($fullSeederPath, $content);
                $this->info("+ Seeder patched with example factory usage (short class name).");
            } else {
                $this->line("! Seeder run() already contains code. Skipped patching.");
            }
        } else {
            $this->warn("- Unable to find run() method in seeder for patching.");
        }

        $this->files->put($fullSeederPath, $content);
    }

    /**
     * Merge parent options and add domain option safely.
     *
     * @return array
     */
    protected function getOptions()
    {
        $options = parent::getOptions();

        $exists = collect($options)->pluck(0)->contains('domain');
        if (! $exists) {
            $options[] = ['domain', null, InputOption::VALUE_REQUIRED, 'The name of the Domain for this model (required)', null];
        }

        return $options;
    }
}
