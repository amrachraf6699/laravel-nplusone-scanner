<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

final class SourceIndex
{
    /** @var array<string, ModelMetadata> */
    private array $models = [];
    /** @var array<string, ModelMetadata> */
    private array $declarations = [];
    /** @var array<string, string> */
    private array $resources = [];
    /** @var list<array{code:string, message:string}> */
    private array $diagnostics = [];

    public function addModel(ModelMetadata $model): void
    {
        $this->models[$model->class] = $model;
    }

    public function addDeclaration(ModelMetadata $declaration): void
    {
        if (isset($this->declarations[$declaration->class])) {
            $this->diagnostics[] = [
                'code' => 'duplicate_declaration',
                'message' => 'Duplicate class declaration: ' . $declaration->class,
            ];
        }
        $this->declarations[$declaration->class] = $declaration;
    }

    public function finalizeModels(): void
    {
        foreach ($this->declarations as $class => $declaration) {
            if ($this->isEloquentDescendant($class, [])) {
                $this->models[$class] = $declaration;
            }
        }
    }

    public function addResource(string $class, ?string $model): void
    {
        if ($model) {
            $this->resources[$class] = $model;
        } else {
            $this->diagnostics[] = [
                'code' => 'unresolved_resource',
                'message' => 'Unable to establish the model for Resource: ' . $class,
            ];
        }
    }

    /** @return list<array{code:string, message:string}> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function model(string $class): ?ModelMetadata
    {
        $class = ltrim($class, '\\');
        if (isset($this->models[$class])) {
            return $this->models[$class];
        }
        return null;
    }

    public function relationship(string $model, string $relationship): ?RelationshipMetadata
    {
        $metadata = $this->model($model);
        if (!$metadata) {
            return null;
        }
        if (isset($metadata->relationships[$relationship])) {
            return $metadata->relationships[$relationship];
        }
        return $metadata->parent ? $this->relationship($metadata->parent, $relationship) : null;
    }

    public function resourceModel(string $resource): ?string
    {
        return $this->resources[ltrim($resource, '\\')] ?? null;
    }

    private function isEloquentDescendant(string $class, array $seen): bool
    {
        if (isset($seen[$class])) {
            return false;
        }
        $seen[$class] = true;
        $parent = $this->declarations[$class]->parent ?? null;
        if ($parent === 'Illuminate\\Database\\Eloquent\\Model' || $parent === 'Illuminate\\Foundation\\Auth\\User') {
            return true;
        }
        return $parent !== null && isset($this->declarations[$parent])
            && $this->isEloquentDescendant($parent, $seen);
    }
}
