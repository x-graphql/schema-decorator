<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\OperationTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use XGraphQL\SchemaTransformer\Exception\LogicException;

final readonly class PruneTransformer implements PostTransformerInterface
{
    public function __construct(
        private bool $skipRemoveUnusedTypes = false,
        private bool $skipRemoveEmptyObjectInterface = false
    ) {
    }

    public function postTransform(DocumentNode $ast): void
    {
        $schemaDefinition = null;
        $types = $usingTypes = $abstractTypes = $directives = $removedTypes = [];

        foreach ($ast->definitions as $definition) {
            if ($definition instanceof SchemaDefinitionNode) {
                $schemaDefinition = $definition;
            }

            if ($definition instanceof TypeDefinitionNode) {
                $types[$definition->getName()->value] = $definition;
            }

            if ($definition instanceof ObjectTypeDefinitionNode) {
                foreach ($definition->interfaces as $interface) {
                    /** @var NamedTypeNode $interface */
                    $abstractTypes[$interface->name->value][] = $definition->getName()->value;
                }
            }

            if ($definition instanceof UnionTypeDefinitionNode) {
                foreach ($definition->types as $subType) {
                    /** @var NamedTypeNode $subType */
                    $abstractTypes[$definition->getName()->value][] = $subType->name->value;
                }
            }

            if ($definition instanceof DirectiveNode) {
                $directives[$definition->name->value] = $definition;
            }
        }

        if (null === $schemaDefinition) {
            throw new LogicException('Not found schema definition on AST, something went wrong!');
        }

        foreach ($directives as $directive) {
            foreach ($directive->arguments as $arg) {
                $usingTypes += $this->getTypeOfArgOrField($arg, $types, $usingTypes, $abstractTypes);
            }
        }

        foreach ($schemaDefinition->operationTypes as $operationType) {
            /** @var OperationTypeDefinitionNode $operationType */
            $typename = $operationType->type->name->value;

            if (isset($types[$typename]) && $types[$typename] instanceof ObjectTypeDefinitionNode) {
                $usingTypes[$typename] = true;

                $usingTypes += $this->getTypesOfFieldsUsing($types[$typename], $types, $abstractTypes, $usingTypes);
            }
        }

        foreach ($ast->definitions as $pos => $definition) {
            if (!$definition instanceof TypeDefinitionNode) {
                continue;
            }

            $typename = $definition->getName()->value;

            if (!$this->skipRemoveUnusedTypes && false === ($usingTypes[$typename] ?? false)) {
                unset($ast->definitions[$pos]);
                $removedTypes[$typename] = true;

                /// Type had been removed, nothing to do.
                continue;
            }

            if (
                !$this->skipRemoveEmptyObjectInterface
                && ($definition instanceof ObjectTypeDefinitionNode || $definition instanceof InterfaceTypeDefinitionNode)
                && 0 === $definition->fields->count()
            ) {
                unset($ast->definitions[$pos]);
                $removedTypes[$typename] = true;
            }
        }

        $ast->definitions->reindex();

        foreach ($schemaDefinition->operationTypes as $pos => $operationType) {
            /** @var OperationTypeDefinitionNode $operationType */
            $typename = $operationType->type->name->value;

            if (isset($removedTypes[$typename])) {
                unset($schemaDefinition->operationTypes[$pos]);
            }
        }

        $schemaDefinition->operationTypes->reindex();
    }

    /**
     * @param ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|InputObjectTypeDefinitionNode $type
     * @param TypeDefinitionNode[] $types
     * @param bool[] $usingTypes
     * @return bool[]
     */
    private function getTypesOfFieldsUsing(
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode|InputObjectTypeDefinitionNode $type,
        array $types,
        array $abstractTypes,
        array $usingTypes,
    ): array {
        foreach ($type->fields as $field) {
            if ($field instanceof FieldDefinitionNode) {
                foreach ($field->arguments as $arg) {
                    $usingTypes += $this->getTypeOfArgOrField($arg, $types, $abstractTypes, $usingTypes);
                }
            }

            $usingTypes += $this->getTypeOfArgOrField($field, $types, $abstractTypes, $usingTypes);
        }

        return $usingTypes;
    }

    private function getTypeOfArgOrField(
        FieldDefinitionNode|InputValueDefinitionNode $field,
        array $types,
        array $abstractTypes,
        array $usingTypes,
    ): array {
        $fieldType = $field->type;

        while (!$fieldType instanceof NamedTypeNode) {
            $fieldType = $fieldType->type;
        }

        $fieldTypename = $fieldType->name->value;

        if (isset($usingTypes[$fieldTypename])) {
            return $usingTypes;
        }

        $usingTypes[$fieldTypename] = true;

        $definition = $types[$fieldTypename] ?? null;

        if (
            $definition instanceof InputObjectTypeDefinitionNode
            || $definition instanceof ObjectTypeDefinitionNode
            || $definition instanceof InterfaceTypeDefinitionNode
        ) {
            $usingTypes += $this->getTypesOfFieldsUsing($definition, $types, $abstractTypes, $usingTypes);
        }

        foreach ($abstractTypes[$fieldTypename] ?? [] as $implTypename) {
            if (isset($usingTypes[$implTypename])) {
                continue;
            }

            $usingTypes[$implTypename] = true;

            $implDefinition = $types[$implTypename] ?? null;

            if ($implDefinition instanceof ObjectTypeDefinitionNode) {
                $usingTypes += $this->getTypesOfFieldsUsing($implDefinition, $types, $abstractTypes, $usingTypes);
            }
        }

        return $usingTypes;
    }
}
