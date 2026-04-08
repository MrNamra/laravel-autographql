<?php

namespace MrNamra\AutoGraphQL;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\{JsonResource, ResourceCollection};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use GraphQL\Error\UserError;
use MrNamra\AutoGraphQL\Attributes\Searchable;
use Illuminate\Support\Facades\Auth;
use MrNamra\AutoGraphQL\Exceptions\GraphQLValidationException;
use MrNamra\AutoGraphQL\Exceptions\GraphQLAuthException;

class ResolverProxy
{
    /**
     * Call an existing controller method with GraphQL arguments.
     */
    public function resolve(
        string $controller,
        string $method,
        array  $args,
        string $modelClass,
        array  $selection = [],
        string $httpMethod = 'GET'
    ): mixed {
        // 1. Prepare a fake Laravel Request
        // Note: For POST/PUT, we use 'POST' and merge data into JSON body Simulation
        $requestData = $args;
        if (isset($args['input']) && is_array($args['input'])) {
            $requestData = array_merge($args, $args['input']);
            unset($requestData['input']);
        }

        $request = Request::create('/', $httpMethod, $requestData);
        
        // Populate inputs for validation etc.
        $request->merge($requestData);

        // 2. Mirror auth context from the current real request
        if (app()->bound('request')) {
            $realRequest = app('request');
            $user = $realRequest->user();
            
            $request->setUserResolver(fn() => $user);
            $request->cookies->replace($realRequest->cookies->all());
            
            // Sync Laravel Auth globally for this context
            if ($user) {
                Auth::setUser($user);
            }
        }

        // 3. Handle automatic eager loading
        $eagerLoads = [];
        if (config('autographql.eager_loading', true) && !empty($selection) && class_exists($modelClass)) {
            $eagerLoads = app(EagerLoadingAnalyzer::class)->analyze($modelClass, $selection);
        }

        // Apply attribute-based manual eager loads
        $attribute = app(RouteScanner::class)->getApiRoutes()[0]['attribute'] ?? null; // Simplification
        if ($attribute && !empty($attribute->eagerLoad)) {
            $eagerLoads = array_unique(array_merge($eagerLoads, $attribute->eagerLoad));
        }

        // 4. Call the controller with intelligent parameter mapping
        $controllerInstance = app($controller);
        $reflection = new \ReflectionMethod($controller, $method);
        $params = $reflection->getParameters();
        $resolvedArgs = [];

        foreach ($params as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // 1. If it's a Request object, inject our fake request
            if ($type && !$type->isBuiltin() && is_subclass_of($type->getName(), Request::class)) {
                $resolvedArgs[] = $request;
                continue;
            }
            if ($type && !$type->isBuiltin() && $type->getName() === Request::class) {
                $resolvedArgs[] = $request;
                continue;
            }

            // 2. Map from GraphQL arguments by name (e.g. id, slug)
            if (isset($args[$name])) {
                $resolvedArgs[] = $args[$name];
                continue;
            }

            // 3. Fallback to route parameters if they exist in the request
            if ($request->route && $request->route($name)) {
                $resolvedArgs[] = $request->route($name);
                continue;
            }

            // 4. Handle default values
            if ($param->isDefaultValueAvailable()) {
                $resolvedArgs[] = $param->getDefaultValue();
                continue;
            }

            // 5. Try to resolve from container (for DI)
            if ($type && !$type->isBuiltin()) {
                $resolvedArgs[] = app($type->getName());
                continue;
            }

            $resolvedArgs[] = null;
        }

        try {
            // 5. Authorization Mirroring: Enforce REST route middleware
            $routeInfo = $this->findRouteInfo($controller, $method);
            if ($routeInfo && !empty($routeInfo['middleware'])) {
                $this->verifyMiddlewareRequirements($routeInfo['middleware'], $request);
            }

            // 6. Intelligent Fallback: If 'id' is missing but 'first' or 'last' is provided,
            // try to find the "List" route for this model and call it instead.
            if (empty($args['id']) && (!empty($args['first']) || !empty($args['last']))) {
                $listRoute = $this->findListRoute($modelClass);
                if ($listRoute) {
                    $controllerInstance = app($listRoute['controller']);
                    $method             = $listRoute['action'];
                    $response           = $controllerInstance->$method($request);
                } else {
                    $response = $controllerInstance->$method(...$resolvedArgs);
                }
            } else {
                $response = $controllerInstance->$method(...$resolvedArgs);
            }
        } catch (ModelNotFoundException $e) {
            return null;
        } catch (ValidationException $e) {
            throw new GraphQLValidationException($e->errors());
        } catch (AuthorizationException $e) {
            throw new GraphQLAuthException($e->getMessage());
        }

        // 5. Unwrap and post-process the response
        return $this->unwrap($response, $modelClass, $eagerLoads, $args);
    }

    /**
     * Standardize Laravel response types for GraphQL consumption and apply automatic filtering/pagination.
     */
    private function unwrap(mixed $response, string $modelClass, array $eagerLoads, array $args = []): mixed
    {
        // JsonResponse (return response()->json(...))
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            return $data['data'] ?? $data;
        }

        // API Resource/Collection
        if ($response instanceof JsonResource) {
            $resource = $response->resource;
            // Apply eager loading to the model/collection inside the resource
            if ($resource instanceof \Illuminate\Database\Eloquent\Model || $resource instanceof \Illuminate\Support\Collection) {
                if (!empty($eagerLoads)) {
                    $resource->load($eagerLoads);
                }
            }
            return $response->resolve();
        }

        // Paginator
        if ($response instanceof LengthAwarePaginator) {
            if (!empty($eagerLoads)) {
                $response->getCollection()->load($eagerLoads);
            }
            return $response->items();
        }

        // Eloquent Builder (Not yet executed)
        if ($response instanceof \Illuminate\Database\Eloquent\Builder || $response instanceof \Illuminate\Database\Query\Builder) {
            $response = $this->applyBuilderFilters($response, $args);
            
            // If it's a pagination request
            if (isset($args['page']) || isset($args['per_page'])) {
                $perPage = $args['per_page'] ?? $args['limit'] ?? 20;
                $page = $args['page'] ?? 1;
                $paginator = $response->paginate($perPage, ['*'], 'page', $page);
                
                if (!empty($eagerLoads)) {
                    $paginator->getCollection()->load($eagerLoads);
                }
                return $paginator->items();
            }

            $result = $response->get();
            if (!empty($eagerLoads)) {
                $result->load($eagerLoads);
            }
            return $result->toArray();
        }

        // Eloquent Collection or Model
        if ($response instanceof \Illuminate\Database\Eloquent\Model || $response instanceof \Illuminate\Database\Eloquent\Collection) {
            if (!empty($eagerLoads)) {
                $response->load($eagerLoads);
            }

            // Apply automatic filtering/pagination/first/last
            if ($response instanceof \Illuminate\Database\Eloquent\Collection) {
                $result = $this->applyCollectionFilters($response, $args);
            } else {
                $result = $response;
            }

            if ($result instanceof \Illuminate\Database\Eloquent\Model) {
                return $result->toArray();
            }

            return ($result && method_exists($result, 'toArray')) ? $result->toArray() : null;
        }

        return $response;
    }

    /**
     * Find a GET route that returns a list (no 'id' param) for the given model.
     */
    private function findListRoute(string $modelClass): ?array
    {
        $routes = app(RouteScanner::class)->getApiRoutes();
        foreach ($routes as $route) {
            if ($route['model'] === $modelClass && 
                strtoupper($route['method']) === 'GET' && 
                !in_array('id', $route['parameters'])) {
                return $route;
            }
        }
        return null;
    }

    /**
     * Apply high-performance database-level filters to a Builder.
     */
    private function applyBuilderFilters($query, array $args)
    {
        $modelClass = get_class($query->getModel());

        // 1. Search
        if (!empty($args['search'])) {
            $keyword = '%' . $args['search'] . '%';
            
            if (!empty($args['search_column'])) {
                // Support dot-notation for cross-table search
                if (str_contains($args['search_column'], '.')) {
                    [$relation, $column] = explode('.', $args['search_column'], 2);
                    $query->whereHas($relation, function($q) use ($column, $keyword) {
                        $q->where($column, 'LIKE', $keyword);
                    });
                } else {
                    $query->where($args['search_column'], 'LIKE', $keyword);
                }
            } else {
                // Multi-column search based on #[Searchable] attribute
                $searchableColumns = $this->getSearchableColumns($modelClass);
                
                if (!empty($searchableColumns)) {
                    $query->where(function($q) use ($searchableColumns, $keyword) {
                        foreach ($searchableColumns as $column) {
                            $q->orWhere($column, 'LIKE', $keyword);
                        }
                    });
                } else {
                    // Fallback if no attributes are defined
                    $query->where('id', 'LIKE', $keyword);
                }
            }
        }

        // 2. Offset/Limit (if not using page-based pagination)
        if (!isset($args['page'])) {
            if (isset($args['offset'])) {
                $query->offset($args['offset']);
            }
            if (isset($args['limit'])) {
                $query->limit($args['limit']);
            }
        }

        return $query;
    }

    /**
     * Apply automatic limit, offset, search, first, and last to a collection (In-Memory Fallback).
     */
    private function applyCollectionFilters(\Illuminate\Database\Eloquent\Collection $collection, array $args): mixed
    {
        // 1. Search
        if (!empty($args['search'])) {
            $keyword = strtolower($args['search']);
            $column  = $args['search_column'] ?? null;

            $collection = $collection->filter(function ($model) use ($keyword, $column) {
                if ($column) {
                    // Support dot-notation for cross-table search
                    if (str_contains($column, '.')) {
                        [$relation, $col] = explode('.', $column, 2);
                        $related = $model->getAttribute($relation);
                        if ($related instanceof \Illuminate\Support\Collection) {
                            return $related->filter(fn($r) => is_string($r->{$col}) && str_contains(strtolower($r->{$col}), $keyword))->isNotEmpty();
                        }
                        if ($related instanceof \Illuminate\Database\Eloquent\Model) {
                            return is_string($related->{$col}) && str_contains(strtolower($related->{$col}), $keyword);
                        }
                        return false;
                    }

                    $val = $model->getAttribute($column);
                    return is_string($val) && str_contains(strtolower($val), $keyword);
                }

                $attributes = $model->getAttributes();
                foreach ($attributes as $key => $value) {
                    if (is_string($value) && str_contains(strtolower($value), $keyword)) {
                        return true;
                    }
                }
                return false;
            });
        }

        // 2. Offset/Page
        $offset = $args['offset'] ?? 0;
        if (isset($args['page']) && $args['page'] > 1) {
            $perPage = $args['per_page'] ?? $args['limit'] ?? 20;
            $offset = ($args['page'] - 1) * $perPage;
        }

        if ($offset > 0) {
            $collection = $collection->slice($offset);
        }

        // 3. Limit
        $limit = $args['per_page'] ?? $args['limit'] ?? 0;
        if ($limit > 0) {
            $collection = $collection->slice(0, $limit);
        }

        // 4. First / Last (Singular query support)
        if (!empty($args['first'])) {
            return $collection->first();
        }
        if (!empty($args['last'])) {
            return $collection->last();
        }

        return $collection->values();
    }

    /**
     * Get columns marked with the #[Searchable] attribute in a model.
     */
    private function getSearchableColumns(string $modelClass): array
    {
        $columns = [];
        $reflection = new ReflectionClass($modelClass);

        // Check properties
        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(Searchable::class)) {
                $columns[] = $property->getName();
            }
        }

        // Check methods (for accessors)
        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes(Searchable::class)) {
                $name = $method->getName();
                // If it's a getXxxxAttribute style or a simple method name
                if (str_starts_with($name, 'get') && str_ends_with($name, 'Attribute')) {
                    $column = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr($name, 3, -9)));
                    $columns[] = $column;
                } else {
                    $columns[] = $name;
                }
            }
        }

        return array_unique($columns);
    }

    /**
     * Find the route metadata for a given controller and action.
     */
    private function findRouteInfo(string $controller, string $action): ?array
    {
        $routes = app(RouteScanner::class)->getApiRoutes();
        foreach ($routes as $route) {
            if ($route['controller'] === $controller && $route['action'] === $action) {
                return $route;
            }
        }
        return null;
    }

    /**
     * Verify that the current request satisfies the target route's middleware.
     */
    private function verifyMiddlewareRequirements(array $middlewares, Request $request): void
    {
        $user = $request->user();

        foreach ($middlewares as $mw) {
            // 1. Generic Auth Enforcement (Sanctum, Passport, Session, etc.)
            // Detects 'auth', 'auth:api', 'auth:sanctum', 'auth:passport', etc.
            if (str_starts_with($mw, 'auth') || $mw === 'verified') {
                if (!$user) {
                    throw new GraphQLAuthException("Access to this field requires the '{$mw}' middleware.");
                }
            }

            // 2. Handle 'guest' middleware
            if ($mw === 'guest' && $user) {
                throw new GraphQLAuthException("This field is only accessible to guests.");
            }
        }
    }
}
