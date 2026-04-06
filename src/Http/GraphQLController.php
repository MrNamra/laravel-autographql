<?php

namespace MrNamra\AutoGraphQL\Http;

use GraphQL\GraphQL;
use GraphQL\Language\Parser;
use GraphQL\Schema;
use GraphQL\Error\DebugFlag;
use Illuminate\Http\{Request, JsonResponse};
use MrNamra\AutoGraphQL\SchemaGenerator;
use MrNamra\AutoGraphQL\EagerLoadingAnalyzer;
use Illuminate\Routing\Controller;

class GraphQLController extends Controller
{
    /**
     * Handles /graphql POST request.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Authorization check for production
        if (app()->isProduction()) {
            if (!$this->isAllowedOnProduction()) {
                return response()->json(['errors' => [['message' => 'GraphQL API is disabled in production.']]], 403);
            }
        }

        // 2. Generate/Retrieve Schema
        /** @var Schema $schema */
        $schema = app(SchemaGenerator::class)->generate();

        $query = $request->input('query');
        $vars  = $request->input('variables', []);

        if (empty($query)) {
            return response()->json(['errors' => [['message' => 'Query is required.']]], 400);
        }

        // 3. Execute Query
        $debug = config('app.debug') 
            ? (DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE) 
            : DebugFlag::NONE;

        $result = GraphQL::executeQuery($schema, $query, null, null, (array)$vars);
        $data   = $result->toArray($debug);
        $status = 200;

        // Determine dynamic status code based on errors
        if (!empty($data['errors'])) {
            foreach ($data['errors'] as $error) {
                if (isset($error['extensions']['status'])) {
                    $code = (int)$error['extensions']['status'];
                    // 401 has higher priority than 422 in this bridge logic
                    if ($code === 401) {
                        $status = 401;
                        break;
                    }
                    if ($code === 422 && $status === 200) {
                        $status = 422;
                    }
                }
            }
        }

        return response()->json($data, $status);
    }

    /**
     * Handles /graphiql GET request for playground.
     */
    public function playground()
    {
        return view('autographql::graphiql', [
            'endpoint' => config('autographql.endpoint', '/graphql'),
        ]);
    }

    /**
     * Production safety check.
     */
    protected function isAllowedOnProduction(): bool
    {
        $allow = config('autographql.production.allow_on_production', false);
        if (!$allow) {
            return false;
        }

        $guard = config('autographql.production.guard');
        if ($guard && is_callable($guard)) {
            return call_user_func($guard, request());
        }

        return true;
    }
}
