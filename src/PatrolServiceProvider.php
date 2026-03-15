<?php

namespace MarcoKoepfli\LaravelPatrol;

use MarcoKoepfli\LaravelPatrol\Commands\PatrolCommand;
use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PatrolServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('patrol')
            ->hasConfigFile()
            ->hasCommand(PatrolCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(Patrol::class, function ($app) {
            $config = config('patrol', []);
            $versionInt = $config['version'] ?? 12;
            $version = LaravelVersion::tryFrom((int) $versionInt) ?? LaravelVersion::V12;

            return new Patrol(
                version: $version,
                basePath: base_path(),
                config: $config,
            );
        });
    }
}
