<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis\Rules;

use Amrachraf6699\LaravelNPlusOneScanner\Analysis\AnalysisContext;
use Amrachraf6699\LaravelNPlusOneScanner\Analysis\AnalysisEvent;
use Amrachraf6699\LaravelNPlusOneScanner\Contracts\AnalysisRule;
use Amrachraf6699\LaravelNPlusOneScanner\Findings\Confidence;
use Amrachraf6699\LaravelNPlusOneScanner\Findings\Finding;
use Amrachraf6699\LaravelNPlusOneScanner\Findings\Severity;
use PhpParser\Node;

final class ResourceRelationshipRule implements AnalysisRule
{
    public function id(): string
    {
        return 'N4';
    }
    public function analyze(Node $node, AnalysisContext $context): iterable
    {
        $event = $context->event;
        if (!$event || $event->type !== AnalysisEvent::RESOURCE) {
            return;
        }
        yield new Finding(
            'N4',
            Severity::MEDIUM,
            Confidence::LOW,
            $event->file,
            $node->getStartLine(),
            1,
            'Potential resource relationship loading risk.',
            $event->model,
            $event->relationship,
            $event->suggestion,
            null,
            (int) ($node->getAttribute('startFilePos', 0)),
        );
    }
}
