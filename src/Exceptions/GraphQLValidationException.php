<?php

namespace MrNamra\AutoGraphQL\Exceptions;

use Exception;
use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

class GraphQLValidationException extends Exception implements ClientAware, ProvidesExtensions
{
    private array $validationErrors;

    public function __construct(array $errors)
    {
        parent::__construct('Validation failed');
        $this->validationErrors = $errors;
    }

    public function isClientSafe(): bool
    {
        return true;
    }

    public function getCategory(): string
    {
        return 'validation';
    }

    public function getExtensions(): array
    {
        return [
            'status' => 422,
            'errors' => $this->validationErrors,
        ];
    }
}
