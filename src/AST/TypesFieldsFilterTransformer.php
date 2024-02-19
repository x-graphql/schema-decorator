<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;

final readonly class TypesFieldsFilterTransformer implements FieldFilterTransformerInterface
{
    public function __construct(private array $typesFields)
    {
    }

    public function filterField(
        InputValueDefinitionNode|FieldDefinitionNode $field,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|InputObjectTypeDefinitionNode $type,
        SchemaDefinitionNode $schema,
    ): bool {
        $typeName = $type->getName()->value;
        $fieldName = $field->name->value;

        return !isset($this->typesFields[$typeName])
            || in_array($fieldName, $this->typesFields[$typeName], true);
    }
}
