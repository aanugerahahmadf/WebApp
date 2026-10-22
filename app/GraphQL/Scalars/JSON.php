<?php

namespace App\GraphQL\Scalars;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

class JSON extends ScalarType
{
    public ?string $description = 'The `JSON` scalar type represents arbitrary JSON values (objects, arrays, strings, numbers, booleans, or null).';

    /**
     * Serializes an internal value to include in a response.
     */
    public function serialize(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Parses an externally provided value (query variable) to use as an input.
     */
    public function parseValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Parses an externally provided literal value (GraphQL query string) to use as an input.
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        return AST::valueFromASTUntyped($valueNode, $variables);
    }
}
