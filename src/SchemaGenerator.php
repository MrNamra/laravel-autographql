<?php

namespace MrNamra\AutoGraphQL;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\{ObjectType, Type};
use Illuminate\Support\Str;

class SchemaGenerator
{
    private array $modelTypes = [];

    public function __construct(
        private RouteScanner      $scanner,
        private TypeGenerator     $typeGen,
        private QueryGenerator    $queryGen,
        private MutationGenerator $mutationGen,
    ) {}

    public function generate(): Schema
    {
        $routes     = $this->scanner->getApiRoutes();
        $queries    = [];
        $mutations  = [];

        // 1. Build a GraphQL ObjectType for each unique model
        foreach ($routes as $route) {
            if (!$route['model'] || isset($this->modelTypes[$route['model']])) {
                continue;
            }

            $modelClass = $route['model'];
            $typeName   = class_basename($modelClass);
            
            $this->modelTypes[$modelClass] = new ObjectType([
                'name'   => $typeName,
                'fields' => fn() => $this->buildFields($modelClass),
            ]);
        }

        // 2. Build queries and mutations from routes
        foreach ($routes as $route) {
            $attribute = $route['attribute'];
            $type      = null;

            if ($attribute && $attribute->response) {
                $customName = Str::studly($attribute->query ?: ($attribute->mutation ?: $route['action'])) . 'Response';
                $type = $this->buildCustomType($customName, $attribute->response);
            } elseif ($route['model']) {
                $type = $this->modelTypes[$route['model']];
            }

            if (!$type) continue;

            $method = strtoupper($route['method']);
            if ($method === 'GET') {
                $queries = array_merge($queries, $this->queryGen->generate($route, $type));
            } else {
                $inputFields = $route['model'] ? $this->typeGen->fromModel($route['model'], true) : [];
                $mutations = array_merge($mutations, $this->mutationGen->generate($route, $type, $inputFields));
            }
        }

        $schema = new Schema([
            'query'    => new ObjectType(['name' => 'Query',    'fields' => $queries]),
            'mutation' => !empty($mutations) ? new ObjectType(['name' => 'Mutation', 'fields' => $mutations]) : null,
        ]);

        return $schema;
    }

    /**
     * Resolve model fields, including links to other registered ObjectTypes.
     */
    private function buildFields(string $modelClass): array
    {
        $rawFields = $this->typeGen->fromModel($modelClass);
        $detector  = app(RelationshipDetector::class);
        $relations = $detector->detect($modelClass);
        $fields    = [];

        foreach ($rawFields as $name => $typeStr) {
            // If it's a relationship, link to the existing ObjectType if possible
            if (isset($relations[$name])) {
                $relatedModel = $relations[$name]['related_model'];
                $isList       = $relations[$name]['is_list'];
                
                if (isset($this->modelTypes[$relatedModel])) {
                    $relatedType = $this->modelTypes[$relatedModel];
                    $fields[$name] = [
                        'type' => $isList ? Type::nonNull(Type::listOf(Type::nonNull($relatedType))) : $relatedType,
                    ];
                    continue;
                }
            }

            // Otherwise, treat as a scalar
            $fields[$name] = ['type' => $this->mapStringToType($typeStr)];
        }

        return $fields;
    }

    private function buildCustomType(string $name, array $fields): ObjectType
    {
        $graphqlFields = [];
        foreach ($fields as $fieldName => $typeDefinition) {
            if (is_array($typeDefinition)) {
                // Nested structure
                $graphqlFields[$fieldName] = [
                    'type' => $this->buildCustomType(Str::studly($name . '_' . $fieldName), $typeDefinition)
                ];
            } elseif (is_string($typeDefinition) && class_exists($typeDefinition)) {
                // Link to existing model type if it exists
                if (isset($this->modelTypes[$typeDefinition])) {
                    $graphqlFields[$fieldName] = ['type' => $this->modelTypes[$typeDefinition]];
                } else {
                    // Ad-hoc model type generation
                    $this->modelTypes[$typeDefinition] = new ObjectType([
                        'name'   => class_basename($typeDefinition),
                        'fields' => fn() => $this->buildFields($typeDefinition),
                    ]);
                    $graphqlFields[$fieldName] = ['type' => $this->modelTypes[$typeDefinition]];
                }
            } else {
                // Scalar type
                $graphqlFields[$fieldName] = ['type' => $this->mapStringToType($typeDefinition)];
            }
        }

        return new ObjectType([
            'name'   => $name,
            'fields' => $graphqlFields,
        ]);
    }

    private function mapStringToType(string $typeStr): Type
    {
        $rawType = rtrim($typeStr, '!');
        $typeMap = [
            'ID'      => Type::id(),
            'Int'     => Type::int(),
            'String'  => Type::string(),
            'Boolean' => Type::boolean(),
            'Float'   => Type::float(),
        ];

        $type = $typeMap[$rawType] ?? Type::string();

        if (str_ends_with($typeStr, '!')) {
            $type = Type::nonNull($type);
        }

        return $type;
    }
}
