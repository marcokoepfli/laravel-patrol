<?php

namespace MarcoKoepfli\LaravelPatrol\Rules;

use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\ProjectContext;
use PhpParser\Node;

class UseResourceControllers extends AbstractRule
{
    private const ROUTE_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    public function id(): string
    {
        return 'use-resource-controllers';
    }

    public function description(): string
    {
        return 'Use Route::resource() instead of defining individual CRUD routes.';
    }

    public function docsUrl(LaravelVersion $version): string
    {
        return $version->docsUrl('controllers#resource-controllers');
    }

    public function check(ProjectContext $context): array
    {
        $violations = [];
        $analyzer = $context->analyzer();

        foreach ($context->filesIn('routes') as $file) {
            $relativePath = $context->relativePath($file->getRealPath());
            $code = $file->getContents();
            $ast = $analyzer->parse($code);

            if ($ast === null) {
                continue;
            }

            // Collect controller references from individual route definitions
            $controllerRoutes = [];

            foreach (self::ROUTE_METHODS as $method) {
                $calls = $analyzer->findStaticCalls($ast, 'Route', $method);

                foreach ($calls as $call) {
                    if ($this->shouldIgnore($call, $analyzer, $code)) {
                        continue;
                    }

                    $controllerName = $this->extractControllerName($call);

                    if ($controllerName !== null) {
                        $controllerRoutes[$controllerName][] = [
                            'method' => $method,
                            'line' => $call->getStartLine(),
                        ];
                    }
                }
            }

            // Flag controllers with 3+ individual route definitions
            foreach ($controllerRoutes as $controller => $routes) {
                if (count($routes) >= 3) {
                    $methods = array_unique(array_column($routes, 'method'));
                    $methodList = implode(', ', $methods);
                    $firstLine = $routes[0]['line'];

                    $violations[] = $this->violation(
                        message: "{$controller} has ".count($routes)." individual route definitions ({$methodList})",
                        file: $relativePath,
                        line: $firstLine,
                        suggestion: "Consider using Route::resource() or Route::apiResource() for {$controller}.",
                    );
                }
            }
        }

        return $violations;
    }

    /**
     * Extract the controller class name from a Route::method() call.
     */
    private function extractControllerName(Node\Expr\StaticCall $call): ?string
    {
        // Pattern: Route::get('/path', [Controller::class, 'method'])
        if (count($call->args) >= 2) {
            $action = $call->args[1]->value ?? null;

            if ($action instanceof Node\Expr\Array_ && count($action->items) >= 1) {
                $arrayItem = $action->items[0];
                $firstItem = $arrayItem->value;

                if ($firstItem instanceof Node\Expr\ClassConstFetch
                    && $firstItem->class instanceof Node\Name
                    && $firstItem->name instanceof Node\Identifier
                    && $firstItem->name->name === 'class') {
                    return $firstItem->class->getLast();
                }
            }
        }

        return null;
    }
}
