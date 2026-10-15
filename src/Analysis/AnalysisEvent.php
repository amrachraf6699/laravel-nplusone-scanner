<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

use PhpParser\Node;

final readonly class AnalysisEvent
{
    public const PROPERTY = 'relationship_property';
    public const QUERY = 'relationship_query';
    public const RESOURCE = 'resource_relationship';

    public function __construct(
        public string $type,
        public Node $node,
        public string $file,
        public ?string $model,
        public ?string $relationship,
        public ?string $operation,
        public string $context,
        public string $confidence,
        public string $suggestion,
    ) {
    }
}
