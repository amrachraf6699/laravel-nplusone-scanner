<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis\Rules;

use Amrachraf6699\LaravelNPlusOneScanner\Analysis\AnalysisContext;
use Amrachraf6699\LaravelNPlusOneScanner\Contracts\AnalysisRule;
use PhpParser\Node;

final class CollectionCallbackRule implements AnalysisRule
{
    public function id(): string
    {
        return 'N3';
    }
    public function analyze(Node $node, AnalysisContext $context): iterable
    {
        yield from [];
    }
}
