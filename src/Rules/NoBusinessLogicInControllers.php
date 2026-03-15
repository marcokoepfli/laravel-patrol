<?php

namespace MarcoKoepfli\LaravelPatrol\Rules;

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use MarcoKoepfli\LaravelPatrol\ProjectContext;

class NoBusinessLogicInControllers extends AbstractRule
{
    private const MAX_STATEMENTS = 10;

    public function id(): string
    {
        return 'no-business-logic-in-controllers';
    }

    public function description(): string
    {
        return 'Controllers should be thin. Move business logic to Action or Service classes.';
    }

    public function docsUrl(LaravelVersion $version): string
    {
        return $version->docsUrl('controllers');
    }

    public function check(ProjectContext $context): array
    {
        $violations = [];
        $analyzer = $context->analyzer();

        foreach ($context->filesIn('app/Http/Controllers') as $file) {
            $relativePath = $context->relativePath($file->getRealPath());
            $code = $file->getContents();
            $ast = $analyzer->parse($code);

            if ($ast === null) {
                continue;
            }

            $methods = $analyzer->findClassMethods($ast, 'public');

            foreach ($methods as $method) {
                // Skip __construct and middleware methods
                if (in_array($method->name->name, ['__construct', 'middleware', 'callAction'])) {
                    continue;
                }

                if ($this->shouldIgnore($method, $analyzer, $code)) {
                    continue;
                }

                $statementCount = $analyzer->countStatements($method);

                if ($statementCount > self::MAX_STATEMENTS) {
                    $violations[] = $this->violation(
                        message: "Method {$method->name->name}() has {$statementCount} statements (max: ".self::MAX_STATEMENTS.')',
                        file: $relativePath,
                        line: $method->getStartLine(),
                        severity: Severity::Warning,
                        suggestion: 'Extract business logic into an Action or Service class to keep controllers thin.',
                    );
                }
            }
        }

        return $violations;
    }
}
