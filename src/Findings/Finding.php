<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Findings;

final readonly class Finding
{
    public function __construct(
        public string $rule,
        public Severity $severity,
        public Confidence $confidence,
        public string $file,
        public int $line,
        public int $column,
        public string $message,
        public ?string $model = null,
        public ?string $relationship = null,
        public ?string $suggestion = null,
        public ?string $documentationUrl = null,
        public ?int $offset = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'severity' => $this->severity->value,
            'confidence' => $this->confidence->value,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'message' => $this->message,
            'model' => $this->model,
            'relationship' => $this->relationship,
            'suggestion' => $this->suggestion,
            'documentation_url' => $this->documentationUrl,
        ];
    }

    public function key(): string
    {
        return implode('|', [$this->rule, $this->file, $this->offset ?? $this->line, $this->relationship]);
    }
}
