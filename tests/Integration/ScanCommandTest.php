<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Tests\Integration;

use Amrachraf6699\LaravelNPlusOneScanner\NPlusOneServiceProvider;
use Orchestra\Testbench\TestCase;

final class ScanCommandTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [NPlusOneServiceProvider::class];
    }

    public function testCommandIsRegistered(): void
    {
        $this->artisan('nplusone:scan', ['--path' => [__DIR__ . '/../Fixtures/Project/app/Models']])
            ->assertExitCode(0);
    }
}
