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

final class CollectionCallbackRule implements AnalysisRule
{
    public function id(): string
    {
        return 'N3';
    }
    public function analyze(Node $node, AnalysisContext $context): iterable
    {
        $event = $context->event;
        if (!$event || $event->context !== 'callback' || !in_array($event->type, [AnalysisEvent::PROPERTY, AnalysisEvent::QUERY], true)) {
            return;
        }
        $type = $event->type === AnalysisEvent::QUERY ? 'N3' : 'N3';
        yield new Finding(
            $type,
            Severity::HIGH,
            Confidence::from($event->confidence),
            $event->file,
            $node->getStartLine(),
            1,
            'Potential N+1 relationship access inside a collection callback.',
            $event->model,
            $event->relationship,
            $event->suggestion,
            null,
            (int) ($node->getAttribute('startFilePos', 0)),
        );
    }
}
