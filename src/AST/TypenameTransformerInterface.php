<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface TypenameTransformerInterface extends TransformerInterface
{
    public function transformTypename(string $name, TypeDefinitionNode $type, SchemaDefinitionNode $schema): string;
}
