<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface TypeFilterTransformerInterface extends TransformerInterface
{
    public function filterType(TypeDefinitionNode $type, SchemaDefinitionNode $schema): bool;
}
