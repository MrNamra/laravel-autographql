<?php

namespace MrNamra\AutoGraphQL;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TypeGenerator
{
    /**
     * Mapping of database column types to GraphQL scalar types.
     */
    private array $typeMap = [
        'bigint'    => 'ID',
        'integer'   => 'Int',
        'int'       => 'Int',
        'smallint'  => 'Int',
        'tinyint'   => 'Int',
        'varchar'   => 'String',
        'char'      => 'String',
        'text'      => 'String',
        'mediumtext'=> 'String',
        'longtext'  => 'String',
        'boolean'   => 'Boolean',
        'decimal'   => 'Float',
        'float'     => 'Float',
        'double'    => 'Float',
        'datetime'  => 'String',
        'timestamp' => 'String',
        'date'      => 'String',
        'json'      => 'String',
        'jsonb'     => 'String',
        'uuid'      => 'ID',
    ];

    /**
     * Map a model class to a set of GraphQL fields.
     */
    public function fromModel(string $modelClass, bool $includeHidden = false): array
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model   = new $modelClass;
        $table   = $model->getTable();
        $fields  = [];

        // DB Abstraction for column listing
        $columns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            // Respect Eloquent's $hidden property unless override requested
            if (!$includeHidden && in_array($column, $model->getHidden())) {
                continue;
            }

            try {
                $dbType      = Schema::getColumnType($table, $column);
                $graphqlType = $this->typeMap[$dbType] ?? 'String';

                // Mark primary key as non-nullable ID
                if ($column === $model->getKeyName()) {
                    $fields[$column] = 'ID!';
                } else {
                    $fields[$column] = $graphqlType;
                }
            } catch (\Exception $e) {
                $fields[$column] = 'String';
            }
        }

        // Add auto-detected relationship fields
        $relationships = app(RelationshipDetector::class)->detect($modelClass);
        foreach ($relationships as $name => $meta) {
            $fields[$name] = $meta['graphql_type'];
        }

        return $fields;
    }
}
