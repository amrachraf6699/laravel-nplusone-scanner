<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

final readonly class RelationshipMetadata
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $relatedModel = null,
    ) {
    }
}
