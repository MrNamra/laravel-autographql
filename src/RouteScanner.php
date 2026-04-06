<?php

namespace MrNamra\AutoGraphQL;

use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use MrNamra\AutoGraphQL\Attributes\GraphQL;

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
     *   parameters: array<string>,
     *   attribute: GraphQL|null
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
            ->map(function ($r) {
                $controller = $r->getControllerClass();
                $action = $r->getActionMethod();
                $attribute = $this->getActionAttribute($controller, $action);

                if ($attribute && $attribute->skip) {
                    return null;
                }

                return [
                    'uri'         => $r->uri(),
                    'method'      => $r->methods()[0],       // GET, POST, PUT, DELETE
                    'controller'  => $controller,
                    'action'      => $action,
                    'model'       => ($attribute && $attribute->model) ? $attribute->model : $this->guessModel($r),
                    'middleware'  => $r->middleware(),
                    'parameters'  => $r->parameterNames(),   // e.g. ['id'] from {id}
                    'attribute'   => $attribute,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Extract the #[GraphQL] attribute from a controller method.
     */
    private function getActionAttribute(string $controller, string $action): ?GraphQL
    {
        if (!method_exists($controller, $action)) {
            return null;
        }

        $reflection = new ReflectionMethod($controller, $action);
        $attributes = $reflection->getAttributes(GraphQL::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Guess the Eloquent model from the controller name.
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
        if (class_exists($rootModel)) {
            return $rootModel;
        }

        return null;
    }
}
