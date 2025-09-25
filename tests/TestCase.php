<?php

namespace Everware\LaravelFortifySanctum\Tests;

use Orchestra\Testbench\Concerns\WithWorkbench;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use WithWorkbench, Concerns\SetUpFortifySanctumTests;

    /** {@see PackageManifest::getManifest()}. */
    protected $enablesPackageDiscoveries = true;
}
