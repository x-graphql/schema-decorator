<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;

final class TransformContext
{
    public function __construct(
        public readonly Schema $executionSchema,
        public readonly OperationDefinitionNode $operation,
        public readonly array $fragments,
        public array $variableValues,
    ) {
    }
}
