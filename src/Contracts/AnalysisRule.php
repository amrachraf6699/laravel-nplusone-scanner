<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Contracts;

use Amrachraf6699\LaravelNPlusOneScanner\Analysis\AnalysisContext;
use Amrachraf6699\LaravelNPlusOneScanner\Findings\Finding;
use PhpParser\Node;

interface AnalysisRule
{
    public function id(): string;

    /** @return iterable<Finding> */
    public function analyze(Node $node, AnalysisContext $context): iterable;
}
