<?php

namespace MarcoKoepfli\LaravelPatrol\Rules;

use MarcoKoepfli\LaravelPatrol\Analyzers\FileAnalyzer;
use MarcoKoepfli\LaravelPatrol\Contracts\Rule;
use MarcoKoepfli\LaravelPatrol\Enums\LaravelVersion;
use MarcoKoepfli\LaravelPatrol\Enums\Severity;
use MarcoKoepfli\LaravelPatrol\Result;
use PhpParser\Node;

abstract class AbstractRule implements Rule
{
    protected ?LaravelVersion $currentVersion = null;

    public function setVersion(LaravelVersion $version): void
    {
        $this->currentVersion = $version;
    }

    public function supportedVersions(): array
    {
        return [];
    }

    protected function violation(
        string $message,
        ?string $file = null,
        ?int $line = null,
        Severity $severity = Severity::Warning,
        ?string $suggestion = null,
    ): Result {
        $version = $this->currentVersion ?? LaravelVersion::V12;

        return new Result(
            ruleId: $this->id(),
            message: $message,
            file: $file,
            line: $line,
            severity: $severity,
            docsUrl: $this->docsUrl($version),
            suggestion: $suggestion,
        );
    }

    /**
     * Check if a node should be ignored via @patrol-ignore comment.
     */
    protected function shouldIgnore(Node $node, FileAnalyzer $analyzer, string $sourceCode = ''): bool
    {
        return $analyzer->hasIgnoreComment($node, $sourceCode);
    }
}
