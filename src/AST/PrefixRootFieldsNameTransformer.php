<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;

final readonly class PrefixRootFieldsNameTransformer implements FieldNameTransformerInterface
{
    public function __construct(private string $prefix)
    {
    }

    public function transformFieldName(
        string $name,
        InputValueDefinitionNode|FieldDefinitionNode $field,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|InputObjectTypeDefinitionNode $type,
        SchemaDefinitionNode $schema,
    ): string {
        foreach ($schema->operationTypes as $operationType) {
            /** @var OperationTypeDefinitionNode $operationType */

            if ($type->getName()->value === $operationType->type->name->value) {
                return $this->prefix . $name;
            }
        }

        return $name;
    }
}
