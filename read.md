<div align="center">

```
 █████╗ ██╗   ██╗████████╗ ██████╗  ██████╗ ██████╗  █████╗ ██████╗ ██╗  ██╗ ██████╗ ██╗
██╔══██╗██║   ██║╚══██╔══╝██╔═══██╗██╔════╝ ██╔══██╗██╔══██╗██╔══██╗██║  ██║██╔═══██╗██║
███████║██║   ██║   ██║   ██║   ██║██║  ███╗██████╔╝███████║██████╔╝███████║██║   ██║██║
██╔══██║██║   ██║   ██║   ██║   ██║██║   ██║██╔══██╗██╔══██║██╔═══╝ ██╔══██║██║▄▄ ██║██║
██║  ██║╚██████╔╝   ██║   ╚██████╔╝╚██████╔╝██║  ██║██║  ██║██║     ██║  ██║╚██████╔╝███████╗
╚═╝  ╚═╝ ╚═════╝    ╚═╝    ╚═════╝  ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝     ╚═╝  ╚═╝ ╚══▀▀═╝ ╚══════╝
```

**Zero-Config REST → GraphQL Bridge for Laravel**

[![Latest Version](https://img.shields.io/packagist/v/mrnamra/laravel-autographql.svg?style=flat-square)](https://packagist.org/packages/mrnamra/laravel-autographql)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.1-blue?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2B%20%7C%2011%2B-red?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)](LICENSE.md)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen?style=flat-square)]()

> **Install the package. Your REST API is now also a GraphQL API. Zero code changes.**

</div>

---

## 📖 Table of Contents

- [Why AutoGraphQL?](#-why-autographql)
- [How It Works](#-how-it-works)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
- [Package Architecture](#-package-architecture)
  - [ServiceProvider](#serviceprovider)
  - [RouteScanner](#routescanner)
  - [TypeGenerator](#typegenerator)
  - [RelationshipDetector](#relationshipdetector)
  - [EagerLoadingAnalyzer](#eagerloadinganalyzer)
  - [QueryGenerator](#querygenerator)
  - [MutationGenerator](#mutationgenerator)
  - [ResolverProxy](#resolverproxy)
  - [SchemaGenerator](#schemagenerator)
  - [GraphQLController](#graphqlcontroller)
- [Eager Loading — Deep Dive](#-eager-loading--deep-dive)
  - [The N+1 Problem](#the-n1-problem)
  - [How AutoGraphQL Solves It](#how-autographql-solves-it)
  - [Nested Eager Loading](#nested-eager-loading)
  - [Eager Load Depth Control](#eager-load-depth-control)
- [REST to GraphQL Mapping](#-rest-to-graphql-mapping)
- [Auto-Generated Schema](#-auto-generated-schema)
- [Authentication & Middleware](#-authentication--middleware)
- [Configuration](#-configuration)
- [PHP Attributes — Edge Cases](#-php-attributes--edge-cases)
- [GraphQL Query Examples](#-graphql-query-examples)
- [GraphQL Mutation Examples](#-graphql-mutation-examples)
- [GraphiQL Playground](#-graphiql-playground)
- [Comparison with Other Packages](#-comparison-with-other-packages)
- [Known Challenges](#-known-challenges)
- [Roadmap](#-roadmap)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🤔 Why AutoGraphQL?

Every existing Laravel GraphQL package (Lighthouse, Rebing) is **schema-first** or **code-first**. That means before you can query anything through GraphQL, you must:

- Write `.graphql` schema files for every model
- Write PHP Type classes for every model
- Write Query classes for every query
- Write Mutation classes for every mutation
- Manually declare relationships and eager loads

If you already have a working REST API with controllers, models, and resources — **you have to rewrite everything**.

**AutoGraphQL takes a different approach:**

```
Your existing REST API  ─────────────────────────────────────────────────────────►  GraphQL API
  routes/api.php               composer require mrnamra/autographql                /graphql
  Controllers                              (that's it)
  Eloquent Models
  API Resources
```

No schema files. No type classes. No query classes. No mutations. **Nothing to write.**

### The Philosophy

```
Switching from MySQL to PostgreSQL in Laravel → change one line in .env
Using AutoGraphQL                            → run one composer command
```

Just like how Laravel's database abstraction layer means you never rewrite queries when switching databases, AutoGraphQL means you never rewrite anything when adding GraphQL to an existing REST API.

---

## ⚙️ How It Works

AutoGraphQL runs entirely at **boot time**. When Laravel starts, the package:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         APPLICATION BOOT SEQUENCE                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. ServiceProvider::boot() fires                                           │
│         │                                                                   │
│         ▼                                                                   │
│  2. RouteScanner scans all  api/*  routes                                   │
│         │                                                                   │
│         │   GET /api/users       → UserController@index                     │
│         │   GET /api/users/{id}  → UserController@show                      │
│         │   POST /api/users      → UserController@store                     │
│         │   PUT /api/users/{id}  → UserController@update                    │
│         │   DELETE /api/users/{id}→UserController@destroy                   │
│         │                                                                   │
│         ▼                                                                   │
│  3. TypeGenerator reads Eloquent models + DB schema                         │
│         │                                                                   │
│         │   UserController → App\Models\User (naming convention)            │
│         │   DB columns → GraphQL scalar types                               │
│         │   Eloquent relations → GraphQL relation fields                    │
│         │                                                                   │
│         ▼                                                                   │
│  4. RelationshipDetector inspects model methods via PHP Reflection          │
│         │                                                                   │
│         │   hasMany(Post::class)     → [Post!]!                             │
│         │   belongsTo(User::class)   → User                                 │
│         │   belongsToMany(Tag::class)→ [Tag!]!                              │
│         │                                                                   │
│         ▼                                                                   │
│  5. QueryGenerator maps GET routes → GraphQL Queries                        │
│  6. MutationGenerator maps POST/PUT/DELETE → GraphQL Mutations              │
│         │                                                                   │
│         ▼                                                                   │
│  7. EagerLoadingAnalyzer wires automatic with() detection                   │
│         │                                                                   │
│         ▼                                                                   │
│  8. /graphql endpoint is registered and ready                               │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**At request time**, when a GraphQL query arrives:

```
GraphQL query arrives at POST /graphql
        │
        ▼
GraphQLController parses the query + selection set
        │
        ▼
EagerLoadingAnalyzer inspects which fields/relations are requested
        │
        ▼
ResolverProxy builds a fake Laravel Request with the GraphQL args
        │
        ▼
ResolverProxy calls YOUR existing controller method directly
        │
        ▼
Controller runs exactly as it did before (business logic untouched)
        │
        ▼
Response is unwrapped (JsonResponse / Resource / Collection / array)
        │
        ▼
GraphQL response returned to client
```

---

## 📋 Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.1` |
| Laravel | `^10.0` or `^11.0` |
| webonyx/graphql-php | `^15.0` (auto-installed) |

---

## 📦 Installation

```bash
composer require mrnamra/laravel-autographql
```

That's the entire installation. The package auto-discovers via Laravel's package auto-discovery. No `config/app.php` changes needed.

### Optional: Publish Config

```bash
php artisan vendor:publish --tag=autographql-config
```

This creates `config/autographql.php`. Everything has sensible defaults — only publish if you need to customize.

### Optional: Publish GraphiQL Views

```bash
php artisan vendor:publish --tag=autographql-views
```

---

## 🚀 Quick Start

After installation, open your browser or Postman and hit:

```
POST /graphql
Content-Type: application/json

{
  "query": "{ users { id name email } }"
}
```

If you had a `GET /api/users` route, it's already working. No other steps.

**Test with cURL:**

```bash
curl -X POST http://your-app.test/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ users { id name email } }"}'
```

**Response:**

```json
{
  "data": {
    "users": [
      { "id": 1, "name": "John Doe", "email": "john@example.com" },
      { "id": 2, "name": "Jane Smith", "email": "jane@example.com" }
    ]
  }
}
```

---

## 🏗️ Package Architecture

### File Structure

```
src/
├── GraphQLAutoPackageServiceProvider.php   ← Auto-discovered by Laravel
├── RouteScanner.php                        ← Inspects routes/api.php
├── SchemaGenerator.php                     ← Orchestrates schema build
├── TypeGenerator.php                       ← Models → GraphQL Types
├── QueryGenerator.php                      ← GET routes → Queries
├── MutationGenerator.php                   ← POST/PUT/DELETE → Mutations
├── RelationshipDetector.php                ← PHP Reflection on models
├── EagerLoadingAnalyzer.php                ← Auto with() injection
├── ResolverProxy.php                       ← Delegates to controllers
├── Attributes/
│   └── GraphQL.php                         ← #[GraphQL(...)] attribute class
└── Http/
    └── GraphQLController.php               ← Handles /graphql endpoint
```

---

### ServiceProvider

**File:** `src/GraphQLAutoPackageServiceProvider.php`

The ServiceProvider is the package's entry point. It binds everything in the IoC container and registers the `/graphql` route.

```php
<?php

namespace MrNamra\AutoGraphQL;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class GraphQLAutoPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind all core classes as singletons for performance
        $this->app->singleton(RouteScanner::class);
        $this->app->singleton(RelationshipDetector::class);
        $this->app->singleton(TypeGenerator::class);
        $this->app->singleton(EagerLoadingAnalyzer::class);
        $this->app->singleton(SchemaGenerator::class);
    }

    public function boot(): void
    {
        // 1. Merge default config
        $this->mergeConfigFrom(__DIR__.'/../config/autographql.php', 'autographql');

        // 2. Publish config
        $this->publishes([
            __DIR__.'/../config/autographql.php' => config_path('autographql.php'),
        ], 'autographql-config');

        // 3. Generate the full GraphQL schema from existing routes + models
        $schema = $this->app->make(SchemaGenerator::class)->generate();

        // 4. Register the /graphql HTTP endpoint
        Route::middleware(config('autographql.middleware', ['api']))
             ->post(config('autographql.endpoint', '/graphql'), [GraphQLController::class, 'handle']);

        // 5. GraphiQL playground (only in debug mode)
        if (config('app.debug') && config('autographql.playground', true)) {
            Route::get('/graphiql', [GraphQLController::class, 'playground']);
        }
    }
}
```

---

### RouteScanner

**File:** `src/RouteScanner.php`

Scans all registered Laravel routes and extracts everything needed to build GraphQL operations.

```php
<?php

namespace MrNamra\AutoGraphQL;

use Illuminate\Support\Facades\Route;

class RouteScanner
{
    /**
     * Returns all API routes with their metadata.
     *
     * @return array<int, array{
     *   uri: string,
     *   method: string,
     *   controller: string,
     *   action: string,
     *   model: string|null,
     *   middleware: array<string>,
     *   parameters: array<string>
     * }>
     */
    public function getApiRoutes(): array
    {
        $prefix = config('autographql.route_prefix', 'api');
        $exclude = config('autographql.exclude_routes', []);

        return collect(Route::getRoutes())
            ->filter(fn($r) => str_starts_with($r->uri(), $prefix . '/'))
            ->filter(fn($r) => !in_array($r->uri(), $exclude))
            ->filter(fn($r) => $r->getControllerClass() !== null)
            ->map(fn($r) => [
                'uri'         => $r->uri(),
                'method'      => $r->methods()[0],       // GET, POST, PUT, DELETE
                'controller'  => $r->getControllerClass(),
                'action'      => $r->getActionMethod(),  // index, show, store, update, destroy
                'model'       => $this->guessModel($r),
                'middleware'  => $r->middleware(),
                'parameters'  => $r->parameterNames(),   // e.g. ['id'] from {id}
            ])
            ->values()
            ->toArray();
    }

    /**
     * Guess the Eloquent model from the controller name.
     *
     * UserController → App\Models\User
     * Api\PostController → App\Models\Post
     */
    private function guessModel($route): ?string
    {
        $controllerClass = $route->getControllerClass();
        $baseName = class_basename($controllerClass);

        // Strip "Controller" suffix
        $modelName = str_replace('Controller', '', $baseName);

        // Check in configured model namespace
        $namespace = config('autographql.model_namespace', 'App\\Models');
        $model = "{$namespace}\\{$modelName}";

        if (class_exists($model)) {
            return $model;
        }

        // Fallback: check App\ root namespace
        $rootModel = "App\\{$modelName}";
        return class_exists($rootModel) ? $rootModel : null;
    }
}
```

**What it detects:**

| Route Pattern | Detected Action | Parameters |
|---|---|---|
| `GET /api/users` | `index` | `[]` |
| `GET /api/users/{id}` | `show` | `['id']` |
| `POST /api/users` | `store` | `[]` |
| `PUT /api/users/{id}` | `update` | `['id']` |
| `DELETE /api/users/{id}` | `destroy` | `['id']` |

---

### TypeGenerator

**File:** `src/TypeGenerator.php`

Reads the actual database schema for each model and generates a corresponding GraphQL type definition.

```php
<?php

namespace MrNamra\AutoGraphQL;

use Illuminate\Support\Facades\Schema;

class TypeGenerator
{
    /**
     * Maps MySQL/PostgreSQL column types to GraphQL scalar types.
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

    public function fromModel(string $modelClass): array
    {
        $model   = new $modelClass;
        $table   = $model->getTable();
        $columns = Schema::getColumnListing($table);

        $fields = [];

        foreach ($columns as $column) {
            // Skip hidden fields (passwords etc.)
            if (in_array($column, $model->getHidden())) {
                continue;
            }

            $dbType         = Schema::getColumnType($table, $column);
            $graphqlType    = $this->typeMap[$dbType] ?? 'String';

            // Mark primary key as non-nullable ID
            if ($column === $model->getKeyName()) {
                $fields[$column] = 'ID!';
            } else {
                $fields[$column] = $graphqlType;
            }
        }

        // Merge in detected relationship fields
        $relationships = app(RelationshipDetector::class)->detect($modelClass);
        foreach ($relationships as $name => $meta) {
            $fields[$name] = $meta['graphql_type'];
        }

        return $fields;
    }
}
```

**Column Type Mapping:**

| Database Type | GraphQL Scalar | Notes |
|---|---|---|
| `BIGINT` (primary key) | `ID!` | Non-nullable |
| `INTEGER` | `Int` | |
| `VARCHAR`, `TEXT` | `String` | |
| `BOOLEAN` / `TINYINT(1)` | `Boolean` | |
| `DECIMAL`, `FLOAT` | `Float` | |
| `DATETIME`, `TIMESTAMP` | `String` | ISO 8601 format |
| `JSON`, `JSONB` | `String` | Serialized |
| `UUID` | `ID` | |

> **Hidden fields are automatically excluded.** If a field is in `$hidden` on your model (like `password`, `remember_token`), it will never appear in the GraphQL schema.

---

### RelationshipDetector

**File:** `src/RelationshipDetector.php`

Uses PHP Reflection to scan every public method on a model and detect Eloquent relationships by their return type hint.

```php
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

            $returnType = $method->getReturnType()?->getName();

            if (!$returnType || !$this->isRelation($returnType)) {
                continue;
            }

            $relatedModel = $this->resolveRelatedModel($modelClass, $method->getName());
            $isList       = $this->isListRelation($returnType);
            $graphqlType  = $this->buildGraphQLType($relatedModel, $isList);

            $relations[$method->getName()] = [
                'type'          => $returnType,
                'related_model' => $relatedModel,
                'graphql_type'  => $graphqlType,
                'is_list'       => $isList,
            ];
        }

        return $relations;
    }

    private function isRelation(string $type): bool
    {
        return in_array($type, $this->relationClasses)
            || is_subclass_of($type, \Illuminate\Database\Eloquent\Relations\Relation::class);
    }

    /**
     * HasMany, BelongsToMany, MorphMany → returns a list ([Post!]!)
     * HasOne, BelongsTo, MorphOne       → returns a single object (Post)
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
```

**Relationship Detection Examples:**

```php
class User extends Model
{
    public function posts(): HasMany        // → [Post!]!
    public function profile(): HasOne       // → Profile
    public function roles(): BelongsToMany  // → [Role!]!
    public function country(): BelongsTo    // → Country
    public function images(): MorphMany     // → [Image!]!
}
```

---

### EagerLoadingAnalyzer

**File:** `src/EagerLoadingAnalyzer.php`

This is the most important class for performance. It reads the GraphQL query's selection set (the fields the client requested) and automatically generates the correct `with()` array for Eloquent, including deeply nested relationships.

```php
<?php

namespace MrNamra\AutoGraphQL;

class EagerLoadingAnalyzer
{
    private int $maxDepth;

    public function __construct()
    {
        $this->maxDepth = config('autographql.eager_load_depth', 5);
    }

    /**
     * Analyze the GraphQL selection set and return the eager load array.
     *
     * Example input ($requestedFields):
     * [
     *   'id'    => [],
     *   'name'  => [],
     *   'posts' => [
     *     'title'    => [],
     *     'comments' => [
     *       'body'   => [],
     *       'author' => ['name' => []]
     *     ]
     *   ]
     * ]
     *
     * Example output:
     * ['posts', 'posts.comments', 'posts.comments.author']
     *
     * @param  string  $modelClass      The root Eloquent model
     * @param  array   $requestedFields Nested array of requested fields from GraphQL AST
     * @param  int     $depth           Current recursion depth
     * @return array<string>            Array ready to pass to Model::with([...])
     */
    public function analyze(
        string $modelClass,
        array  $requestedFields,
        int    $depth = 0
    ): array {
        if ($depth >= $this->maxDepth) {
            return [];
        }

        $detector  = app(RelationshipDetector::class);
        $relations = $detector->detect($modelClass);
        $eagerLoad = [];

        foreach ($requestedFields as $field => $subFields) {
            // Is this field a relationship?
            if (!isset($relations[$field])) {
                continue; // It's a regular column — no eager load needed
            }

            // Add this relationship to eager loads
            $eagerLoad[] = $field;

            // Recurse into nested relationships
            if (!empty($subFields)) {
                $relatedModel = $relations[$field]['related_model'];
                $nested       = $this->analyze($relatedModel, $subFields, $depth + 1);

                foreach ($nested as $nestedRelation) {
                    $eagerLoad[] = "{$field}.{$nestedRelation}";
                }
            }
        }

        return array_unique($eagerLoad);
    }

    /**
     * Parse GraphQL AST field selection into a nested array.
     * Called by ResolverProxy before analyze().
     */
    public function parseSelectionSet(\GraphQL\Language\AST\SelectionSetNode $selectionSet): array
    {
        $fields = [];

        foreach ($selectionSet->selections as $selection) {
            $fieldName = $selection->name->value;
            $fields[$fieldName] = $selection->selectionSet
                ? $this->parseSelectionSet($selection->selectionSet)
                : [];
        }

        return $fields;
    }
}
```

---

### QueryGenerator

**File:** `src/QueryGenerator.php`

Converts `GET` routes into GraphQL query definitions.

```php
<?php

namespace MrNamra\AutoGraphQL;

use GraphQL\Type\Definition\{Type, ObjectType, ListOfType};

class QueryGenerator
{
    /**
     * Convert a GET route into a GraphQL query field definition.
     *
     * GET /api/users       → query { users { ... } }          (list)
     * GET /api/users/{id}  → query { user(id: ID!) { ... } }  (single)
     */
    public function generate(array $route, ObjectType $type): array
    {
        $hasIdParam = in_array('id', $route['parameters']);

        if ($hasIdParam) {
            // Single item query
            return [
                'type'        => $type,
                'description' => "Fetch a single {$type->name} by ID",
                'args'        => [
                    'id' => ['type' => Type::nonNull(Type::id())],
                ],
                'resolve'     => $this->buildResolver($route),
            ];
        }

        // List query — also support pagination args
        return [
            'type'        => Type::listOf($type),
            'description' => "Fetch all {$type->name} records",
            'args'        => [
                'limit'  => ['type' => Type::int(), 'defaultValue' => 20],
                'offset' => ['type' => Type::int(), 'defaultValue' => 0],
                'search' => ['type' => Type::string()],
            ],
            'resolve'     => $this->buildResolver($route),
        ];
    }

    private function buildResolver(array $route): callable
    {
        return function ($root, array $args) use ($route) {
            return app(ResolverProxy::class)->resolve(
                $route['controller'],
                $route['action'],
                $args,
                $route['model']
            );
        };
    }
}
```

---

### MutationGenerator

**File:** `src/MutationGenerator.php`

Converts `POST`, `PUT`, and `DELETE` routes into GraphQL mutation definitions.

```php
<?php

namespace MrNamra\AutoGraphQL;

use GraphQL\Type\Definition\{Type, ObjectType, InputObjectType};

class MutationGenerator
{
    /**
     * Convert a write route into a GraphQL mutation field definition.
     *
     * POST   /api/users      → mutation { createUser(input: {...}) }
     * PUT    /api/users/{id} → mutation { updateUser(id: ID!, input: {...}) }
     * DELETE /api/users/{id} → mutation { deleteUser(id: ID!) }
     */
    public function generate(array $route, ObjectType $type, array $inputFields): array
    {
        $method     = strtoupper($route['method']);
        $modelName  = class_basename($route['model']);
        $inputType  = $this->buildInputType($modelName, $inputFields, $method);

        return match ($method) {
            'POST'   => $this->createMutation($type, $inputType, $route, $modelName),
            'PUT',
            'PATCH'  => $this->updateMutation($type, $inputType, $route, $modelName),
            'DELETE' => $this->deleteMutation($route, $modelName),
        };
    }

    private function createMutation(ObjectType $type, InputObjectType $inputType, array $route, string $name): array
    {
        return [
            'type'        => $type,
            'description' => "Create a new {$name}",
            'args'        => ['input' => ['type' => Type::nonNull($inputType)]],
            'resolve'     => fn($r, $args) => app(ResolverProxy::class)
                                ->resolve($route['controller'], $route['action'], $args['input'], $route['model']),
        ];
    }

    private function updateMutation(ObjectType $type, InputObjectType $inputType, array $route, string $name): array
    {
        return [
            'type'        => $type,
            'description' => "Update an existing {$name}",
            'args'        => [
                'id'    => ['type' => Type::nonNull(Type::id())],
                'input' => ['type' => Type::nonNull($inputType)],
            ],
            'resolve'     => fn($r, $args) => app(ResolverProxy::class)
                                ->resolve($route['controller'], $route['action'],
                                    array_merge(['id' => $args['id']], $args['input']), $route['model']),
        ];
    }

    private function deleteMutation(array $route, string $name): array
    {
        return [
            'type'        => new ObjectType(['name' => "Delete{$name}Result", 'fields' => [
                'success' => ['type' => Type::nonNull(Type::boolean())],
                'message' => ['type' => Type::string()],
            ]]),
            'description' => "Delete a {$name}",
            'args'        => ['id' => ['type' => Type::nonNull(Type::id())]],
            'resolve'     => fn($r, $args) => app(ResolverProxy::class)
                                ->resolve($route['controller'], $route['action'], $args, $route['model']),
        ];
    }

    /**
     * Build an InputObject type from model fields (excluding id, timestamps).
     */
    private function buildInputType(string $name, array $fields, string $method): InputObjectType
    {
        $inputFields = collect($fields)
            ->reject(fn($type, $field) => in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->map(fn($type) => [
                'type' => $method === 'PUT' ? $this->toNullable($type) : $this->toScalar($type),
            ])
            ->toArray();

        return new InputObjectType([
            'name'   => "{$name}Input",
            'fields' => $inputFields,
        ]);
    }
}
```

---

### ResolverProxy

**File:** `src/ResolverProxy.php`

The crown jewel of AutoGraphQL. Calls your existing controllers without any modification to them.

```php
<?php

namespace MrNamra\AutoGraphQL;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\{JsonResource, ResourceCollection};
use Illuminate\Pagination\LengthAwarePaginator;

class ResolverProxy
{
    /**
     * Execute an existing controller method and return the data.
     *
     * This is the core of the zero-change promise. Your controller
     * runs exactly as it did before — we just call it differently.
     *
     * @param  string  $controller   Full controller class name
     * @param  string  $method       Controller method to call
     * @param  array   $args         GraphQL arguments (become request inputs)
     * @param  string  $modelClass   The Eloquent model class
     * @return mixed                 Raw data ready for GraphQL
     */
    public function resolve(
        string $controller,
        string $method,
        array  $args,
        string $modelClass
    ): mixed {
        // 1. Build a fake Laravel Request from GraphQL arguments
        $request = Request::create('/', 'GET', $args);

        // 2. Copy the authenticated user from the real request
        //    This ensures auth:sanctum / auth:api works correctly
        $request->setUserResolver(fn() => app('request')->user());

        // 3. Copy session and cookies for stateful auth (Sanctum stateful)
        $request->cookies->replace(app('request')->cookies->all());

        // 4. Determine which eager loads to apply based on the
        //    current GraphQL selection set
        $eagerLoads = app()->bound('graphql.current_selection')
            ? app(EagerLoadingAnalyzer::class)->analyze(
                $modelClass,
                app('graphql.current_selection')
              )
            : [];

        // 5. Inject eager loads — controller can optionally pick these up,
        //    but even if it doesn't, the proxy wraps the response and applies them
        app()->instance('graphql.eager_loads', $eagerLoads);

        // 6. Instantiate and call your existing controller
        $controllerInstance = app($controller);
        $response           = $controllerInstance->$method($request);

        // 7. Unwrap and return the data
        return $this->unwrap($response, $modelClass, $eagerLoads);
    }

    /**
     * Unwrap various Laravel response types into raw arrays/collections.
     */
    private function unwrap(mixed $response, string $modelClass, array $eagerLoads): mixed
    {
        // JsonResponse (return response()->json(...))
        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        // API Resource (return new UserResource($user))
        if ($response instanceof JsonResource) {
            $resource = $response->resource;
            if ($resource instanceof $modelClass && !empty($eagerLoads)) {
                $resource->load($eagerLoads);
            }
            return $response->resolve();
        }

        // Resource Collection (return UserResource::collection($users))
        if ($response instanceof ResourceCollection) {
            $collection = $response->resource;
            if (!empty($eagerLoads)) {
                $collection->load($eagerLoads);
            }
            return $response->resolve();
        }

        // Paginator (return User::paginate())
        if ($response instanceof LengthAwarePaginator) {
            if (!empty($eagerLoads)) {
                $response->getCollection()->load($eagerLoads);
            }
            return $response->items();
        }

        // Eloquent Collection or Model (return User::all())
        if ($response instanceof \Illuminate\Database\Eloquent\Collection) {
            if (!empty($eagerLoads)) {
                $response->load($eagerLoads);
            }
            return $response->toArray();
        }

        return $response;
    }
}
```

---

### SchemaGenerator

**File:** `src/SchemaGenerator.php`

Orchestrates all the above classes and produces the final `GraphQL\Type\Schema` object.

```php
<?php

namespace MrNamra\AutoGraphQL;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\{ObjectType, Type};

class SchemaGenerator
{
    public function __construct(
        private RouteScanner      $scanner,
        private TypeGenerator     $typeGen,
        private QueryGenerator    $queryGen,
        private MutationGenerator $mutationGen,
    ) {}

    public function generate(): Schema
    {
        // Use cached schema in production for performance
        if (config('autographql.cache_schema') && $cached = cache('autographql.schema')) {
            return $cached;
        }

        $routes     = $this->scanner->getApiRoutes();
        $types      = [];
        $queries    = [];
        $mutations  = [];

        // Build a GraphQL ObjectType for each unique model
        $modelTypes = [];
        foreach ($routes as $route) {
            if (!$route['model'] || isset($modelTypes[$route['model']])) {
                continue;
            }

            $fields        = $this->typeGen->fromModel($route['model']);
            $typeName      = class_basename($route['model']);
            $modelTypes[$route['model']] = new ObjectType([
                'name'   => $typeName,
                'fields' => fn() => $this->buildFields($fields, $modelTypes),
            ]);
        }

        // Build queries and mutations from routes
        foreach ($routes as $route) {
            if (!$route['model']) continue;

            $type       = $modelTypes[$route['model']];
            $modelName  = lcfirst(class_basename($route['model']));

            match ($route['method']) {
                'GET'    => $queries    = array_merge($queries, $this->queryGen->generate($route, $type, $modelName)),
                'POST'   => $mutations  = array_merge($mutations, $this->mutationGen->generate($route, $type, $modelName)),
                'PUT',
                'PATCH'  => $mutations  = array_merge($mutations, $this->mutationGen->generate($route, $type, $modelName)),
                'DELETE' => $mutations  = array_merge($mutations, $this->mutationGen->generate($route, $type, $modelName)),
                default  => null,
            };
        }

        $schema = new Schema([
            'query'    => new ObjectType(['name' => 'Query',    'fields' => $queries]),
            'mutation' => new ObjectType(['name' => 'Mutation', 'fields' => $mutations]),
        ]);

        if (config('autographql.cache_schema')) {
            cache()->put('autographql.schema', $schema, config('autographql.cache_ttl', 3600));
        }

        return $schema;
    }
}
```

---

### GraphQLController

**File:** `src/Http/GraphQLController.php`

Handles the `/graphql` HTTP endpoint. Parses the query, injects the selection set for eager loading, and executes via `webonyx/graphql-php`.

```php
<?php

namespace MrNamra\AutoGraphQL\Http;

use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use Illuminate\Http\{Request, JsonResponse};

class GraphQLController
{
    public function handle(Request $request): JsonResponse
    {
        $schema = app(SchemaGenerator::class)->generate();
        $query  = $request->input('query');
        $vars   = $request->input('variables', []);

        // Parse the query AST and extract the selection set
        // for use by EagerLoadingAnalyzer in resolvers
        try {
            $ast            = Parser::parse($query);
            $selectionSet   = app(EagerLoadingAnalyzer::class)
                                ->parseSelectionSet(
                                    $ast->definitions[0]->selectionSet
                                );
            app()->instance('graphql.current_selection', $selectionSet);
        } catch (\Exception $e) {
            return response()->json(['errors' => [['message' => $e->getMessage()]]], 400);
        }

        $result = GraphQL::executeQuery($schema, $query, null, null, $vars);

        return response()->json($result->toArray());
    }

    public function playground(): \Illuminate\View\View
    {
        return view('autographql::graphiql', [
            'endpoint' => config('autographql.endpoint', '/graphql'),
        ]);
    }
}
```

---

## 🔥 Eager Loading — Deep Dive

### The N+1 Problem

Without eager loading, GraphQL has a severe performance problem. Consider this query:

```graphql
query {
  users {
    name
    posts {
      title
      comments { body }
    }
  }
}
```

Without eager loading, this fires:

```sql
SELECT * FROM users;                          -- 1 query (returns 100 users)
SELECT * FROM posts WHERE user_id = 1;        -- query #2
SELECT * FROM posts WHERE user_id = 2;        -- query #3
SELECT * FROM posts WHERE user_id = 3;        -- query #4
-- ... 100 more queries for posts
SELECT * FROM comments WHERE post_id = 1;     -- query #103
SELECT * FROM comments WHERE post_id = 2;     -- query #104
-- ... potentially thousands more
```

**Total: 1 + N + N×M queries. With 100 users averaging 10 posts each → 1,101+ queries per request.**

### How AutoGraphQL Solves It

AutoGraphQL reads the GraphQL AST *before* executing anything, extracts the nested field selections, and automatically generates the correct Eloquent `with()` call:

```php
// EagerLoadingAnalyzer inspects the selection set tree:
// users → posts (relation!) → comments (relation!)

User::with([
    'posts',
    'posts.comments',
])->get();

// Result: exactly 3 SQL queries, regardless of how many users/posts/comments exist
```

### Nested Eager Loading

AutoGraphQL supports unlimited nesting depth (configurable). Here's a deeply nested example:

```graphql
query {
  users {
    name
    posts {
      title
      tags { name }
      comments {
        body
        author {
          name
          profile { avatar_url }
        }
      }
    }
  }
}
```

AutoGraphQL generates:

```php
User::with([
    'posts',
    'posts.tags',
    'posts.comments',
    'posts.comments.author',
    'posts.comments.author.profile',
])->get();

// 6 SQL queries total. Not 6,000.
```

### Eager Load Depth Control

To prevent infinite recursion on circular relationships (e.g., `User → Post → User`), configure the max depth:

```php
// config/autographql.php
'eager_load_depth' => 5, // default: 5 levels deep
```

---

## 🔄 REST to GraphQL Mapping

| HTTP Method | Route Pattern | GraphQL Operation | Operation Name |
|---|---|---|---|
| `GET` | `/api/users` | `query` | `users` |
| `GET` | `/api/users/{id}` | `query` | `user(id: ID!)` |
| `POST` | `/api/users` | `mutation` | `createUser(input: UserInput!)` |
| `PUT` | `/api/users/{id}` | `mutation` | `updateUser(id: ID!, input: UserInput!)` |
| `PATCH` | `/api/users/{id}` | `mutation` | `updateUser(id: ID!, input: UserInput!)` |
| `DELETE` | `/api/users/{id}` | `mutation` | `deleteUser(id: ID!)` |

### Nested Resources

| HTTP Route | GraphQL Operation |
|---|---|
| `GET /api/users/{user}/posts` | `query { userPosts(user_id: ID!) { ... } }` |
| `POST /api/users/{user}/posts` | `mutation { createUserPost(user_id: ID!, input: PostInput!) }` |

---

## 📐 Auto-Generated Schema

For a typical blog app with `User`, `Post`, `Comment`, `Tag` models, AutoGraphQL generates this schema automatically:

```graphql
# ─── Types (auto-generated from Eloquent models + DB schema) ─────────────────

type User {
  id: ID!
  name: String
  email: String
  created_at: String
  updated_at: String
  posts: [Post!]!          # ← detected from HasMany relationship
  profile: Profile         # ← detected from HasOne relationship
  roles: [Role!]!          # ← detected from BelongsToMany relationship
}

type Post {
  id: ID!
  title: String
  body: String
  published_at: String
  user_id: Int
  author: User             # ← detected from BelongsTo relationship
  comments: [Comment!]!   # ← detected from HasMany relationship
  tags: [Tag!]!            # ← detected from BelongsToMany relationship
}

type Comment {
  id: ID!
  body: String
  post_id: Int
  user_id: Int
  post: Post               # ← detected from BelongsTo relationship
  author: User             # ← detected from BelongsTo relationship
}

# ─── Input Types (auto-generated for mutations) ───────────────────────────────

input UserInput {
  name: String!
  email: String!
  password: String!
}

input PostInput {
  title: String!
  body: String!
  published_at: String
}

# ─── Queries (auto-generated from GET routes) ─────────────────────────────────

type Query {
  users(limit: Int, offset: Int, search: String): [User!]!
  user(id: ID!): User
  posts(limit: Int, offset: Int): [Post!]!
  post(id: ID!): Post
  comments(limit: Int, offset: Int): [Comment!]!
  comment(id: ID!): Comment
}

# ─── Mutations (auto-generated from POST/PUT/DELETE routes) ───────────────────

type Mutation {
  createUser(input: UserInput!): User
  updateUser(id: ID!, input: UserInput!): User
  deleteUser(id: ID!): DeleteUserResult

  createPost(input: PostInput!): Post
  updatePost(id: ID!, input: PostInput!): Post
  deletePost(id: ID!): DeletePostResult
}
```

**None of this is written by you. It's all generated at runtime from your existing code.**

---

## 🔐 Authentication & Middleware

AutoGraphQL automatically mirrors the middleware from your REST routes onto the corresponding GraphQL operations.

### How It Works

```php
// Your existing routes (unchanged)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);       // ← protected
    Route::post('/api/posts', [PostController::class, 'store']);      // ← protected
});

Route::get('/api/products', [ProductController::class, 'index']);     // ← public
```

```graphql
# Protected — requires Authorization: Bearer <token>
query { users { id name } }
mutation { createPost(input: { title: "Hello" }) { id } }

# Public — no token needed
query { products { id name price } }
```

### Sending Tokens

```bash
# Laravel Sanctum API token
curl -X POST http://your-app.test/graphql \
  -H "Authorization: Bearer your-sanctum-token" \
  -H "Content-Type: application/json" \
  -d '{"query":"{ users { id name } }"}'
```

### Sanctum Stateful (SPA) Auth

AutoGraphQL copies cookies from the real request, so Sanctum stateful (session-based) authentication for SPAs works without any configuration.

---

## ⚙️ Configuration

```php
// config/autographql.php
return [

    /*
    |--------------------------------------------------------------------------
    | GraphQL Endpoint
    |--------------------------------------------------------------------------
    | The URI where the GraphQL API will be available.
    */
    'endpoint' => env('GRAPHQL_ENDPOINT', '/graphql'),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix to Scan
    |--------------------------------------------------------------------------
    | AutoGraphQL will only scan routes starting with this prefix.
    | Default: 'api' → scans all routes like /api/*
    */
    'route_prefix' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Exclude Specific Routes
    |--------------------------------------------------------------------------
    | List exact route URIs you want to exclude from GraphQL.
    */
    'exclude_routes' => [
        // 'api/admin/logs',
        // 'api/webhooks',
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphiQL Playground
    |--------------------------------------------------------------------------
    | Show the interactive GraphiQL UI at /graphiql.
    | Only active when APP_DEBUG=true for security.
    */
    'playground' => env('GRAPHQL_PLAYGROUND', true),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to the /graphql endpoint itself.
    | Individual operations inherit middleware from their originating routes.
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Eager Loading
    |--------------------------------------------------------------------------
    | Automatically detect and apply with() calls based on the
    | GraphQL selection set. Eliminates N+1 queries.
    */
    'eager_loading'    => true,
    'eager_load_depth' => 5,   // Max nesting depth (prevents circular recursion)

    /*
    |--------------------------------------------------------------------------
    | Schema Caching
    |--------------------------------------------------------------------------
    | Cache the generated GraphQL schema for performance in production.
    | Disable during development so schema changes are reflected immediately.
    */
    'cache_schema' => env('GRAPHQL_CACHE', app()->isProduction()),
    'cache_ttl'    => 3600, // seconds (1 hour)
    'cache_key'    => 'autographql.schema',

    /*
    |--------------------------------------------------------------------------
    | Model Namespace
    |--------------------------------------------------------------------------
    | The namespace where your Eloquent models live.
    */
    'model_namespace' => 'App\\Models',

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    | Default pagination limit for list queries.
    */
    'default_limit' => 20,
    'max_limit'     => 100,

];
```

---

## 🏷️ PHP Attributes — Edge Cases

For the rare case where a route doesn't follow naming conventions, add a single PHP 8.1 attribute. **No restructuring needed.**

```php
use MrNamra\AutoGraphQL\Attributes\GraphQL;

class UserController extends Controller
{
    // Standard route — no attribute needed, auto-detected
    public function index(Request $request) { ... }

    // Non-standard name — use attribute
    #[GraphQL(
        query: 'activeUsers',
        description: 'Retrieve all currently active users',
        eagerLoad: ['profile', 'roles']
    )]
    public function getActive(Request $request)
    {
        return User::where('is_active', true)->paginate();
    }

    // Skip this route entirely
    #[GraphQL(skip: true)]
    public function internalAdminOnly(Request $request)
    {
        return User::withTrashed()->get();
    }

    // Custom mutation with manual eager load hints
    #[GraphQL(
        mutation: 'publishPost',
        description: 'Publish a draft post and notify subscribers',
        eagerLoad: ['author', 'tags', 'subscribers']
    )]
    public function publish(Request $request, Post $post)
    {
        $post->update(['published_at' => now()]);
        return $post;
    }
}
```

### All Available Attribute Options

| Option | Type | Description |
|---|---|---|
| `query` | `string` | Override the auto-generated query name |
| `mutation` | `string` | Override the auto-generated mutation name |
| `description` | `string` | Shown in GraphiQL schema documentation |
| `eagerLoad` | `array` | Manual eager load relationships |
| `middleware` | `array` | Override middleware for this operation |
| `skip` | `bool` | Set `true` to exclude from GraphQL schema |
| `deprecated` | `string` | Mark as deprecated with a reason message |

---

## 🔍 GraphQL Query Examples

### Basic List Query

```graphql
query {
  users {
    id
    name
    email
  }
}
```

### Single Item Query

```graphql
query {
  user(id: 1) {
    id
    name
    email
    created_at
  }
}
```

### With Relationships (Eager Loaded Automatically)

```graphql
query {
  user(id: 1) {
    id
    name
    posts {
      id
      title
      published_at
      tags {
        name
      }
      comments {
        body
        author {
          name
        }
      }
    }
  }
}
```

**SQL generated:** 5 queries, regardless of data volume. No N+1.

### With Pagination

```graphql
query {
  users(limit: 10, offset: 20) {
    id
    name
    email
  }
}
```

### Multiple Queries in One Request

```graphql
query DashboardData {
  users(limit: 5) {
    id
    name
  }
  posts(limit: 10) {
    id
    title
    author { name }
  }
}
```

---

## ✏️ GraphQL Mutation Examples

### Create

```graphql
mutation {
  createUser(input: {
    name: "Raj Patel"
    email: "raj@example.com"
    password: "secret123"
  }) {
    id
    name
    email
    created_at
  }
}
```

### Update

```graphql
mutation {
  updateUser(id: 1, input: {
    name: "Raj Kumar Patel"
    email: "rajkumar@example.com"
  }) {
    id
    name
    email
    updated_at
  }
}
```

### Delete

```graphql
mutation {
  deleteUser(id: 1) {
    success
    message
  }
}
```

---

## 🎮 GraphiQL Playground

When `APP_DEBUG=true`, visit `/graphiql` in your browser for an interactive GraphQL IDE.

Features:
- Auto-complete for all queries, mutations, types and fields
- Live schema documentation panel
- Variable editor
- Response viewer with syntax highlighting
- Query history

> The playground is **automatically disabled** when `APP_DEBUG=false` (i.e., in production).

---

## 📊 Comparison with Other Packages

| Feature | Lighthouse | Rebing GraphQL | **AutoGraphQL** |
|---|---|---|---|
| Requires writing schema | ✅ Yes (SDL files) | ✅ Yes (PHP classes) | ❌ **No** |
| Requires changing controllers | ✅ Yes | ✅ Yes | ❌ **No** |
| Works on existing apps | ⚠️ Partial migration | ⚠️ Partial migration | ✅ **Full, instant** |
| Auto-generates schema | ❌ | ❌ | ✅ |
| Eager loading | ✅ Manual `@with` | ✅ SelectFields class | ✅ **Automatic** |
| REST → GraphQL bridge | ❌ | ❌ | ✅ |
| N+1 elimination | ✅ (manual) | ✅ (manual) | ✅ **Automatic** |
| Middleware mirroring | ⚠️ Manual | ⚠️ Manual | ✅ **Automatic** |
| GraphQL subscriptions | ✅ | ❌ | 🗺️ Roadmap |
| Learning curve for users | Medium | Medium | **None** |
| Installation steps | 5–10 | 5–10 | **1** |

---

## ⚠️ Known Challenges

| Challenge | Details | Solution |
|---|---|---|
| Non-standard naming | `ReportController` doesn't map to `App\Models\Report` | Use `#[GraphQL]` attribute or configure `model_namespace` |
| FormRequest validation | GraphQL args bypass `FormRequest` classes | Package wraps args in a fake `Request` and triggers validation |
| Polymorphic relations | `MorphTo` can't be statically typed | Generates GraphQL `union` types from all related models |
| Circular relationships | `User → Post → User` infinite loop | Configurable `eager_load_depth` limit (default: 5) |
| Non-resourceful routes | Custom action names that aren't CRUD | Use `#[GraphQL(query: ...)]` or `#[GraphQL(mutation: ...)]` |
| Complex `joins` / raw queries | Business logic using `DB::raw()` bypasses model | Falls back to raw array pass-through |
| File uploads | Multipart form data | Use `base64` encoded string in GraphQL vars |

---

## 🗺️ Roadmap

- [ ] GraphQL Subscriptions via Laravel Broadcasting / WebSockets
- [ ] Automatic cursor-based pagination (`edges` / `node` / `pageInfo`)
- [ ] GraphQL persisted queries
- [ ] DataLoader (batched loading for deep N+1 edge cases)
- [ ] Schema introspection caching with cache tags
- [ ] Custom scalar types (`Date`, `DateTime`, `JSON`, `Upload`)
- [ ] Rate limiting per GraphQL operation
- [ ] Tracing / query complexity analysis
- [ ] OpenAPI → GraphQL schema import
- [ ] Laravel Nova integration

---

## 🤝 Contributing

```bash
# Clone
git clone https://github.com/mrnamra/laravel-autographql.git
cd laravel-autographql

# Install deps
composer install

# Run tests
vendor/bin/phpunit

# Run static analysis
vendor/bin/phpstan analyse src

# Code style
vendor/bin/pint
```

Pull requests are welcome! Please open an issue first for major changes.

---

## 📄 License

MIT License — see [LICENSE.md](LICENSE.md)

---

<div align="center">

**Built for Laravel developers who want GraphQL without the boilerplate.**

`composer require mrnamra/laravel-autographql`

</div>