<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

final class ModelMetadataResolver
{
    private const RELATION_METHODS = [
        'hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphOne', 'morphMany',
        'morphTo', 'morphToMany', 'morphedByMany', 'hasOneThrough', 'hasManyThrough',
    ];

    /** @param list<Node> $ast */
    public function index(array $ast, SourceIndex $index, array $resourceModels = []): void
    {
        foreach ($ast as $node) {
            $this->visit($node, $index, $resourceModels);
        }
    }

    private function visit(Node $node, SourceIndex $index, array $resourceModels): void
    {
        if ($node instanceof Stmt\Class_) {
            $class = $this->className($node);
            $parent = $node->extends?->toString();
            $isModel = $this->isModel($parent);
            $relationships = [];
            $with = [];
            foreach ($node->stmts as $statement) {
                if ($statement instanceof Stmt\Property && $statement->props && $statement->props[0]->name->toString() === 'with') {
                    $with = $this->literalStrings($statement->props[0]->default);
                }
                if ($statement instanceof Stmt\ClassMethod && $statement->isPublic() && $statement->name->toString()) {
                    $relationship = $this->relationship($statement);
                    if ($relationship) {
                        $relationships[$statement->name->toString()] = $relationship;
                    }
                }
            }
            if ($isModel) {
                $index->addModel(new ModelMetadata($class, $parent, $relationships, $with));
            }
            if ($this->isResource($parent)) {
                $mapped = $resourceModels[$class] ?? $this->mixinModel($node->getDocComment()?->getText());
                $index->addResource($class, is_string($mapped) ? ltrim($mapped, '\\') : null);
            }
            foreach ($node->stmts as $statement) {
                $this->visitNested($statement, $index, $resourceModels);
            }
            return;
        }
        $this->visitNested($node, $index, $resourceModels);
    }

    private function visitNested(Node $node, SourceIndex $index, array $resourceModels): void
    {
        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->{$name};
            if ($value instanceof Node) {
                $this->visit($value, $index, $resourceModels);
            } elseif (is_array($value)) {
                foreach ($value as $child) {
                    if ($child instanceof Node) {
                        $this->visit($child, $index, $resourceModels);
                    }
                }
            }
        }
    }

    private function relationship(Stmt\ClassMethod $method): ?RelationshipMetadata
    {
        $returnType = $method->returnType instanceof Node ? ltrim($method->returnType->toString(), '\\') : null;
        $body = $method->stmts ?? [];
        foreach ($body as $statement) {
            if (!$statement instanceof Stmt\Return_ || !$statement->expr instanceof Expr\MethodCall) {
                continue;
            }
            $call = $statement->expr;
            $name = $call->name instanceof Node\Identifier ? $call->name->toString() : null;
            if ($name && in_array($name, self::RELATION_METHODS, true)) {
                $related = isset($call->args[0]) && $call->args[0]->value instanceof Expr\ClassConstFetch
                    ? ltrim($call->args[0]->value->class->toString(), '\\') : null;
                return new RelationshipMetadata($method->name->toString(), $returnType ?? $name, $related);
            }
        }
        if ($returnType && str_contains($returnType, 'Relations\\')) {
            return new RelationshipMetadata($method->name->toString(), $returnType);
        }
        return null;
    }

    private function className(Stmt\Class_ $class): string
    {
        $name = $class->namespacedName ?? $class->name;
        return $name ? ltrim($name->toString(), '\\') : '';
    }

    private function isModel(?string $parent): bool
    {
        return $parent && (str_ends_with($parent, '\\Model') || str_ends_with($parent, '\\User'));
    }

    private function isResource(?string $parent): bool
    {
        return $parent && (str_ends_with($parent, '\\JsonResource') || str_ends_with($parent, '\\Resource'));
    }

    private function mixinModel(?string $doc): ?string
    {
        if ($doc && preg_match('/@mixin\\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)/', $doc, $match)) {
            return $match[1];
        }
        return null;
    }

    /** @return list<string> */
    private function literalStrings(?Node $node): array
    {
        if ($node instanceof Node\Scalar\String_) {
            return [$node->value];
        }
        if ($node instanceof Expr\Array_) {
            $values = [];
            foreach ($node->items as $item) {
                if ($item && $item->value instanceof Node\Scalar\String_) {
                    $values[] = $item->value->value;
                } elseif ($item && $item->key instanceof Node\Scalar\String_) {
                    $values[] = $item->key->value;
                }
            }
            return $values;
        }
        return [];
    }
}
