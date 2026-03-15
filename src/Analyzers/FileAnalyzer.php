<?php

namespace MarcoKoepfli\LaravelPatrol\Analyzers;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class FileAnalyzer
{
    private Parser $parser;

    /** @var array<string, list<Node\Stmt>|null> */
    private array $astCache = [];

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * Parse a PHP file and return the AST with resolved names.
     * Returns null if the code has syntax errors.
     *
     * @return list<Node\Stmt>|null
     */
    public function parse(string $code): ?array
    {
        $hash = md5($code);

        if (! isset($this->astCache[$hash])) {
            try {
                $ast = $this->parser->parse($code);
            } catch (Error) {
                $this->astCache[$hash] = null;

                return null;
            }

            if ($ast !== null) {
                $traverser = new NodeTraverser;
                $traverser->addVisitor(new NameResolver);

                /** @var list<Node\Stmt> $ast */
                $ast = $traverser->traverse($ast);
            }

            $this->astCache[$hash] = $ast;
        }

        return $this->astCache[$hash];
    }

    /**
     * Find all nodes matching a callback in an AST.
     *
     * @param  Node\Stmt[]  $ast
     * @return list<Node>
     */
    public function findNodes(array $ast, callable $filter): array
    {
        $found = [];
        $collector = new class($filter, $found) extends NodeVisitorAbstract
        {
            /** @var list<Node> */
            public array $results = [];

            public function __construct(
                private readonly \Closure $filter,
                array &$found,
            ) {
                $this->results = &$found;
            }

            public function enterNode(Node $node): ?int
            {
                if (($this->filter)($node)) {
                    $this->results[] = $node;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser;
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        return $found;
    }

    /**
     * Find all function calls by name (e.g. 'env', 'config').
     *
     * @param  Node\Stmt[]  $ast
     * @return list<Node\Expr\FuncCall>
     */
    public function findFunctionCalls(array $ast, string $functionName): array
    {
        /** @var list<Node\Expr\FuncCall> */
        return $this->findNodes($ast, function (Node $node) use ($functionName) {
            if (! $node instanceof Node\Expr\FuncCall) {
                return false;
            }

            if ($node->name instanceof Node\Name) {
                return $node->name->toLowerString() === strtolower($functionName);
            }

            return false;
        });
    }

    /**
     * Find all static method calls (e.g. DB::raw, Route::get).
     *
     * @param  Node\Stmt[]  $ast
     * @return list<Node\Expr\StaticCall>
     */
    public function findStaticCalls(array $ast, string $className, ?string $methodName = null): array
    {
        /** @var list<Node\Expr\StaticCall> */
        return $this->findNodes($ast, function (Node $node) use ($className, $methodName) {
            if (! $node instanceof Node\Expr\StaticCall) {
                return false;
            }

            if (! $node->class instanceof Node\Name) {
                return false;
            }

            $classMatch = $node->class->getLast() === $className
                || $node->class->toLowerString() === strtolower($className);

            if (! $classMatch) {
                return false;
            }

            if ($methodName === null) {
                return true;
            }

            if ($node->name instanceof Node\Identifier) {
                return $node->name->name === $methodName;
            }

            return false;
        });
    }

    /**
     * Find all method calls on a variable (e.g. $request->validate).
     *
     * @param  Node\Stmt[]  $ast
     * @return list<Node\Expr\MethodCall>
     */
    public function findMethodCalls(array $ast, string $variableName, string $methodName): array
    {
        /** @var list<Node\Expr\MethodCall> */
        return $this->findNodes($ast, function (Node $node) use ($variableName, $methodName) {
            if (! $node instanceof Node\Expr\MethodCall) {
                return false;
            }

            if (! $node->var instanceof Node\Expr\Variable) {
                return false;
            }

            if ($node->var->name !== $variableName) {
                return false;
            }

            if ($node->name instanceof Node\Identifier) {
                return $node->name->name === $methodName;
            }

            return false;
        });
    }

    /**
     * Find all class method definitions in a file.
     *
     * @param  Node\Stmt[]  $ast
     * @return list<Node\Stmt\ClassMethod>
     */
    public function findClassMethods(array $ast, ?string $visibility = 'public'): array
    {
        /** @var list<Node\Stmt\ClassMethod> */
        return $this->findNodes($ast, function (Node $node) use ($visibility) {
            if (! $node instanceof Node\Stmt\ClassMethod) {
                return false;
            }

            if ($visibility === null) {
                return true;
            }

            return match ($visibility) {
                'public' => $node->isPublic(),
                'protected' => $node->isProtected(),
                'private' => $node->isPrivate(),
                default => true,
            };
        });
    }

    /**
     * Check if a node has a @patrol-ignore comment by looking at the source code.
     * Checks the same line and the line above for @patrol-ignore.
     */
    public function hasIgnoreComment(Node $node, string $sourceCode = ''): bool
    {
        // Strategy 1: Check AST comments attached to the node
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), '@patrol-ignore')) {
                return true;
            }
        }

        $docComment = $node->getDocComment();
        if ($docComment && str_contains($docComment->getText(), '@patrol-ignore')) {
            return true;
        }

        // Strategy 2: Check source code lines (handles cases where comments attach to parent)
        if ($sourceCode !== '') {
            $lines = explode("\n", $sourceCode);
            $nodeLine = $node->getStartLine();

            // Check same line
            if (isset($lines[$nodeLine - 1]) && str_contains($lines[$nodeLine - 1], '@patrol-ignore')) {
                return true;
            }

            // Check line above
            if ($nodeLine > 1 && isset($lines[$nodeLine - 2]) && str_contains($lines[$nodeLine - 2], '@patrol-ignore')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count the actual statements in a method body (recursive).
     */
    public function countStatements(Node\Stmt\ClassMethod $method): int
    {
        if ($method->stmts === null) {
            return 0;
        }

        return $this->countStatementsRecursive($method->stmts);
    }

    /**
     * @param  Node\Stmt[]  $stmts
     */
    private function countStatementsRecursive(array $stmts): int
    {
        $count = 0;

        foreach ($stmts as $stmt) {
            $count++;

            if ($stmt instanceof Node\Stmt\If_) {
                $count += $this->countStatementsRecursive($stmt->stmts);
                foreach ($stmt->elseifs as $elseif) {
                    $count += $this->countStatementsRecursive($elseif->stmts);
                }
                if ($stmt->else) {
                    $count += $this->countStatementsRecursive($stmt->else->stmts);
                }
            } elseif ($stmt instanceof Node\Stmt\Foreach_ || $stmt instanceof Node\Stmt\For_ || $stmt instanceof Node\Stmt\While_) {
                $count += $this->countStatementsRecursive($stmt->stmts);
            } elseif ($stmt instanceof Node\Stmt\TryCatch) {
                $count += $this->countStatementsRecursive($stmt->stmts);
                foreach ($stmt->catches as $catch) {
                    $count += $this->countStatementsRecursive($catch->stmts);
                }
                if ($stmt->finally) {
                    $count += $this->countStatementsRecursive($stmt->finally->stmts);
                }
            }
        }

        return $count;
    }
}
