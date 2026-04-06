<?php

namespace MrNamra\AutoGraphQL;

use GraphQL\Type\Definition\{Type, ObjectType};
use Illuminate\Support\Str;

class QueryGenerator
{
    /**
     * Map a GET route to a GraphQL query field definition.
     */
    public function generate(array $route, ObjectType $type): array
    {
        $hasIdParam = in_array('id', $route['parameters']);
        $attribute = $route['attribute'];
        $queryName = ($attribute && $attribute->query) 
            ? $attribute->query 
            : ($hasIdParam 
                ? lcfirst(class_basename($route['model'])) 
                : Str::plural(lcfirst(class_basename($route['model']))));

        if ($hasIdParam) {
            // Single item query
            return [
                $queryName => [
                    'type'        => $type,
                    'description' => $attribute->description ?? "Fetch a single " . class_basename($route['model']),
                    'args'        => [
                        'id'    => ['type' => Type::id()],
                        'first' => ['type' => Type::int()],
                        'last'  => ['type' => Type::int()],
                    ],
                    'resolve'     => $this->buildResolver($route),
                ]
            ];
        }

        // List query with pagination and search
        return [
            $queryName => [
                'type'        => Type::listOf($type),
                'description' => $attribute->description ?? "Fetch all " . class_basename($route['model']),
                'args'        => [
                    'page'          => ['type' => Type::int(), 'defaultValue' => 1],
                    'per_page'      => ['type' => Type::int(), 'defaultValue' => config('autographql.default_limit', 20)],
                    'limit'         => ['type' => Type::int(), 'defaultValue' => config('autographql.default_limit', 20)],
                    'offset'        => ['type' => Type::int(), 'defaultValue' => 0],
                    'search'        => ['type' => Type::string()],
                    'search_column' => ['type' => Type::string()],
                ],
                'resolve'     => $this->buildResolver($route),
            ]
        ];
    }

    private function buildResolver(array $route): callable
    {
        return function ($root, array $args, $context, $info) use ($route) {
            $selection = $info->getFieldSelection(5); // Depth control
            return app(ResolverProxy::class)->resolve(
                $route['controller'],
                $route['action'],
                $args,
                $route['model'],
                $selection
            );
        };
    }
}
