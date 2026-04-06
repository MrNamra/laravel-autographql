<?php

namespace MrNamra\AutoGraphQL;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Database\Eloquent\Relations\{
    HasOne, HasMany, BelongsTo, BelongsToMany,
    HasManyThrough, HasOneThrough, MorphMany, MorphTo, MorphOne
};

class RelationshipDetector
{
    /**
     * All known Eloquent relationship classes.
     */
    private array $relationClasses = [
        HasOne::class,
        HasMany::class,
        BelongsTo::class,
        BelongsToMany::class,
        HasManyThrough::class,
        HasOneThrough::class,
        MorphMany::class,
        MorphTo::class,
        MorphOne::class,
    ];

    /**
     * Inspect a model class and return all its detected relationships.
     *
     * @return array<string, array{
     *   type: string,
     *   related_model: string,
     *   graphql_type: string,
     *   is_list: bool
     * }>
     */
    public function detect(string $modelClass): array
    {
        $reflection = new ReflectionClass($modelClass);
        $relations  = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip inherited methods, constructors, etc.
            if ($method->getDeclaringClass()->getName() !== $modelClass) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (!$returnType || $returnType->isBuiltin()) {
                continue;
            }
            
            $typeName = $returnType->getName();

            if (!$this->isRelation($typeName)) {
                continue;
            }

            try {
                $relatedModel = $this->resolveRelatedModel($modelClass, $method->getName());
                $isList       = $this->isListRelation($typeName);
                $graphqlType  = $this->buildGraphQLType($relatedModel, $isList);

                $relations[$method->getName()] = [
                    'type'          => $typeName,
                    'related_model' => $relatedModel,
                    'graphql_type'  => $graphqlType,
                    'is_list'       => $isList,
                ];
            } catch (\Throwable $e) {
                // Skip if relation cannot be resolved (e.g. requires runtime state)
                continue;
            }
        }

        return $relations;
    }

    private function isRelation(string $type): bool
    {
        if (in_array($type, $this->relationClasses)) {
            return true;
        }
        
        if (class_exists($type)) {
            return is_subclass_of($type, \Illuminate\Database\Eloquent\Relations\Relation::class);
        }
        
        return false;
    }

    /**
     * HasMany, BelongsToMany, MorphMany → returns a list ([Post!]!)
     */
    private function isListRelation(string $type): bool
    {
        return in_array($type, [
            HasMany::class,
            BelongsToMany::class,
            HasManyThrough::class,
            MorphMany::class,
        ]);
    }

    private function resolveRelatedModel(string $modelClass, string $methodName): string
    {
        // Instantiate model temporarily and call the relation method
        // Note: This may be heavy but it's the most reliable way to get related model
        $model    = new $modelClass;
        $relation = $model->$methodName();
        return get_class($relation->getRelated());
    }

    private function buildGraphQLType(string $modelClass, bool $isList): string
    {
        $typeName = class_basename($modelClass);
        return $isList ? "[{$typeName}!]!" : $typeName;
    }
}
