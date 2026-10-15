<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

use Amrachraf6699\LaravelNPlusOneScanner\Findings\FindingCollector;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class FlowAnalyzer
{
    private const QUERY_TERMINALS = ['count', 'exists', 'first', 'get', 'sum', 'avg', 'max', 'min', 'pluck'];

    private RuleEngine $ruleEngine;

    public function __construct(?RuleEngine $ruleEngine = null)
    {
        $this->ruleEngine = $ruleEngine ?? RuleEngine::default();
    }

    /** @param list<Node> $ast */
    public function analyze(array $ast, string $file, SourceIndex $index, FindingCollector $collector, array $enabledRules): void
    {
        $state = new FlowState();
        $this->statements($ast, $file, $index, $collector, $enabledRules, $state, false, null, null);
    }

    /** @param list<Node> $nodes */
    private function statements(array $nodes, string $file, SourceIndex $index, FindingCollector $collector, array $rules, FlowState $state, bool $inIteration, ?string $iterationModel, ?string $resourceModel): void
    {
        foreach ($nodes as $node) {
            if (!$node instanceof Node) {
                continue;
            }
            if ($node instanceof Stmt\Namespace_) {
                $this->statements($node->stmts, $file, $index, $collector, $rules, $state, $inIteration, $iterationModel, $resourceModel);
                continue;
            }
            if ($node instanceof Stmt\Class_) {
                $model = $index->resourceModel($this->className($node));
                foreach ($node->stmts as $member) {
                    if ($member instanceof Stmt\ClassMethod) {
                        $methodName = $member->name->toString();
                        $isResource = $model !== null && $methodName === 'toArray';
                        $this->statements($member->stmts ?? [], $file, $index, $collector, $rules, new FlowState(), false, null, $isResource ? $model : $resourceModel);
                    }
                }
                continue;
            }
            if ($node instanceof Stmt\Foreach_) {
                $collection = $this->expression($node->expr, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
                $bodyState = $state->copy();
                $model = $collection?->model;
                if ($node->valueVar instanceof Expr\Variable && is_string($node->valueVar->name)) {
                    $bodyState->vars[$node->valueVar->name] = ValueFact::model($model, true, $collection?->loaded ?? [], $collection?->aggregates ?? []);
                }
                $bodyState->iterationContext = $model !== null ? 'loop' : 'none';
                $bodyState->iterationConfidence = 'high';
                $this->statements($node->stmts, $file, $index, $collector, $rules, $bodyState, $model !== null, $model, $resourceModel);
                continue;
            }
            if ($node instanceof Stmt\If_) {
                $this->expression($node->cond, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
                $then = $state->copy();
                $this->statements($node->stmts, $file, $index, $collector, $rules, $then, $inIteration, $iterationModel, $resourceModel);
                $branches = [$then];
                foreach ($node->elseifs as $elseif) {
                    $branch = $state->copy();
                    $this->statements($elseif->stmts, $file, $index, $collector, $rules, $branch, $inIteration, $iterationModel, $resourceModel);
                    $branches[] = $branch;
                }
                if ($node->else) {
                    $branch = $state->copy();
                    $this->statements($node->else->stmts, $file, $index, $collector, $rules, $branch, $inIteration, $iterationModel, $resourceModel);
                    $branches[] = $branch;
                } else {
                    $branches[] = $state->copy();
                }
                $joined = FlowState::join($branches);
                $state->vars = $joined->vars;
                continue;
            }
            if ($node instanceof Stmt\For_ || $node instanceof Stmt\While_ || $node instanceof Stmt\Do_) {
                $conditions = $node instanceof Stmt\For_ ? $node->cond : [$node->cond];
                foreach ($conditions as $expr) {
                    if ($expr instanceof Node) {
                        $this->expression($expr, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
                    }
                }
                if ($node instanceof Stmt\For_) {
                    foreach (array_merge($node->init, $node->loop) as $expr) {
                        $this->expression($expr, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
                    }
                }
                $bodyState = $state->copy();
                if ($this->containsIndexedCollectionAccess($node->stmts, $state)) {
                    $bodyState->iterationContext = 'loop';
                    $bodyState->iterationConfidence = 'medium';
                    $this->statements($node->stmts, $file, $index, $collector, $rules, $bodyState, true, null, $resourceModel);
                } else {
                    $this->statements($node->stmts, $file, $index, $collector, $rules, $bodyState, false, null, $resourceModel);
                }
                continue;
            }
            if ($node instanceof Stmt\Expression) {
                $this->expression($node->expr, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
                continue;
            }
            if ($node instanceof Stmt\Unset_) {
                foreach ($node->vars as $variable) {
                    if ($variable instanceof Expr\Variable && is_string($variable->name)) {
                        $state->vars[$variable->name] = ValueFact::unknown();
                    }
                }
                continue;
            }
            $this->walkNode($node, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
        }
    }

    private function expression(?Node $node, FlowState $state, SourceIndex $index, string $file, FindingCollector $collector, array $rules, bool $inIteration, ?string $resourceModel): ?ValueFact
    {
        if (!$node) {
            return null;
        }
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            return $state->vars[$node->name] ?? null;
        }
        if ($node instanceof Expr\ArrayDimFetch) {
            $collection = $this->expression($node->var, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
            if ($collection?->kind === 'collection' && $collection->model) {
                return ValueFact::model($collection->model, true, $collection->loaded, $collection->aggregates);
            }
            return ValueFact::unknown();
        }
        if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $fact = $this->expression($node->expr, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
            $state->vars[$node->var->name] = $fact?->copy() ?? ValueFact::unknown();
            return $fact;
        }
        if ($node instanceof Expr\AssignOp && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $this->expression($node->expr, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
            $state->vars[$node->var->name] = ValueFact::unknown();
            return $state->vars[$node->var->name];
        }
        if ($node instanceof Expr\ClassConstFetch && $node->name instanceof Node\Identifier && $node->name->toString() === 'class') {
            return ValueFact::model(ltrim($node->class->toString(), '\\'));
        }
        if ($node instanceof Expr\StaticCall) {
            return $this->staticCall($node, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
        }
        if ($node instanceof Expr\MethodCall) {
            return $this->methodCall($node, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
        }
        if ($node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch) {
            return $this->property($node, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
        }
        $this->walkNode($node, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
        return null;
    }

    private function staticCall(Expr\StaticCall $call, FlowState $state, SourceIndex $index, string $file, FindingCollector $collector, array $rules, bool $inIteration, ?string $resourceModel): ?ValueFact
    {
        $class = ltrim($call->class->toString(), '\\');
        $name = $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        $model = $index->model($class);
        if (!$model || !$name) {
            foreach ($call->args as $arg) {
                $this->expression($arg->value, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
            }
            return null;
        }
        $loaded = [];
        if (in_array($name, ['with', 'withOnly'], true)) {
            $loaded = $this->relationArguments($call->args);
        }
        if (in_array($name, ['withCount', 'withExists'], true)) {
            return ValueFact::builder($class, [], $this->relationArguments($call->args));
        }
        if (in_array($name, ['all', 'get', 'cursor', 'lazy'], true)) {
            return ValueFact::collection($class, $model->with, $loaded);
        }
        if (in_array($name, ['query', 'where', 'whereIn', 'whereNull', 'whereNotNull', 'orderBy', 'latest', 'oldest', 'select', 'addSelect', 'limit', 'offset', 'take', 'skip', 'distinct'], true)) {
            return ValueFact::builder($class, $model->with, $loaded);
        }
        if (in_array($name, ['find', 'findOrFail', 'first', 'firstOrFail', 'sole'], true)) {
            return ValueFact::model($class);
        }
        return null;
    }

    private function methodCall(Expr\MethodCall $call, FlowState $state, SourceIndex $index, string $file, FindingCollector $collector, array $rules, bool $inIteration, ?string $resourceModel): ?ValueFact
    {
        $name = $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        $receiver = $this->expression($call->var, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
        if (!$name) {
            return null;
        }
        if ($receiver?->kind === 'model' && $index->relationship($receiver->model ?? '', $name)) {
            $relation = $index->relationship($receiver->model ?? '', $name);
            if ($call->getAttribute('nplusone_query_terminal') === true) {
                return null;
            }
            return ValueFact::relationQuery($receiver->model, $name, $receiver->iterable);
        }
        if ($receiver?->kind === 'relation_query' && in_array($name, self::QUERY_TERMINALS, true)) {
            if ($inIteration && $state->iterationContext !== 'none' && $receiver->iterable) {
                $this->emitQuery($call, $file, $index, $collector, $rules, $receiver, $name, $state);
            }
            return $name === 'get' ? ValueFact::collection($receiver->model, [], []) : ValueFact::unknown();
        }
        if ($receiver?->kind === 'builder') {
            if (in_array($name, ['with', 'withOnly'], true)) {
                $receiver->loaded = array_values(array_unique(array_merge($receiver->loaded, $this->relationArguments($call->args))));
                return $receiver;
            }
            if (in_array($name, ['withCount', 'withExists'], true)) {
                $receiver->aggregates = array_values(array_unique(array_merge($receiver->aggregates, $this->relationArguments($call->args))));
                return $receiver;
            }
            if (in_array($name, ['get', 'all', 'cursor', 'lazy'], true)) {
                return ValueFact::collection($receiver->model, $receiver->loaded, $receiver->aggregates);
            }
            if (in_array($name, ['first', 'firstOrFail', 'sole'], true)) {
                return ValueFact::model($receiver->model, false, $receiver->loaded, $receiver->aggregates);
            }
            return $receiver;
        }
        if ($receiver?->kind === 'collection') {
            if (in_array($name, ['load', 'loadMissing'], true)) {
                $receiver->loaded = array_values(array_unique(array_merge($receiver->loaded, $this->relationArguments($call->args))));
                return $receiver;
            }
            if (in_array($name, ['withRelationshipAutoloading'], true)) {
                $receiver->autoloading = true;
                return $receiver;
            }
            if (in_array($name, ['each', 'map'], true) && isset($call->args[0]->value) && ($call->args[0]->value instanceof Expr\Closure || $call->args[0]->value instanceof Expr\ArrowFunction)) {
                $this->callback($call->args[0]->value, $receiver, $file, $index, $collector, $rules, $resourceModel);
                return $name === 'map' ? ValueFact::unknown() : $receiver;
            }
        }
        if ($receiver?->kind === 'model' && in_array($name, ['loadCount', 'loadExists'], true)) {
            if ($inIteration && $state->iterationContext !== 'none' && $receiver->iterable) {
                foreach ($this->relationArguments($call->args) as $relation) {
                    $this->emitEvent(new AnalysisEvent(
                        AnalysisEvent::QUERY,
                        $call,
                        $file,
                        $receiver->model,
                        $relation,
                        $name,
                        $state->iterationContext,
                        $state->iterationConfidence,
                        'Load this aggregate once before iteration.',
                    ), $index, $collector, $rules);
                }
            }
            $receiver->aggregates = array_values(array_unique(array_merge($receiver->aggregates, $this->relationArguments($call->args))));
            return $receiver;
        }
        if ($receiver?->kind === 'model' && in_array($name, ['load', 'loadMissing'], true)) {
            $receiver->loaded = array_values(array_unique(array_merge($receiver->loaded, $this->relationArguments($call->args))));
            if ($inIteration && $state->iterationContext !== 'none' && $receiver->iterable) {
                foreach ($this->relationArguments($call->args) as $relation) {
                    $this->emitEvent(new AnalysisEvent(
                        AnalysisEvent::QUERY,
                        $call,
                        $file,
                        $receiver->model,
                        $relation,
                        'load',
                        $state->iterationContext,
                        $state->iterationConfidence,
                        'Load this relationship once on the collection before iteration.',
                    ), $index, $collector, $rules);
                }
            }
            return $receiver;
        }
        return null;
    }

    private function property(Node $property, FlowState $state, SourceIndex $index, string $file, FindingCollector $collector, array $rules, bool $inIteration, ?string $resourceModel): ?ValueFact
    {
        $name = $property->name instanceof Node\Identifier ? $property->name->toString() : null;
        if (!$name) {
            return null;
        }
        if ($property->var instanceof Expr\Variable && $property->var->name === 'this' && $resourceModel) {
            if ($index->relationship($resourceModel, $name)) {
                $this->emitEvent(new AnalysisEvent(
                    AnalysisEvent::RESOURCE,
                    $property,
                    $file,
                    $resourceModel,
                    $name,
                    null,
                    'resource',
                    'low',
                    'Check that the resource collection eager loads this relationship.',
                ), $index, $collector, $rules);
            }
            return ValueFact::unknown();
        }
        $receiver = $this->expression($property->var, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
        if ($receiver?->kind !== 'model') {
            return null;
        }
        if ($this->isAggregateAttribute($name, $receiver->aggregates)) {
            return ValueFact::unknown();
        }
        if (!$index->relationship($receiver->model ?? '', $name)) {
            return null;
        }
        if (!$inIteration || in_array($name, $receiver->loaded, true) || $receiver->autoloading) {
            return ValueFact::unknown();
        }
        if ($state->iterationContext !== 'none' && $receiver->iterable) {
            $this->emitEvent(new AnalysisEvent(
                AnalysisEvent::PROPERTY,
                $property,
                $file,
                $receiver->model,
                $name,
                null,
                $state->iterationContext,
                $state->iterationConfidence,
                'Consider eager loading ' . $name . ' before iterating.',
            ), $index, $collector, $rules);
        }
        return ValueFact::unknown();
    }

    private function callback(Expr\Closure|Expr\ArrowFunction $callback, ValueFact $collection, string $file, SourceIndex $index, FindingCollector $collector, array $rules, ?string $resourceModel): void
    {
        $state = new FlowState();
        $parameter = $callback->params[0]->var ?? null;
        if ($parameter instanceof Expr\Variable && is_string($parameter->name)) {
            $state->vars[$parameter->name] = ValueFact::model($collection->model, true, $collection->loaded, $collection->aggregates);
        }
        $body = $callback instanceof Expr\Closure ? $callback->stmts : [new Stmt\Expression($callback->expr)];
        $state->iterationContext = 'callback';
        $state->iterationConfidence = 'high';
        $this->statements($body, $file, $index, $collector, $rules, $state, true, $collection->model, $resourceModel);
    }

    private function emitQuery(Expr\MethodCall $call, string $file, SourceIndex $index, FindingCollector $collector, array $rules, ValueFact $fact, string $operation, FlowState $state): void
    {
        $suggestion = match ($operation) {
            'count' => "Consider withCount('{$fact->relationship}').",
            'exists' => "Consider withExists('{$fact->relationship}').",
            default => 'Consider batching this relationship query before iteration.',
        };
        $this->emitEvent(new AnalysisEvent(
            AnalysisEvent::QUERY,
            $call,
            $file,
            $fact->model,
            $fact->relationship,
            $operation,
            $state->iterationContext,
            $state->iterationConfidence,
            $suggestion,
        ), $index, $collector, $rules);
    }

    private function emitEvent(AnalysisEvent $event, SourceIndex $index, FindingCollector $collector, array $rules): void
    {
        $this->ruleEngine->emit($event, $index, $rules, $collector);
    }

    private function walkNode(Node $node, FlowState $state, SourceIndex $index, string $file, FindingCollector $collector, array $rules, bool $inIteration, ?string $resourceModel): void
    {
        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};
            if ($child instanceof Node) {
                $this->expression($child, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
            } elseif (is_array($child)) {
                foreach ($child as $item) {
                    if ($item instanceof Node) {
                                        $this->expression($item, $state, $index, $file, $collector, $rules, $inIteration, $resourceModel);
                    }
                }
            }
        }
    }

    /** @return list<string> */
    private function relationArguments(array $args): array
    {
        $result = [];
        foreach ($args as $arg) {
            $value = $arg->value;
            if ($value instanceof Node\Scalar\String_) {
                $result[] = (string) strtok($value->value, '.');
            }
            if ($value instanceof Expr\Array_) {
                foreach ($value->items as $item) {
                    if ($item && $item->value instanceof Node\Scalar\String_) {
                                        $result[] = (string) strtok($item->value->value, '.');
                    }
                }
            }
        }
        return array_values(array_unique(array_filter($result)));
    }

    private function className(Stmt\Class_ $class): string
    {
        return ltrim(($class->namespacedName ?? $class->name)?->toString() ?? '', '\\');
    }

    /** @param list<string> $aggregates */
    private function isAggregateAttribute(string $name, array $aggregates): bool
    {
        foreach ($aggregates as $aggregate) {
            if ($name === $aggregate . '_count' || $name === $aggregate . '_exists') {
                return true;
            }
        }
        return false;
    }

    /** @param list<Node> $nodes */
    private function containsIndexedCollectionAccess(array $nodes, FlowState $state): bool
    {
        foreach ($nodes as $node) {
            if (!$node instanceof Node) {
                continue;
            }
            if ($node instanceof Expr\ArrayDimFetch && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
                if (($state->vars[$node->var->name] ?? null)?->kind === 'collection') {
                    return true;
                }
            }
            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};
                if ($child instanceof Node && $this->containsIndexedCollectionAccess([$child], $state)) {
                    return true;
                }
                if (is_array($child) && $this->containsIndexedCollectionAccess($child, $state)) {
                    return true;
                }
            }
        }
        return false;
    }
}
