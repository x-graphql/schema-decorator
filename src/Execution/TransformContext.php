<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;

final readonly class TransformContext
{
    public function __construct(
        public Schema $schema,
        public OperationDefinitionNode $operation,
        public array $fragments,
        public array $variableValues,
    ) {
    }
}
