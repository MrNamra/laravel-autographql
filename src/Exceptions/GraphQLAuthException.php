<?php

namespace MrNamra\AutoGraphQL\Exceptions;

use Exception;
use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

class GraphQLAuthException extends Exception implements ClientAware, ProvidesExtensions
{
    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'authentication';
    }

    public function getExtensions(): array
    {
        return [
            'status' => 401,
        ];
    }
}
