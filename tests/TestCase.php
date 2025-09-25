<?php

namespace Everware\LaravelFortifySanctum\Tests;

// use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

use Everware\LaravelFortifySanctum\Providers\FortifySanctumServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use WithWorkbench, Concerns\SetUpStatelessAuth;

    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app)
    {
        return [
            FortifySanctumServiceProvider::class,
        ];
    }

    public function ignorePackageDiscoveriesFrom()
    {
        return [];
    }
}
