<?php

namespace MarcoKoepfli\LaravelPatrol\Rules;

use MarcoKoepfli\LaravelPatrol\Analyzers\FileAnalyzer;
use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use PhpParser\Node;

class UseFormRequests extends AbstractRule
{
    public function id(): string
    {
        return 'use-form-requests';
    }

    public function description(): string
    {
        return 'Controllers should use Form Request classes for validation instead of inline validation.';
    }

    public function docsUrl(LaravelVersion $version): string
    {
        return $version->docsUrl('validation#form-request-validation');
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

            // Find $request->validate() calls
            $requestValidateCalls = $analyzer->findMethodCalls($ast, 'request', 'validate');
            foreach ($requestValidateCalls as $call) {
                if ($this->shouldIgnore($call, $analyzer, $code)) {
                    continue;
                }

                $violations[] = $this->violation(
                    message: 'Inline validation: $request->validate()',
                    file: $relativePath,
                    line: $call->getStartLine(),
                    suggestion: 'Extract validation to a Form Request class using: php artisan make:request',
                );
            }

            // Find $this->validate() calls (only in controllers, not FormRequests)
            if ($this->isControllerClass($ast, $analyzer)) {
                $thisValidateCalls = $analyzer->findMethodCalls($ast, 'this', 'validate');
                foreach ($thisValidateCalls as $call) {
                    if ($this->shouldIgnore($call, $analyzer, $code)) {
                        continue;
                    }

                    $violations[] = $this->violation(
                        message: 'Inline validation: $this->validate()',
                        file: $relativePath,
                        line: $call->getStartLine(),
                        suggestion: 'Extract validation to a Form Request class using: php artisan make:request',
                    );
                }
            }

            // Find Validator::make() static calls
            $validatorMakeCalls = $analyzer->findStaticCalls($ast, 'Validator', 'make');
            foreach ($validatorMakeCalls as $call) {
                if ($this->shouldIgnore($call, $analyzer, $code)) {
                    continue;
                }

                $violations[] = $this->violation(
                    message: 'Inline validation: Validator::make()',
                    file: $relativePath,
                    line: $call->getStartLine(),
                    suggestion: 'Extract validation to a Form Request class using: php artisan make:request',
                );
            }
        }

        return $violations;
    }

    /**
     * Check if the file contains a controller class (not a FormRequest).
     *
     * @param  Node\Stmt[]  $ast
     */
    private function isControllerClass(array $ast, FileAnalyzer $analyzer): bool
    {
        $classes = $analyzer->findNodes($ast, fn (Node $node) => $node instanceof Node\Stmt\Class_);

        foreach ($classes as $class) {
            if (! $class instanceof Node\Stmt\Class_) {
                continue;
            }

            // If extends FormRequest, this is NOT a controller
            if ($class->extends !== null && $class->extends->getLast() === 'FormRequest') {
                return false;
            }
        }

        return true;
    }
}
