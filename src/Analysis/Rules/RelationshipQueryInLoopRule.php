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

final class RelationshipQueryInLoopRule implements AnalysisRule
{
    public function id(): string
    {
        return 'N2';
    }
    public function analyze(Node $node, AnalysisContext $context): iterable
    {
        $event = $context->event;
        if (!$event || $event->type !== AnalysisEvent::QUERY || $event->context !== 'loop') {
            return;
        }
        yield new Finding(
            'N2',
            Severity::HIGH,
            Confidence::from($event->confidence),
            $event->file,
            $node->getStartLine(),
            1,
            'Potential repeated relationship query inside an iteration.',
            $event->model,
            $event->relationship,
            $event->suggestion,
            null,
            (int) ($node->getAttribute('startFilePos', 0)),
        );
    }
}
