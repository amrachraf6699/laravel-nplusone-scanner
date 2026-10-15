<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Scanner;

final readonly class ScanDiagnostic
{
    public function __construct(
        public string $code,
        public string $level,
        public string $message,
        public ?string $file = null,
        public ?int $line = null,
    ) {
    }

    public function toArray(): array
    {
        return ['code' => $this->code, 'level' => $this->level, 'message' => $this->message, 'file' => $this->file, 'line' => $this->line];
    }
}
