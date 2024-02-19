<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface FieldNameTransformerInterface extends TransformerInterface
{
    public function transformFieldName(
        string $name,
        FieldDefinitionNode|InputValueDefinitionNode $field,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|InputObjectTypeDefinitionNode $type,
        SchemaDefinitionNode $schema,
    ): string;
}
