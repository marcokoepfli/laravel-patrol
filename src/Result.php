<?php

namespace MarcoKoepfli\LaravelPatrol;

use MarcoKoepfli\LaravelPatrol\Enums\Severity;

class Result
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $message,
        public readonly ?string $file = null,
        public readonly ?int $line = null,
        public readonly Severity $severity = Severity::Warning,
        public readonly ?string $docsUrl = null,
        public readonly ?string $suggestion = null,
    ) {}

    public function toArray(): array
    {
        return [
            'rule' => $this->ruleId,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'severity' => $this->severity->value,
            'docs_url' => $this->docsUrl,
            'suggestion' => $this->suggestion,
        ];
    }
}
