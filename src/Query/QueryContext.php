<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Query;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;

final readonly class QueryContext
{
    public function __construct(
        public Schema $schema,
        public OperationDefinitionNode $operation,
        public array $fragments,
        public array $variableValues,
    ) {
    }
}
