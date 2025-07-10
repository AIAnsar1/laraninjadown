<?php

namespace App\Console\Commands\Generator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CrudGenerator extends Command
{
     /**
     * The CRUD Files of the console command.
     *
     * @var array
     */
    protected array $fileName = [
        'app/Events/CrudGeneratorEvent',
        'app/Http/Controllers/CrudGeneratorController',
        'app/Http/Requests/StoreRequest/StoreCrudGeneratorRequest',
        'app/Http/Requests/UpdateRequest/UpdateCrudGeneratorRequest',
        'app/Http/Resources/CrudGeneratorResource',
        'app/Jobs/CrudGeneratorJob',
        'app/Listeners/CrudGeneratorListener',
        'app/Models/CrudGenerator',
        'app/Observers/CrudGeneratorObserver',
        'app/Repositories/CrudGeneratorRepository',
        'app/Services/CrudGeneratorService',
        'database/factories/CrudGeneratorFactory',
        'database/seeders/CrudGeneratorSeeder',
    ];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generator:crud  {name} {--path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creating CRUD App With Rest API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->option('path') ?? ucfirst($this->argument('name'));
        $pathX = $this->argument('name');
        $testMethods = ['Index', 'Store', 'Show', 'Update', 'Delete'];
        $httpMethods = ['Get', 'Post', 'Get', 'Put', 'Delete'];

        foreach ($this->fileName as $fileName)
        {
            echo "[ ETA ]: " .str_replace('CrudGenerator', $this->argument('name'), $fileName) . '.php' . PHP_EOL;
            $content = file_get_contents($fileName . '.php');
            $content = str_replace(['CrudGenerator', 'crudgenerators', 'CrudgeneratorId'], [$this->argument('name'), $path, strtolower($this->argument('name')) . '_id'], $content);
            file_put_contents(str_replace('CrudGenerator', $this->argument('name'), $fileName) . '.php', $content);

            foreach ($testMethods as $index => $methods)
            {
                $testName = "{$httpMethods[$index]}{$pathX}{$methods}RequestTest";
                Artisan::call("make:test {$pathX}/{$testName}");

                $testFileName = "tests/Feature/{$pathX}/{$httpMethods[$index]}{$path}{$methods}RequestTest.php";
                $fileContent = file_get_contents($testFileName);
                $fileContent = str_replace(["test_example", "get"], ["test_{$pathX}_{$httpMethods[$index]}_request", $httpMethods[$index]], $fileContent);
                file_put_contents($testFileName, $fileContent);
            }
        }
        Artisan::call('make:migration create_' . strtolower($this->argument('name')) . 's_table');
        $this->info("[ ETA ]: Service Created Success Fully! ");
    }
}
