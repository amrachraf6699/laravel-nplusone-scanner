<?php

declare(strict_types=1);

namespace Amrachraf6699\LaravelNPlusOneScanner\Analysis;

final class SourceIndex
{
    /** @var array<string, ModelMetadata> */
    private array $models = [];
    /** @var array<string, string> */
    private array $resources = [];

    public function addModel(ModelMetadata $model): void
    {
        $this->models[$model->class] = $model;
    }

    public function addResource(string $class, ?string $model): void
    {
        if ($model) {
            $this->resources[$class] = $model;
        }
    }

    public function model(string $class): ?ModelMetadata
    {
        $class = ltrim($class, '\\');
        if (isset($this->models[$class])) {
            return $this->models[$class];
        }
        foreach ($this->models as $name => $metadata) {
            if (str_ends_with($name, '\\' . $class) || $name === $class) {
                return $metadata;
            }
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
}
