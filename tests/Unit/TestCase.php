<?php

namespace Tests\Unit;

use App\Providers\ApiResponseServiceProvider;
use PHPUnit\Framework\TestCase as Orchestra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();
    }

    protected function GetPackageProviders($app)
    {
        return [ ApiResponseServiceProvider::class ];
    }


    public function GetEbvironmentSetUP($app)
    {
        $app->config->set('database.default', 'testing');
        $app->config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => 'memory',
            'prefix' => '',
        ]);
    }
}
