<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

final class ModelMetadata
{
    /** @param array<string, RelationshipMetadata> $relationships @param list<string> $with */
    public function __construct(
        public string $class,
        public ?string $parent,
        public array $relationships = [],
        public array $with = [],
    ) {
    }
}
