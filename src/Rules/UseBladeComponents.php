<?php

namespace MarcoKoepfli\LaravelPatrol\Rules;

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use MarcoKoepfli\LaravelPatrol\ProjectContext;

class UseBladeComponents extends AbstractRule
{
    public function id(): string
    {
        return 'use-blade-components';
    }

    public function description(): string
    {
        return 'Prefer Blade components over @include directives.';
    }

    public function docsUrl(LaravelVersion $version): string
    {
        return $version->docsUrl('blade#components');
    }

    public function check(ProjectContext $context): array
    {
        $violations = [];

        foreach ($context->filesIn('resources/views', '*.blade.php') as $file) {
            $relativePath = $context->relativePath($file->getRealPath());
            $contents = $file->getContents();
            $lines = explode("\n", $contents);

            foreach ($lines as $index => $line) {
                $lineNumber = $index + 1;

                // Check for @patrol-ignore on the line above or same line
                if (str_contains($line, '@patrol-ignore')) {
                    continue;
                }
                if ($index > 0 && str_contains($lines[$index - 1], '@patrol-ignore')) {
                    continue;
                }

                // Match @include but not @includeIf, @includeWhen, @includeFirst
                // which have different semantics and may be intentional
                if (preg_match('/@include\s*\(/', $line) && ! preg_match('/@include(If|When|First|Unless)\s*\(/', $line)) {

                    // Extract the view name for a better message
                    $viewName = null;
                    if (preg_match("/@include\s*\(\s*['\"]([^'\"]+)['\"]/", $line, $matches)) {
                        $viewName = $matches[1];
                    }

                    $message = $viewName
                        ? "@include('{$viewName}') found"
                        : '@include directive found';

                    $violations[] = $this->violation(
                        message: $message,
                        file: $relativePath,
                        line: $lineNumber,
                        severity: Severity::Info,
                        suggestion: 'Consider using a Blade component (<x-component />) instead of @include for better encapsulation and type safety.',
                    );
                }
            }
        }

        return $violations;
    }
}
