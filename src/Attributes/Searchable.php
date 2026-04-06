<?php

namespace MrNamra\AutoGraphQL\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
class Searchable
{
    // Simply marking a property/method as searchable
}
