<?php

namespace MarcoKoepfli\LaravelPatrol\Contracts;

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use MarcoKoepfli\LaravelPatrol\Result;

interface Rule
{
    /**
     * Unique machine-readable identifier, e.g. 'no-env-outside-config'.
     */
    public function id(): string;

    /**
     * Human-friendly description shown in output.
     */
    public function description(): string;

    /**
     * URL to the relevant Laravel docs section.
     */
    public function docsUrl(LaravelVersion $version): string;

    /**
     * Which Laravel versions this rule applies to. Empty array = all versions.
     *
     * @return LaravelVersion[]
     */
    public function supportedVersions(): array;

    /**
     * Run the rule against the project and return violations.
     *
     * @return Result[]
     */
    public function check(ProjectContext $context): array;
}
