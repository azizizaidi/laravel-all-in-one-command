<?php

namespace AziziZaidi\AllInOneCommand\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeFeatureCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:feature {name : The name of the feature}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new feature with all related files (Model, Migration, Controller, etc.)';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * @var array Stores the details of what to generate.
     */
    protected $featureDetails = [];

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rawName = $this->argument('name');

        // Validate input
        if (empty(trim($rawName))) {
            $this->error('Feature name cannot be empty.');
            return Command::INVALID;
        }

        // Validate name format
        if (!preg_match('/^[a-zA-Z_\/][a-zA-Z0-9_\/]*$/', $rawName)) {
            $this->error('Feature name must contain only letters, numbers, underscores, and forward slashes.');
            return Command::INVALID;
        }

        $name = Str::studly($rawName);
        $this->info("Creating a new feature: {$name}");

        try {
            $this->collectFeatureDetails($name);
            $this->displaySummary();

            if (!$this->confirm('Proceed? (Y/n)', true)) {
                $this->info('Operation cancelled.');
                return Command::INVALID;
            }

            $this->generateFiles($name);

            $this->info("Feature {$name} scaffolding generated successfully!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function collectFeatureDetails($name)
    {
        $this->featureDetails['name'] = $name;

        $this->featureDetails['model'] = $this->confirm('Generate Model?', true);
        $this->featureDetails['migration'] = $this->confirm('Generate Migration?', true);
        $this->featureDetails['factory'] = $this->confirm('Generate Factory?', true);

        if ($this->featureDetails['seeder'] = $this->confirm('Generate Seeder?', true)) {
            $this->featureDetails['add_to_database_seeder'] = $this->confirm('  > Add ' . $name . 'Seeder to DatabaseSeeder.php? (y/N)', false);
        } else {
            $this->featureDetails['add_to_database_seeder'] = false;
        }

        // Controller
        if ($this->featureDetails['controller'] = $this->confirm('Generate Controller?', true)) {
            $this->featureDetails['controller_type'] = $this->choice(
                '  > Controller Type (Resource/Invokable/Basic)',
                ['Resource', 'Invokable', 'Basic'],
                'Resource'
            );
            $defaultNamespace = 'App\\Http\\Controllers';
            if (Str::contains($name, '/')) { // Check if name has a subdirectory
                $parts = explode('/', $name);
                array_pop($parts); // Remove the feature name itself
                $subNamespace = implode('\\', array_map('Str::studly', $parts));
                $defaultNamespace .= '\\' . $subNamespace;
            }
            $this->featureDetails['controller_namespace'] = $this->ask('  > Namespace for Controller', $defaultNamespace);
        } else {
            $this->featureDetails['controller_type'] = null;
            $this->featureDetails['controller_namespace'] = null;
        }

        // Form Requests
        if ($this->featureDetails['form_requests'] = $this->confirm('Generate Form Requests (Store/Update)?', true)) {
            // Store request will be App\Http\Requests\StoreOrderRequest
            // Update request will be App\Http\Requests\UpdateOrderRequest
        }

        // Service Class
        if ($this->featureDetails['service'] = $this->confirm('Generate Service Class?', true)) {
            $this->featureDetails['service_interface'] = $this->confirm('  > Generate Service Interface and bind?', false);
        } else {
            $this->featureDetails['service_interface'] = false;
        }

        // Web Routes
        if ($this->featureDetails['web_routes'] = $this->confirm('Add Web Routes?', true)) {
            $defaultRoutePath = Str::plural(Str::kebab($name));
            $this->featureDetails['web_route_path'] = $this->ask('  > Route path', $defaultRoutePath);
        } else {
            $this->featureDetails['web_route_path'] = null;
        }

        // API Routes
        $this->featureDetails['api_routes'] = $this->confirm('Add API Routes?', false);
        // If API routes are chosen, we might ask for a path similar to web routes, or assume /api/ prefix.
        // For now, keeping it simple. If true, we'll add a resource route.

        // Tests
        if ($this->featureDetails['tests'] = $this->confirm('Generate Tests?', true)) {
            $testChoice = $this->choice(
                '  > Test types',
                ['Unit', 'Feature', 'Both (Unit and Feature)'],
                'Both (Unit and Feature)'
            );

            // Convert choice to array format
            switch ($testChoice) {
                case 'Unit':
                    $this->featureDetails['test_types'] = ['Unit'];
                    break;
                case 'Feature':
                    $this->featureDetails['test_types'] = ['Feature'];
                    break;
                case 'Both (Unit and Feature)':
                default:
                    $this->featureDetails['test_types'] = ['Unit', 'Feature'];
                    break;
            }
        } else {
            $this->featureDetails['test_types'] = [];
        }

        // Policy
        if ($this->featureDetails['policy'] = $this->confirm('Generate Policy?', true)) {
            $this->featureDetails['register_policy'] = $this->confirm('  > Register Policy in AuthServiceProvider?', true);
        } else {
            $this->featureDetails['register_policy'] = false;
        }

        // Scheduled Task Command
        $this->featureDetails['scheduled_task'] = $this->confirm('Generate Scheduled Task Command?', false);
        // If yes, we might ask for command name/signature later or derive it.

        // Blade Views
        if ($this->featureDetails['views'] = $this->confirm('Generate Blade Views (CRUD)?', false)) {
            // We might ask for a subdirectory within resources/views or derive from feature name
            $this->featureDetails['view_path'] = Str::plural(Str::kebab($name)); // e.g., 'orders' for Order feature
        } else {
            $this->featureDetails['view_path'] = null;
        }
    }

    protected function displaySummary()
    {
        $this->info("\nSummary of files to be created:");
        $name = $this->featureDetails['name'];

        if ($this->featureDetails['model']) {
            $this->line("- app/Models/{$name}.php");
        }
        if ($this->featureDetails['migration']) {
            // Migration name will have a timestamp, so just a placeholder for now
            $this->line("- database/migrations/..._create_" . Str::snake($name) . "s_table.php");
        }
        if ($this->featureDetails['factory']) {
            $this->line("- database/factories/{$name}Factory.php");
        }
        if ($this->featureDetails['seeder']) {
            $this->line("- database/seeders/{$name}Seeder.php");
            if ($this->featureDetails['add_to_database_seeder']) {
                $this->line("  - Will attempt to add {$name}Seeder to DatabaseSeeder.php");
            }
        }
        if ($this->featureDetails['controller']) {
            $controllerName = Str::studly($name) . 'Controller';
            $namespace = $this->featureDetails['controller_namespace'] ? Str::studly(str_replace('/', '\\', $this->featureDetails['controller_namespace'])) : 'App\\Http\\Controllers';
            $fullControllerPath = str_replace('App\\', 'app\\', $namespace) . '/' . $controllerName . '.php';
            $this->line("- {$fullControllerPath} (Type: {$this->featureDetails['controller_type']})");
        }
        if ($this->featureDetails['form_requests']) {
            $requestNamespace = $this->featureDetails['controller_namespace'] ? Str::studly(str_replace('/', '\\', $this->featureDetails['controller_namespace'])) . '\\Requests' : 'App\\Http\\Requests';
            $requestNamespacePath = str_replace('App\\', 'app\\', $requestNamespace);

            $this->line("- {$requestNamespacePath}/Store{$name}Request.php");
            $this->line("- {$requestNamespacePath}/Update{$name}Request.php");
        }
        if ($this->featureDetails['service']) {
            $serviceName = Str::studly($name) . 'Service';
            // Assuming services are in App/Services, potentially with subdirectories based on controller namespace
            $serviceBaseNamespace = 'App\\Services';
            $serviceSubPath = '';
            if($this->featureDetails['controller_namespace'] && $this->featureDetails['controller_namespace'] !== 'App\\Http\\Controllers'){
                $subNamespace = str_replace('App\\Http\\Controllers\\', '', $this->featureDetails['controller_namespace']);
                $serviceSubPath = str_replace('\\', '/', $subNamespace) . '/';
            }
            $this->line("- app/Services/{$serviceSubPath}{$serviceName}.php");
            if ($this->featureDetails['service_interface']) {
                $this->line("- app/Services/{$serviceSubPath}Contracts/{$serviceName}Interface.php");
                $this->line("  - Will attempt to bind interface in AppServiceProvider.php");
            }
        }
        if ($this->featureDetails['web_routes']) {
            $controllerName = Str::studly($name) . 'Controller';
            $namespace = $this->featureDetails['controller_namespace'] ? Str::studly(str_replace('/', '\\', $this->featureDetails['controller_namespace'])) : 'App\\Http\\Controllers';
            $fullControllerClass = $namespace . '\\' . $controllerName;
            $this->line("- Will attempt to add web routes for '{$this->featureDetails['web_route_path']}' to routes/web.php (Controller: {$fullControllerClass})");
        }
        if ($this->featureDetails['api_routes']) {
            $controllerName = Str::studly($name) . 'Controller';
            $namespace = $this->featureDetails['controller_namespace'] ? Str::studly(str_replace('/', '\\', $this->featureDetails['controller_namespace'])) : 'App\\Http\\Controllers';
            $fullControllerClass = $namespace . '\\' . $controllerName;
            $apiPath = Str::plural(Str::kebab($name));
            $this->line("- Will attempt to add API resource routes for 'api/{$apiPath}' to routes/api.php (Controller: {$fullControllerClass})");
        }
        if ($this->featureDetails['tests'] && !empty($this->featureDetails['test_types'])) {
            // Ensure test_types is properly handled
            $testTypes = [];
            if (isset($this->featureDetails['test_types'])) {
                if (is_array($this->featureDetails['test_types'])) {
                    $testTypes = $this->featureDetails['test_types'];
                } elseif (is_string($this->featureDetails['test_types'])) {
                    $testTypes = explode(',', $this->featureDetails['test_types']);
                }
            }

            // Clean up test types
            $testTypes = array_map(function($type) {
                return trim(ucfirst(strtolower($type)));
            }, $testTypes);

            if (in_array('Unit', $testTypes)) {
                $this->line("- tests/Unit/{$name}Test.php");
            }
            if (in_array('Feature', $testTypes)) {
                $this->line("- tests/Feature/{$name}Test.php");
            }
        }
        if ($this->featureDetails['policy']) {
            $this->line("- app/Policies/{$name}Policy.php");
            if ($this->featureDetails['register_policy']) {
                $this->line("  - Will attempt to register {$name}Policy in AuthServiceProvider.php");
            }
        }
        if ($this->featureDetails['scheduled_task']) {
            $this->line("- app/Console/Commands/{$name}ScheduledCommand.php (Placeholder name)");
        }
        if ($this->featureDetails['views']) {
            $viewBasePath = "resources/views/{$this->featureDetails['view_path']}";
            $this->line("- {$viewBasePath}/index.blade.php");
            $this->line("- {$viewBasePath}/create.blade.php");
            $this->line("- {$viewBasePath}/edit.blade.php");
            $this->line("- {$viewBasePath}/show.blade.php");
            // Potentially a _form.blade.php partial
        }
        $this->output->newLine();
    }

    protected function generateFiles($name)
    {
        if ($this->featureDetails['model']) {
            $this->generateModel($name);
        }
        if ($this->featureDetails['migration']) {
            $this->generateMigration($name);
        }
        if ($this->featureDetails['factory']) {
            $this->generateFactory($name);
        }
        if ($this->featureDetails['seeder']) {
            $this->generateSeeder($name);
            if ($this->featureDetails['add_to_database_seeder']) {
                $this->addSeederToDatabaseSeeder($name);
            }
        }
        if ($this->featureDetails['controller']) {
            $this->generateController($name);
        }
        if ($this->featureDetails['form_requests']) {
            $this->generateFormRequests($name);
        }
        if ($this->featureDetails['service']) {
            $this->generateService($name);
            if ($this->featureDetails['service_interface']) {
                $this->generateServiceInterface($name);
                $this->bindServiceInterface($name);
            }
        }
        if ($this->featureDetails['web_routes']) {
            $this->generateWebRoutes($name);
        }
        if ($this->featureDetails['api_routes']) {
            $this->generateApiRoutes($name);
        }
        if ($this->featureDetails['tests'] && !empty($this->featureDetails['test_types'])) {
            $this->generateTests($name);
        }
        if ($this->featureDetails['policy']) {
            $this->generatePolicy($name);
            if ($this->featureDetails['register_policy']) {
                $this->registerPolicy($name);
            }
        }
        if ($this->featureDetails['scheduled_task']) {
            $this->generateScheduledTask($name);
        }
        if ($this->featureDetails['views']) {
            $this->generateViews($name);
        }
    }

    protected function generateModel($name)
    {
        $this->line("Generating Model: app/Models/{$name}.php");

        try {
            $modelPath = app_path("Models/{$name}.php");
            $this->makeDirectory(dirname($modelPath));

            if ($this->files->exists($modelPath)) {
                $this->warn("Model {$name} already exists. Skipping.");
                return;
            }

            $stub = "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass {$name} extends Model\n{\n    use HasFactory;\n\n    protected \$fillable = [\n        // Add your fillable fields here\n    ];\n\n    protected \$casts = [\n        // Add your casts here\n    ];\n}\n";
            $this->files->put($modelPath, $stub);
            $this->info("Model {$name} created successfully.");
        } catch (\Exception $e) {
            $this->error("Failed to create model {$name}: " . $e->getMessage());
        }
    }

    protected function generateMigration($name)
    {
        $tableName = Str::snake(Str::plural($name));
        $migrationName = 'create_' . $tableName . '_table';
        $this->line("Generating Migration: database/migrations/..._{$migrationName}.php");

        try {
            $result = $this->call('make:migration', [
                'name' => $migrationName,
                '--create' => $tableName
            ]);

            if ($result === 0) {
                $this->info("Migration {$migrationName} created successfully.");
            } else {
                $this->warn("Migration creation may have failed or already exists.");
            }
        } catch (\Exception $e) {
            $this->error("Failed to create migration {$migrationName}: " . $e->getMessage());
        }
    }

    protected function generateFactory($name)
    {
        $this->line("Generating Factory: database/factories/{$name}Factory.php");
        // Artisan command call: php artisan make:factory OrderFactory --model=Order
        $this->call('make:factory', [
            'name' => "{$name}Factory",
            '--model' => $name
        ]);
    }

    protected function generateSeeder($name)
    {
        $this->line("Generating Seeder: database/seeders/{$name}Seeder.php");
        // Artisan command call: php artisan make:seeder OrderSeeder
        $this->call('make:seeder', ['name' => "{$name}Seeder"]);
    }

    protected function addSeederToDatabaseSeeder($name)
    {
        $this->line("Attempting to add {$name}Seeder to DatabaseSeeder.php");
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');

        if (!$this->files->exists($databaseSeederPath)) {
            $this->warn("DatabaseSeeder.php not found at {$databaseSeederPath}. Skipping.");
            return;
        }

        $content = $this->files->get($databaseSeederPath);
        // $seederCall = "\\App\\Models\\{$name}::factory(10)->create(); // Example, adjust as needed\n        // \$this->call({$name}Seeder::class);"; // More common way
        $seederClassCall = "        \$this->call(\App\\Seeders\\{$name}Seeder::class);"; // Corrected namespace for seeder

        if (Str::contains($content, "{$name}Seeder::class")) {
            $this->info("{$name}Seeder already exists in DatabaseSeeder.php.");
            return;
        }

        // Try to add before the last `}` in the run method or at a sensible place
        $runMethodSignature = 'public function run(): void';
        $runMethodPosition = strpos($content, $runMethodSignature);

        if ($runMethodPosition === false) {
            $this->warn("Could not automatically find the run() method in DatabaseSeeder.php. Please add {$name}Seeder call manually.");
            return;
        }

        $commentToInsertBefore = '// \App\Models\User::factory(10)->create();';
        $commentPosition = strpos($content, $commentToInsertBefore, $runMethodPosition);

        $newContent = $content; // Initialize with original content

        if ($commentPosition !== false) {
            // Insert before the example user factory call
            $lines = explode("\n", $content);
            $insertLineNum = -1;
            foreach ($lines as $i => $line) {
                if (strpos($line, $commentToInsertBefore) !== false) {
                    $insertLineNum = $i;
                    break;
                }
            }
            if ($insertLineNum !== -1) {
                preg_match('/^(\s*)/', $lines[$insertLineNum], $matches);
                $indentation = $matches[0] ?? '        ';
                array_splice($lines, $insertLineNum, 0, $indentation . $seederClassCall);
                $newContent = implode("\n", $lines);
            }
        } else {
            // Fallback: try to insert before the closing brace of the run method
            $runMethodOpeningBrace = strpos($content, '{', $runMethodPosition + strlen($runMethodSignature));
            if ($runMethodOpeningBrace !== false) {
                // Find the matching closing brace for the run method
                $openBraces = 0;
                $currentPosition = $runMethodOpeningBrace;
                $closingBracePosition = -1;
                while ($currentPosition < strlen($content)) {
                    if ($content[$currentPosition] === '{') {
                        $openBraces++;
                    } elseif ($content[$currentPosition] === '}') {
                        $openBraces--;
                        if ($openBraces === 0) {
                            $closingBracePosition = $currentPosition;
                            break;
                        }
                    }
                    $currentPosition++;
                }

                if ($closingBracePosition !== -1) {
                    $lines = explode("\n", $content);
                    $insertLineNum = -1;
                    // Find the line number of the closing brace
                    $tempCharCount = 0;
                    for($i=0; $i < count($lines); $i++){
                        $tempCharCount += strlen($lines[$i]) + 1; // +1 for newline
                        if($tempCharCount > $closingBracePosition){
                            $insertLineNum = $i;
                            break;
                        }
                    }

                    if($insertLineNum !== -1){
                        preg_match('/^(\s*)/', $lines[$insertLineNum > 0 ? $insertLineNum -1 : $insertLineNum], $matches);
                        $indentation = $matches[0] ?? '        ';
                         // If the closing brace is on its own line, insert before it
                        if(trim($lines[$insertLineNum]) === '}'){
                             array_splice($lines, $insertLineNum, 0, $indentation . $seederClassCall);
                        } else {
                            // If closing brace is not alone, it's more complex, try to find previous line's indent
                             $prevLineIndent = ($insertLineNum > 0) ? (preg_match('/^(\s*)/', $lines[$insertLineNum-1], $m) ? $m[0] : '        ') : '        ';
                             array_splice($lines, $insertLineNum, 0, $prevLineIndent . $seederClassCall);
                        }
                        $newContent = implode("\n", $lines);
                    } else {
                        $this->warn("Could not reliably find the insertion point within DatabaseSeeder.php's run() method. Please add {$name}Seeder call manually.");
                    }
                } else {
                    $this->warn("Could not find the closing brace of the run() method in DatabaseSeeder.php. Please add {$name}Seeder call manually.");
                }
            } else {
                 $this->warn("Could not find the run() method's opening brace in DatabaseSeeder.php. Please add {$name}Seeder call manually.");
            }
        }

        if ($newContent !== $content && !Str::contains($content, "{$name}Seeder::class")) { // Check again to be sure
            $this->files->put($databaseSeederPath, $newContent);
            $this->info("Successfully added {$name}Seeder to DatabaseSeeder.php.");
        } else if (!Str::contains($this->files->get($databaseSeederPath), "{$name}Seeder::class")) { // Read fresh content
             $this->warn("Failed to add {$name}Seeder to DatabaseSeeder.php automatically. Please add it manually: \$this->call(\\App\\Seeders\\{$name}Seeder::class);");
        }
    }

    protected function generateController($name)
    {
        $controllerName = Str::studly($name) . 'Controller';
        // Use the stored namespace, ensuring it's properly formatted
        $rawNamespace = $this->featureDetails['controller_namespace'] ?? 'App\\Http\\Controllers';
        // Convert to StudlyCase and ensure backslashes
        $namespace = implode('\\', array_map('Str::studly', explode('\\', str_replace('/', '\\', $rawNamespace))));

        $fullControllerName = $namespace . '\\' . $controllerName;

        $this->line("Generating Controller: {$fullControllerName}.php");

        $modelNamespace = 'App\\Models'; // Default model namespace
        // If controller namespace has subdirectories, model might too (this is an assumption)
        if (str_starts_with($namespace, 'App\\Http\\Controllers\\')) {
            $subNamespace = str_replace('App\\Http\\Controllers\\', '', $namespace);
            if (!empty($subNamespace)) {
                // Potentially, model is in App\Models\SubNamespace\ModelName
                // For now, let's keep it simple or make this configurable later
            }
        }

        $options = [
            'name' => $fullControllerName,
            // make:controller expects model with its namespace if not in App\Models
            // However, if the model is in App\Models, just the name is fine.
            // Let's assume App\Models for now for simplicity with the artisan command.
            '--model' => $name, // Assumes model is in App\Models, or make:controller resolves it
        ];
        
        // If the model is namespaced (e.g. App\Models\Shop\Order), we might need to pass the full path
        // $options['--model'] = $modelNamespace . '\\' . $name; // More explicit if needed


        if ($this->featureDetails['controller_type'] === 'Resource') {
            $options['--resource'] = true;
        } elseif ($this->featureDetails['controller_type'] === 'Invokable') {
            $options['--invokable'] = true;
        }
        // 'Basic' is the default if neither --resource nor --invokable is passed

        $this->call('make:controller', $options);
    }

    protected function generateFormRequests($name)
    {
        // Determine base namespace for requests, mirroring controller structure under App\Http\Requests
        $rawControllerNamespace = $this->featureDetails['controller_namespace'] ?? 'App\\Http\\Controllers';
        $baseRequestNamespace = str_replace('App\\Http\\Controllers', 'App\\Http\\Requests', $rawControllerNamespace);
        // Ensure it's StudlyCase and uses backslashes
        $baseRequestNamespace = implode('\\', array_map('Str::studly', explode('\\', str_replace('/', '\\', $baseRequestNamespace))));


        $storeRequestName = $baseRequestNamespace . '\\Store' . Str::studly($name) . 'Request';
        $updateRequestName = $baseRequestNamespace . '\\Update' . Str::studly($name) . 'Request';

        $this->line("Generating Form Request: {$storeRequestName}.php");
        $this->call('make:request', ['name' => $storeRequestName]);

        $this->line("Generating Form Request: {$updateRequestName}.php");
        $this->call('make:request', ['name' => $updateRequestName]);
    }

    protected function generateService($name)
    {
        $serviceName = Str::studly($name) . 'Service';
        $rawControllerNamespace = $this->featureDetails['controller_namespace'] ?? 'App\\Http\\Controllers';
        
        $baseServiceNamespace = 'App\\Services';
        $serviceSubNamespaceParts = [];

        // Derive sub-namespace for service from controller namespace
        if (str_starts_with($rawControllerNamespace, 'App\\Http\\Controllers\\')) {
            $controllerSubNamespace = str_replace('App\\Http\\Controllers\\', '', $rawControllerNamespace);
            if (!empty($controllerSubNamespace)) {
                $serviceSubNamespaceParts = explode('\\', str_replace('/', '\\', $controllerSubNamespace));
                $serviceSubNamespaceParts = array_map('Str::studly', $serviceSubNamespaceParts);
            }
        }
        
        $fullServiceNamespace = $baseServiceNamespace;
        if (!empty($serviceSubNamespaceParts)) {
            $fullServiceNamespace .= '\\' . implode('\\', $serviceSubNamespaceParts);
        }

        $servicePath = app_path('Services/' . (!empty($serviceSubNamespaceParts) ? implode('/', $serviceSubNamespaceParts) . '/' : '') . $serviceName . '.php');

        $this->line("Generating Service: {$servicePath}");
        $this->makeDirectory(dirname($servicePath));

        $stub = "<?php\n\nnamespace {$fullServiceNamespace};\n\n";
        if ($this->featureDetails['service_interface']) {
            $interfaceName = $serviceName . 'Interface';
            $interfaceNamespace = $fullServiceNamespace . '\\Contracts';
            $stub .= "use {$interfaceNamespace}\\{$interfaceName};\n\n";
            $stub .= "class {$serviceName} implements {$interfaceName}\n";
        } else {
            $stub .= "class {$serviceName}\n";
        }
        $stub .= "{\n    /**\n     * Handle the service logic for {$name}\n     *\n     * @return mixed\n     */\n    public function handle()\n    {\n        // Implement your service logic here\n    }\n}\n";

        if ($this->files->exists($servicePath)) {
            $this->warn("Service {$serviceName} already exists. Skipping.");
            return;
        }

        $this->files->put($servicePath, $stub);
    }

    protected function generateServiceInterface($name)
    {
        $serviceName = Str::studly($name) . 'Service';
        $interfaceName = $serviceName . 'Interface';
        $rawControllerNamespace = $this->featureDetails['controller_namespace'] ?? 'App\\Http\\Controllers';

        $baseServiceNamespace = 'App\\Services';
        $serviceSubNamespaceParts = [];

        if (str_starts_with($rawControllerNamespace, 'App\\Http\\Controllers\\')) {
            $controllerSubNamespace = str_replace('App\\Http\\Controllers\\', '', $rawControllerNamespace);
            if (!empty($controllerSubNamespace)) {
                $serviceSubNamespaceParts = explode('\\', str_replace('/', '\\', $controllerSubNamespace));
                $serviceSubNamespaceParts = array_map('Str::studly', $serviceSubNamespaceParts);
            }
        }
        
        $fullInterfaceNamespace = $baseServiceNamespace;
        if (!empty($serviceSubNamespaceParts)) {
            $fullInterfaceNamespace .= '\\' . implode('\\', $serviceSubNamespaceParts);
        }
        $fullInterfaceNamespace .= '\\Contracts';


        $interfacePath = app_path('Services/' . (!empty($serviceSubNamespaceParts) ? implode('/', $serviceSubNamespaceParts) . '/' : '') . 'Contracts/' . $interfaceName . '.php');

        $this->line("Generating Service Interface: {$interfacePath}");
        $this->makeDirectory(dirname($interfacePath));

        if ($this->files->exists($interfacePath)) {
            $this->warn("Service interface {$interfaceName} already exists. Skipping.");
            return;
        }

        $stub = "<?php\n\nnamespace {$fullInterfaceNamespace};\n\ninterface {$interfaceName}\n{\n    /**\n     * Handle the service logic for {$name}\n     *\n     * @return mixed\n     */\n    public function handle();\n}\n";
        $this->files->put($interfacePath, $stub);
    }

    protected function bindServiceInterface($name)
    {
        $this->line("Attempting to bind service interface in AppServiceProvider.php");
        $appServiceProviderPath = app_path('Providers/AppServiceProvider.php');

        if (!$this->files->exists($appServiceProviderPath)) {
            $this->warn("AppServiceProvider.php not found at {$appServiceProviderPath}. Skipping binding.");
            return;
        }

        $serviceName = Str::studly($name) . 'Service';
        $interfaceName = $serviceName . 'Interface';
        
        $rawControllerNamespace = $this->featureDetails['controller_namespace'] ?? 'App\\Http\\Controllers';
        $baseServiceNamespace = 'App\\Services';
        $serviceSubNamespaceParts = [];

        if (str_starts_with($rawControllerNamespace, 'App\\Http\\Controllers\\')) {
            $controllerSubNamespace = str_replace('App\\Http\\Controllers\\', '', $rawControllerNamespace);
            if (!empty($controllerSubNamespace)) {
                $serviceSubNamespaceParts = array_map('Str::studly', explode('\\', str_replace('/', '\\', $controllerSubNamespace)));
            }
        }

        $fullServiceNamespace = $baseServiceNamespace . (!empty($serviceSubNamespaceParts) ? '\\' . implode('\\', $serviceSubNamespaceParts) : '');
        $fullInterfaceNamespace = $fullServiceNamespace . '\\Contracts';

        $content = $this->files->get($appServiceProviderPath);

        $interfaceToImport = "use {$fullInterfaceNamespace}\\{$interfaceName};";
        $serviceToImport = "use {$fullServiceNamespace}\\{$serviceName};";
        $binding = "        \$this->app->bind({$interfaceName}::class, {$serviceName}::class);";

        if (Str::contains($content, $binding) && Str::contains($content, $interfaceToImport) && Str::contains($content, $serviceToImport)) {
            $this->info("Service interface already bound with correct imports in AppServiceProvider.php.");
            return;
        }

        $newContent = $content;
        $madeChanges = false;

        // Add use statements if not already present
        $useStatementsToAdd = [];
        if (!Str::contains($newContent, $interfaceToImport)) {
            $useStatementsToAdd[] = $interfaceToImport;
        }
        if (!Str::contains($newContent, $serviceToImport)) {
            $useStatementsToAdd[] = $serviceToImport;
        }

        if (!empty($useStatementsToAdd)) {
            $madeChanges = true;
            // Find the last use statement or namespace declaration
            $lastUsePosition = strrpos($newContent, "\nuse ");
            $namespacePosition = strpos($newContent, "\nnamespace ");
            $insertionPointUse = 0;

            if ($lastUsePosition !== false) {
                $endOfLastUseLine = strpos($newContent, ";", $lastUsePosition) + 1;
                $insertionPointUse = $endOfLastUseLine;
            } elseif ($namespacePosition !== false) { // if no 'use' but 'namespace' exists
                $endOfNamespaceLine = strpos($newContent, ";", $namespacePosition) + 1;
                $insertionPointUse = $endOfNamespaceLine;
            } else { // Fallback if no use or namespace, unlikely for AppServiceProvider
                $phpTagEnd = strpos($newContent, "<?php") + strlen("<?php");
                $insertionPointUse = $phpTagEnd;
            }
            $newContent = substr_replace($newContent, "\n" . implode("\n", $useStatementsToAdd), $insertionPointUse, 0);
        }


        // Add binding in the register method if not already present
        if (!Str::contains($newContent, $binding)) {
            $madeChanges = true;
            $registerMethodPosition = strpos($newContent, 'public function register()');
            if ($registerMethodPosition !== false) {
                $openingBracePosition = strpos($newContent, '{', $registerMethodPosition);
                if ($openingBracePosition !== false) {
                    // Insert after the opening brace, typically on a new line with indentation
                    $linesBeforeInsert = explode("\n", substr($newContent, 0, $openingBracePosition + 1));
                    $lastLineBeforeInsert = end($linesBeforeInsert);
                    preg_match('/^(\s*)/', $lastLineBeforeInsert, $matches);
                    $indentationForBrace = $matches[0] ?? '';
                    
                    $existingContentAfterBrace = substr($newContent, $openingBracePosition + 1);
                    $firstCharAfterBrace = trim($existingContentAfterBrace)[0] ?? '';
                    
                    $prefix = "\n" . $indentationForBrace . "    "; // Standard 4-space indent from brace line
                    if ($firstCharAfterBrace === '}') { // If register method is empty
                         $prefix = "\n" . $indentationForBrace . "    ";
                    } else {
                         // Find indentation of the first line of code if any
                         $firstCodeLineMatch = [];
                         if(preg_match('/^\s*([^\s\/])/', $existingContentAfterBrace, $firstCodeLineMatch, PREG_OFFSET_CAPTURE)){
                            $firstCodeLineStart = $firstCodeLineMatch[0][1];
                            $firstCodeLineIndentMatch = [];
                            if(preg_match('/^(\s*)/', substr($existingContentAfterBrace, $firstCodeLineStart), $firstCodeLineIndentMatch)){
                                $prefix = "\n" . $firstCodeLineIndentMatch[0];
                            }
                         }
                    }
                    $newContent = substr_replace($newContent, $prefix . $binding, $openingBracePosition + 1, 0);

                } else {
                    $this->warn("Could not find the opening brace of the register() method in AppServiceProvider.php. Please add binding manually.");
                }
            } else {
                $this->warn("Could not find the register() method in AppServiceProvider.php. Please add binding manually.");
            }
        }


        if ($madeChanges) {
            $this->files->put($appServiceProviderPath, $newContent);
            $this->info("Successfully updated AppServiceProvider.php for service binding.");
        } else if (!Str::contains($this->files->get($appServiceProviderPath), $binding)) { // Final check
            $this->warn("Failed to bind service interface in AppServiceProvider.php automatically. Please add it manually.");
        }
    }

    protected function generateWebRoutes($name)
    {
        $webRoutesPath = base_path('routes/web.php');
        if (!$this->files->exists($webRoutesPath)) {
            $this->warn("routes/web.php not found. Skipping web route addition.");
            return;
        }

        $controllerName = Str::studly($name) . 'Controller';
        $rawNamespace = $this->featureDetails['controller_namespace'] ?? 'App\\Http\\Controllers';
        $namespace = implode('\\', array_map('Str::studly', explode('\\', str_replace('/', '\\', $rawNamespace))));
        $fullControllerClass = $namespace . '\\' . $controllerName;
        
        $routePath = $this->featureDetails['web_route_path'];

        $this->line("Attempting to add web routes to routes/web.php for '{$routePath}'");

        $controllerImport = "use {$fullControllerClass};";
        $routeDefinition = "Route::resource('{$routePath}', {$controllerName}::class);";
        if ($this->featureDetails['controller_type'] !== 'Resource') {
            $routeName = Str::kebab($name);
            $routeDefinition = "Route::get('/{$routePath}', [{$controllerName}::class, 'index'])->name('{$routeName}.index'); // Placeholder";
            if($this->featureDetails['controller_type'] === 'Invokable'){
                $routeDefinition = "Route::get('/{$routePath}', {$controllerName}::class)->name('{$routeName}');";
            }
        }

        $content = $this->files->get($webRoutesPath);
        $newContent = $content;
        $madeChanges = false;

        if (!Str::contains($newContent, $controllerImport) && class_exists($fullControllerClass)) {
            $lastUsePosition = strrpos($newContent, "\nuse ");
            $phpTagPosition = strpos($newContent, "<?php");
            $insertionPointUse = $phpTagPosition !== false ? strpos($newContent, "\n", $phpTagPosition) +1 : 0; // after <?php\n

            if ($lastUsePosition !== false) {
                $endOfLastUseLine = strpos($newContent, ";", $lastUsePosition) + 1;
                $insertionPointUse = $endOfLastUseLine;
            }
            
            $importStatement = "\n" . $controllerImport;
            // Ensure there's a blank line after <?php if we're inserting right after it and no other use statements
            if ($lastUsePosition === false && $phpTagPosition !== false && substr($newContent, $insertionPointUse -1, 1) === "\n" && substr($newContent, $insertionPointUse,1) !== "\n" ){
                 // $importStatement = "\n" . $importStatement; // Add extra newline if needed
            }

            $newContent = substr_replace($newContent, $importStatement, $insertionPointUse, 0);
            $madeChanges = true;
        }

        if (!Str::contains($newContent, $routePath) || !Str::contains($newContent, "{$controllerName}::class")) {
            $newContent = rtrim($newContent) . "\n\n" . $routeDefinition . "\n";
            $madeChanges = true;
        }
        
        if ($madeChanges) {
            $this->files->put($webRoutesPath, $newContent);
            $this->info("Updated routes/web.php for {$name}.");
        } else {
            $this->info("Web routes for {$name} might already exist or controller not imported. Skipping direct addition.");
        }
    }

    protected function generateApiRoutes($name)
    {
        $apiRoutesPath = base_path('routes/api.php');
        if (!$this->files->exists($apiRoutesPath)) {
            $this->warn("routes/api.php not found. Skipping API route addition.");
            return;
        }

        $controllerName = Str::studly($name) . 'Controller';
        $rawNamespace = $this->featureDetails['controller_namespace'] ?? 'App\\Http\\Controllers';
        $namespace = implode('\\', array_map('Str::studly', explode('\\', str_replace('/', '\\', $rawNamespace))));
        $fullControllerClass = $namespace . '\\' . $controllerName;

        $routePath = Str::plural(Str::kebab($name));

        $this->line("Attempting to add API routes to routes/api.php for '{$routePath}'");

        $controllerImport = "use {$fullControllerClass};";
        $routeDefinition = "Route::apiResource('{$routePath}', {$controllerName}::class);";
         if ($this->featureDetails['controller_type'] !== 'Resource') {
            $routeName = Str::kebab($name);
            $routeDefinition = "Route::get('/{$routePath}', [{$controllerName}::class, 'index'])->name('api.{$routeName}.index'); // Placeholder";
            if($this->featureDetails['controller_type'] === 'Invokable'){
                $routeDefinition = "Route::get('/{$routePath}', {$controllerName}::class)->name('api.{$routeName}');";
            }
        }

        $content = $this->files->get($apiRoutesPath);
        $newContent = $content;
        $madeChanges = false;

        if (!Str::contains($newContent, $controllerImport) && class_exists($fullControllerClass)) {
            $lastUsePosition = strrpos($newContent, "\nuse ");
            $phpTagPosition = strpos($newContent, "<?php");
            $insertionPointUse = $phpTagPosition !== false ? strpos($newContent, "\n", $phpTagPosition) +1 : 0;

            if ($lastUsePosition !== false) {
                $endOfLastUseLine = strpos($newContent, ";", $lastUsePosition) + 1;
                $insertionPointUse = $endOfLastUseLine;
            }
            
            $importStatement = "\n" . $controllerImport;
             if ($lastUsePosition === false && $phpTagPosition !== false && substr($newContent, $insertionPointUse -1, 1) === "\n" && substr($newContent, $insertionPointUse,1) !== "\n" ){
                // $importStatement = "\n" . $importStatement;
            }
            $newContent = substr_replace($newContent, $importStatement, $insertionPointUse, 0);
            $madeChanges = true;
        }

        if (!Str::contains($newContent, $routePath) || !Str::contains($newContent, "{$controllerName}::class")) {
            $authMiddlewarePosition = strpos($newContent, "Route::middleware('auth:sanctum')->group(function () {");
            if ($authMiddlewarePosition !== false) {
                // Try to find the closing of the group to insert before it
                $openBraces = 0;
                $currentPos = $authMiddlewarePosition;
                $groupEndPos = -1;
                while($currentPos < strlen($newContent)){
                    if($newContent[$currentPos] === '{'){
                        $openBraces++;
                    } elseif($newContent[$currentPos] === '}'){
                        $openBraces--;
                        if($openBraces === 0 && $currentPos > $authMiddlewarePosition){ // Found the matching brace for the group
                             // Check if this is followed by ');'
                            if(substr($newContent, $currentPos, 3) === '});'){
                                $groupEndPos = $currentPos;
                                break;
                            }
                        }
                    }
                    $currentPos++;
                }

                if($groupEndPos !== -1){
                     // Get indentation of the line where groupEndPos '}' is
                    $prevNewline = strrpos(substr($newContent, 0, $groupEndPos), "\n");
                    $indentLine = substr($newContent, $prevNewline + 1, $groupEndPos - ($prevNewline + 1) );
                    preg_match('/^(\s*)/', $indentLine, $matches);
                    $indentation = ($matches[0] ?? '    ') . '    '; // Indent further for the route

                    $newContent = substr_replace($newContent, "\n" . $indentation . $routeDefinition, $groupEndPos, 0);
                } else {
                     $newContent = rtrim($newContent) . "\n\n" . $routeDefinition . "\n";
                }
            } else {
                $newContent = rtrim($newContent) . "\n\n" . $routeDefinition . "\n";
            }
            $madeChanges = true;
        }
        
        if($madeChanges){
            $this->files->put($apiRoutesPath, $newContent);
            $this->info("Updated routes/api.php for {$name}.");
        } else {
            $this->info("API routes for {$name} might already exist or controller not imported. Skipping direct addition.");
        }
    }

    protected function generateTests($name)
    {
        // Ensure test_types is always an array
        $testTypes = [];
        if (isset($this->featureDetails['test_types'])) {
            if (is_array($this->featureDetails['test_types'])) {
                $testTypes = $this->featureDetails['test_types'];
            } elseif (is_string($this->featureDetails['test_types'])) {
                $testTypes = explode(',', $this->featureDetails['test_types']);
            }
        }

        // Clean up test types (remove whitespace and ensure proper casing)
        $testTypes = array_map(function($type) {
            return trim(ucfirst(strtolower($type)));
        }, $testTypes);

        $studlyName = Str::studly($name);

        if (in_array('Unit', $testTypes)) {
            $this->line("Generating Unit Test: tests/Unit/{$studlyName}Test.php");
            try {
                $this->call('make:test', [
                    'name' => "Unit\\{$studlyName}Test",
                    '--unit' => true,
                ]);
                $this->info("Unit test created successfully.");
            } catch (\Exception $e) {
                $this->error("Failed to create unit test: " . $e->getMessage());
            }
        }

        if (in_array('Feature', $testTypes)) {
            $this->line("Generating Feature Test: tests/Feature/{$studlyName}Test.php");
            try {
                $this->call('make:test', [
                    'name' => "Feature\\{$studlyName}Test",
                    // For feature tests, --unit is not needed. It's the default if not --unit.
                ]);
                $this->info("Feature test created successfully.");
            } catch (\Exception $e) {
                $this->error("Failed to create feature test: " . $e->getMessage());
            }
        }
    }

    protected function generatePolicy($name)
    {
        $studlyName = Str::studly($name);
        $policyName = "{$studlyName}Policy";
        $this->line("Generating Policy: app/Policies/{$policyName}.php");
        $this->call('make:policy', [
            'name' => $policyName,
            '--model' => "App\\Models\\{$studlyName}", // Assumes model is in App\Models
        ]);
    }

    protected function registerPolicy($name)
    {
        $authServiceProviderPath = app_path('Providers/AuthServiceProvider.php');
        if (!$this->files->exists($authServiceProviderPath)) {
            $this->warn("AuthServiceProvider.php not found. Skipping policy registration.");
            return;
        }

        $studlyName = Str::studly($name);
        $modelClass = "App\\Models\\{$studlyName}";
        $policyClass = "App\\Policies\\{$studlyName}Policy";

        $this->line("Attempting to register {$studlyName}Policy in AuthServiceProvider.php");

        $content = $this->files->get($authServiceProviderPath);
        $newContent = $content;
        $madeChanges = false;

        // Prepare import statements
        $modelImport = "use {$modelClass};";
        $policyImport = "use {$policyClass};";

        // Add model import if not present
        if (!Str::contains($newContent, $modelImport) && class_exists($modelClass)) {
            $lastUsePosition = strrpos($newContent, "\nuse ");
            $namespacePosition = strpos($newContent, "\nnamespace ");
            $insertionPoint = ($lastUsePosition !== false)
                ? strpos($newContent, ";", $lastUsePosition) + 1
                : (($namespacePosition !== false) ? strpos($newContent, ";", $namespacePosition) + 1 : strpos($newContent, "<?php") + strlen("<?php"));
            $newContent = substr_replace($newContent, "\n" . $modelImport, $insertionPoint, 0);
            $madeChanges = true;
        }

        // Add policy import if not present
        if (!Str::contains($newContent, $policyImport) && class_exists($policyClass)) {
             // Re-evaluate insertion point after potential first modification
            $lastUsePosition = strrpos($newContent, "\nuse ");
            $namespacePosition = strpos($newContent, "\nnamespace ");
            $insertionPoint = ($lastUsePosition !== false)
                ? strpos($newContent, ";", $lastUsePosition) + 1
                : (($namespacePosition !== false) ? strpos($newContent, ";", $namespacePosition) + 1 : strpos($newContent, "<?php") + strlen("<?php"));
            $newContent = substr_replace($newContent, "\n" . $policyImport, $insertionPoint, 0);
            $madeChanges = true;
        }
        
        // Prepare policy registration string
        $policyMapping = "        {$studlyName}::class => {$studlyName}Policy::class,";

        if (!Str::contains($newContent, "{$studlyName}::class => {$studlyName}Policy::class")) {
            $policiesPropertyPosition = strpos($newContent, 'protected $policies = [');
            if ($policiesPropertyPosition !== false) {
                $openingBracketPosition = strpos($newContent, '[', $policiesPropertyPosition);
                if ($openingBracketPosition !== false) {
                    // Insert after the opening bracket, typically on a new line with indentation
                    $linesBeforeInsert = explode("\n", substr($newContent, 0, $openingBracketPosition + 1));
                    $lastLineBeforeInsert = end($linesBeforeInsert);
                    preg_match('/^(\s*)/', $lastLineBeforeInsert, $matches);
                    $indentationForBracket = $matches[0] ?? '';
                    
                    $prefix = "\n" . $indentationForBracket . "    "; // Standard 4-space indent from bracket line

                    $newContent = substr_replace($newContent, $prefix . $policyMapping, $openingBracketPosition + 1, 0);
                    $madeChanges = true;
                } else {
                    $this->warn("Could not find the opening bracket of the \$policies property. Please register policy manually.");
                }
            } else {
                $this->warn("Could not find the \$policies property in AuthServiceProvider.php. Please register policy manually.");
            }
        }

        if ($madeChanges) {
            $this->files->put($authServiceProviderPath, $newContent);
            $this->info("Updated AuthServiceProvider.php for {$studlyName}Policy registration.");
        } else {
            $this->info("{$studlyName}Policy might already be registered or classes not found. Skipping modification.");
        }
    }

    protected function generateScheduledTask($name)
    {
        $studlyName = Str::studly($name);
        // Example command name, could be made more configurable
        $commandName = "app:{$studlyName}ScheduledTask";
        $className = "{$studlyName}ScheduledCommand"; // Or derive from commandName

        $this->line("Generating Scheduled Task Command: app/Console/Commands/{$className}.php");
        
        // make:command requires a name like 'FooCommand' or 'foo:bar'
        // If we pass just className, it will be FooCommand.php with class FooCommand
        // If we pass commandName, it will be FooScheduledTask.php with class FooScheduledTask
        // Let's use a descriptive class name.
        $this->call('make:command', [
            'name' => $className,
            // '--command' => $commandName // Optionally set the signature
        ]);

        // We might want to update the generated command's signature and description
        $commandPath = app_path("Console/Commands/{$className}.php");
        if ($this->files->exists($commandPath)) {
            $content = $this->files->get($commandPath);
            $newSignature = "protected \$signature = '{$commandName}';";
            $newDescription = "protected \$description = 'Scheduled task related to {$studlyName} feature.';";
            
            $content = preg_replace("/protected \\\$signature = '[^']*';/", $newSignature, $content, 1);
            $content = preg_replace("/protected \\\$description = '[^']*';/", $newDescription, $content, 1);
            
            $this->files->put($commandPath, $content);
            $this->info("Updated signature and description for {$className}.");
        } else {
            $this->warn("Could not find generated command {$commandPath} to update signature/description.");
        }
    }

    protected function generateViews($name)
    {
        $viewDirectory = resource_path("views/" . $this->featureDetails['view_path']);
        $this->makeDirectory($viewDirectory);

        $viewsToGenerate = ['index', 'create', 'edit', 'show']; // Potentially '_form'
        $studlyName = Str::studly($name);
        $lowerName = Str::lower($name);
        $pluralLowerName = Str::plural($lowerName);
        $viewPathPrefix = $this->featureDetails['view_path'];

        foreach ($viewsToGenerate as $view) {
            $viewPath = "{$viewDirectory}/{$view}.blade.php";
            $this->line("Generating View: {$viewPath}");

            // Basic stub content for each view
            $stubContent = "";
            switch ($view) {
                case 'index':
                    $stubContent = "@extends('layouts.app') {{-- Or your main layout --}}\n\n" .
                                   "@section('content')\n" .
                                   "    <div class=\"container\">\n" .
                                   "        <h1>All " . Str::title(str_replace('_', ' ', $pluralLowerName)) . "</h1>\n" .
                                   "        <a href=\"{{ route('{$viewPathPrefix}.create') }}\" class=\"btn btn-primary mb-3\">Create New {$studlyName}</a>\n" .
                                   "        {{-- TODO: Display {$pluralLowerName} in a table or list --}}\n" .
                                   "        {{-- Example for a table: --}}\n" .
                                   "        {{-- @if(\${$pluralLowerName}->count() > 0) --}}\n" .
                                   "        {{-- <table class=\"table\"><thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead><tbody> --}}\n" .
                                   "        {{-- @foreach(\${$pluralLowerName} as \${$lowerName}) --}}\n" .
                                   "        {{-- <tr><td>{{ \${$lowerName}->id }}</td><td>{{ \${$lowerName}->name ?? 'N/A' }}</td> --}}\n" .
                                   "        {{-- <td><a href=\"{{ route('{$viewPathPrefix}.show', \${$lowerName}) }}\">View</a> | <a href=\"{{ route('{$viewPathPrefix}.edit', \${$lowerName}) }}\">Edit</a></td></tr> --}}\n" .
                                   "        {{-- @endforeach --}}\n" .
                                   "        {{-- </tbody></table> --}}\n" .
                                   "        {{-- @else --}}\n" .
                                   "        {{-- <p>No {$pluralLowerName} found.</p> --}}\n" .
                                   "        {{-- @endif --}}\n" .
                                   "    </div>\n" .
                                   "@endsection\n";
                    break;
                case 'create':
                    $stubContent = "@extends('layouts.app')\n\n" .
                                   "@section('content')\n" .
                                   "    <div class=\"container\">\n" .
                                   "        <h1>Create New {$studlyName}</h1>\n" .
                                   "        <form action=\"{{ route('{$viewPathPrefix}.store') }}\" method=\"POST\">\n" .
                                   "            @csrf\n" .
                                   "            {{-- TODO: Add form fields here for creating a {$lowerName} --}}\n" .
                                   "            {{-- Example: <div class=\"form-group\"><label for=\"name\">Name</label><input type=\"text\" name=\"name\" id=\"name\" class=\"form-control\"></div> --}}\n" .
                                   "            <button type=\"submit\" class=\"btn btn-success\">Save {$studlyName}</button>\n" .
                                   "            <a href=\"{{ route('{$viewPathPrefix}.index') }}\" class=\"btn btn-secondary\">Cancel</a>\n" .
                                   "        </form>\n" .
                                   "    </div>\n" .
                                   "@endsection\n";
                    break;
                case 'edit':
                    $stubContent = "@extends('layouts.app')\n\n" .
                                   "@section('content')\n" .
                                   "    <div class=\"container\">\n" .
                                   "        <h1>Edit {$studlyName}</h1>\n" .
                                   "        <form action=\"{{ route('{$viewPathPrefix}.update', \${$lowerName}->id) }}\" method=\"POST\">\n" .
                                   "            @csrf\n" .
                                   "            @method('PUT')\n" .
                                   "            {{-- TODO: Add form fields here, populated with \${$lowerName} data --}}\n" .
                                   "            {{-- Example: <div class=\"form-group\"><label for=\"name\">Name</label><input type=\"text\" name=\"name\" id=\"name\" class=\"form-control\" value=\"{{ \${$lowerName}->name ?? '' }}\"></div> --}}\n" .
                                   "            <button type=\"submit\" class=\"btn btn-primary\">Update {$studlyName}</button>\n" .
                                   "            <a href=\"{{ route('{$viewPathPrefix}.index') }}\" class=\"btn btn-secondary\">Cancel</a>\n" .
                                   "        </form>\n" .
                                   "    </div>\n" .
                                   "@endsection\n";
                    break;
                case 'show':
                    $stubContent = "@extends('layouts.app')\n\n" .
                                   "@section('content')\n" .
                                   "    <div class=\"container\">\n" .
                                   "        <h1>View {$studlyName}</h1>\n" .
                                   "        {{-- TODO: Display \${$lowerName} details --}}\n" .
                                   "        <p><strong>ID:</strong> {{ \${$lowerName}->id }}</p>\n" .
                                   "        {{-- <p><strong>Name:</strong> {{ \${$lowerName}->name ?? 'N/A' }}</p> --}}\n" .
                                   "        <a href=\"{{ route('{$viewPathPrefix}.edit', \${$lowerName}->id) }}\" class=\"btn btn-warning\">Edit</a>\n" .
                                   "        <a href=\"{{ route('{$viewPathPrefix}.index') }}\" class=\"btn btn-secondary\">Back to List</a>\n" .
                                   "        <form action=\"{{ route('{$viewPathPrefix}.destroy', \${$lowerName}->id) }}\" method=\"POST\" style=\"display:inline-block;\" onsubmit=\"return confirm('Are you sure?');\">\n" .
                                   "            @csrf\n" .
                                   "            @method('DELETE')\n" .
                                   "            <button type=\"submit\" class=\"btn btn-danger\">Delete</button>\n" .
                                   "        </form>\n" .
                                   "    </div>\n" .
                                   "@endsection\n";
                    break;
            }

            if (!$this->files->exists($viewPath) || $this->confirm("View {$viewPath} already exists. Overwrite?", false) ) {
                $this->files->put($viewPath, $stubContent);
            } else {
                $this->info("Skipped overwriting view {$viewPath}.");
            }
        }
    }


    /**
     * Create a directory if it doesn't exist.
     *
     * @param string $path
     * @return void
     */
    protected function makeDirectory($path)
    {
        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true, true);
        }
    }
}