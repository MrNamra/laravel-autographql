<?php

namespace MrNamra\AutoGraphQL\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class GraphQL
{
    public function __construct(
        public ?string $query = null,
        public ?string $mutation = null,
        public ?string $description = null,
        public ?string $model = null,
        public array $eagerLoad = [],
        public array $middleware = [],
        public bool $skip = false,
        public ?string $deprecated = null,
    ) {}
}
