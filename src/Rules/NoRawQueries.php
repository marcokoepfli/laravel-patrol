<?php

namespace MarcoKoepfli\LaravelPatrol\Rules;

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use MarcoKoepfli\LaravelPatrol\ProjectContext;

class NoRawQueries extends AbstractRule
{
    private const RAW_METHODS = ['select', 'insert', 'update', 'delete', 'statement', 'raw', 'unprepared'];

    public function id(): string
    {
        return 'no-raw-queries';
    }

    public function description(): string
    {
        return 'Prefer Eloquent ORM over raw database queries.';
    }

    public function docsUrl(LaravelVersion $version): string
    {
        return $version->docsUrl('eloquent');
    }

    public function check(ProjectContext $context): array
    {
        $violations = [];
        $analyzer = $context->analyzer();

        foreach ($context->filesIn('app') as $file) {
            $relativePath = $context->relativePath($file->getRealPath());

            // Migrations, seeders, and database-related files are expected to use DB facade
            if ($this->isExcludedPath($relativePath)) {
                continue;
            }

            $code = $file->getContents();
            $ast = $analyzer->parse($code);

            if ($ast === null) {
                continue;
            }

            foreach (self::RAW_METHODS as $method) {
                $calls = $analyzer->findStaticCalls($ast, 'DB', $method);

                foreach ($calls as $call) {
                    if ($this->shouldIgnore($call, $analyzer, $code)) {
                        continue;
                    }

                    $violations[] = $this->violation(
                        message: "Raw database query: DB::{$method}()",
                        file: $relativePath,
                        line: $call->getStartLine(),
                        severity: Severity::Info,
                        suggestion: 'Consider using Eloquent models and relationships instead of raw queries.',
                    );
                }
            }
        }

        return $violations;
    }

    private function isExcludedPath(string $path): bool
    {
        $excludedPrefixes = [
            'app/Console/',
        ];

        $excludedPatterns = [
            '/Migration/',
            '/Seeder/',
            '/database/',
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        foreach ($excludedPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
