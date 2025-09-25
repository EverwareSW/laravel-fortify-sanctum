<?php

namespace Everware\LaravelFortifySanctum\Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use WithWorkbench, Concerns\SetUpStatelessAuth;

    /** {@see PackageManifest::getManifest()}. */
    protected $enablesPackageDiscoveries = true;
}
