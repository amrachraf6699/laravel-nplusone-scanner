<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

final class AnalysisContext
{
    public function __construct(
        public SourceIndex $index,
        public string $file,
        public ?AnalysisEvent $event = null,
    ) {
    }
}
