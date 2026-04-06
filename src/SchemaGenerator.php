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
            if (!$route['model']) continue;

            $type = $this->modelTypes[$route['model']];

            switch (strtoupper($route['method'])) {
                case 'GET':
                    $queries = array_merge($queries, $this->queryGen->generate($route, $type));
                    break;
                case 'POST':
                case 'PUT':
                case 'PATCH':
                case 'DELETE':
                    $mutations = array_merge($mutations, $this->mutationGen->generate($route, $type, $this->typeGen->fromModel($route['model'], true)));
                    break;
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
