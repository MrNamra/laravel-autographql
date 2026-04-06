<?php

namespace MrNamra\AutoGraphQL;

use GraphQL\Type\Definition\{Type, ObjectType, InputObjectType};
use Illuminate\Support\Str;

class MutationGenerator
{
    private array $cachedTypes = [];

    /**
     * Convert a write route (POST, PUT, DELETE) into a GraphQL mutation definition.
     */
    public function generate(array $route, ObjectType $type, array $inputFields): array
    {
        $method     = strtoupper($route['method']);
        $modelName  = class_basename($route['model']);
        $attribute  = $route['attribute'];
        
        $inputType  = $this->buildInputType($modelName, $inputFields, $method);

        return match ($method) {
            'POST'   => $this->createMutation($type, $inputType, $route, $modelName, $attribute),
            'PUT',
            'PATCH'  => $this->updateMutation($type, $inputType, $route, $modelName, $attribute),
            'DELETE' => $this->deleteMutation($route, $modelName, $attribute),
            default  => [],
        };
    }

    private function createMutation(ObjectType $type, InputObjectType $inputType, array $route, string $name, $attr): array
    {
        $mutationName = ($attr && $attr->mutation) ? $attr->mutation : 'create' . $name;
        
        return [
            $mutationName => [
                'type'        => $type,
                'description' => $attr->description ?? "Create a new {$name}",
                'args'        => ['input' => ['type' => Type::nonNull($inputType)]],
                'resolve'     => fn($r, $args, $c, $info) => app(ResolverProxy::class)
                                    ->resolve($route['controller'], $route['action'], $args['input'], $route['model'], $info->getFieldSelection(5)),
            ]
        ];
    }

    private function updateMutation(ObjectType $type, InputObjectType $inputType, array $route, string $name, $attr): array
    {
        $mutationName = ($attr && $attr->mutation) ? $attr->mutation : 'update' . $name;
        
        return [
            $mutationName => [
                'type'        => $type,
                'description' => $attr->description ?? "Update an existing {$name}",
                'args'        => [
                    'id'    => ['type' => Type::nonNull(Type::id())],
                    'input' => ['type' => Type::nonNull($inputType)],
                ],
                'resolve'     => fn($r, $args, $c, $info) => app(ResolverProxy::class)
                                    ->resolve($route['controller'], $route['action'],
                                        array_merge(['id' => $args['id']], $args['input']), $route['model'], $info->getFieldSelection(5)),
            ]
        ];
    }

    private function deleteMutation(array $route, string $name, $attr): array
    {
        $mutationName = ($attr && $attr->mutation) ? $attr->mutation : 'delete' . $name;
        
        return [
            $mutationName => [
                'type'        => new ObjectType(['name' => "Delete{$name}Result", 'fields' => [
                    'success' => ['type' => Type::nonNull(Type::boolean())],
                    'message' => ['type' => Type::string()],
                ]]),
                'description' => $attr->description ?? "Delete a {$name}",
                'args'        => ['id' => ['type' => Type::nonNull(Type::id())]],
                'resolve'     => fn($r, $args) => app(ResolverProxy::class)
                                    ->resolve($route['controller'], $route['action'], $args, $route['model']),
            ]
        ];
    }

    private function buildInputType(string $name, array $fields, string $method): InputObjectType
    {
        $inputFields = [];
        
        foreach ($fields as $field => $typeStr) {
            // Skip ID and timestamps for inputs
            if (in_array(strtolower($field), ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $graphqlType = $this->mapStringToType($typeStr);
            
            // Make relationship fields and nested types optional in the input
            if (str_contains($typeStr, '[') || str_contains($typeStr, 'Object')) {
                $graphqlType = $this->toNullable($graphqlType);
            }

            // For PUT, make everything nullable
            if ($method === 'PUT' || $method === 'PATCH') {
                $graphqlType = $this->toNullable($graphqlType);
            }

            $inputFields[$field] = ['type' => $graphqlType];
        }

        $typeName = "{$name}" . ($method === 'POST' ? 'CreateInput' : 'UpdateInput');

        if (isset($this->cachedTypes[$typeName])) {
            return $this->cachedTypes[$typeName];
        }

        $this->cachedTypes[$typeName] = new InputObjectType([
            'name'   => $typeName,
            'fields' => $inputFields,
        ]);

        return $this->cachedTypes[$typeName];
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

    private function toNullable(Type $type): Type
    {
        if ($type instanceof \GraphQL\Type\Definition\NonNull) {
            return $type->getWrappedType();
        }
        return $type;
    }
}
