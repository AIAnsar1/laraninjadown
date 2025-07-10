<?php

namespace App\Console\Commands\Generator;

use Illuminate\Console\Command;

class CrudRemove extends Command
{
    /**
     * The CRUD Files of the console command.
     *
     * @var array
     */
    protected array $fileNames = [
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
    protected $signature = 'generator:remove {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removing CRUD App With REST API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testMethods = ['Index', 'Store', 'Show', 'Update', 'Delete'];
        $httpMethods = ['get', 'post', 'get', 'put', 'delete'];
        $pathX = $this->argument('name');

        foreach ($this->fileNames as $fileName)
        {
            echo "[ ETA ]: " . str_replace('CrudGeneratpr', $this->argument('name'), $fileName). ".php" . PHP_EOL;
            @unlink(str_replace('CrudGenerator', $this->argument('name'), $fileName) . ".php");

            foreach ($testMethods as $index => $methods)
            {
                $testFileNames = "tests/Features/{$pathX}/{$httpMethods[$index]}{$pathX}{$testMethods[$index]}RequestTest.php";

                if (file_exists($testFileNames))
                {
                    @unlink($testFileNames);
                }
            }
        }
        $this->deleteDirectory("tests/Feature/{$pathX}");
        die();
    }


    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = "$dir/$file";
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        rmdir($dir);
    }
}
