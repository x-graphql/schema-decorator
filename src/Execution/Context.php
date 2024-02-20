<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\HasFieldsType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Schema;

final readonly class Context
{
    public \SplObjectStorage $abstractTypesUsing;

    public function __construct(
        public Schema $schema,
        public OperationDefinitionNode $operation,
        public array $fragments,
        public array $variableValues,
    ) {
        $this->calcAbstractTypesUsing($this->schema, $this->operation, $this->fragments);
    }

    private function calcAbstractTypesUsing(Schema $schema, OperationDefinitionNode $operation, array $fragments): void
    {
        $storage = new \SplObjectStorage();
        $abstractTypes = [];

        foreach ($fragments as $fragment) {
            $nameNode = $fragment->typeCondition->name;
            $type = $schema->getType($nameNode->value);

            $abstractTypes = array_merge(
                $abstractTypes,
                self::collectOnSelectionSet($schema, $type, $fragment->getSelectionSet())
            );
        }

        $rootType = $schema->getOperationType($operation->operation);

        $abstractTypes = array_merge(
            $abstractTypes,
            self::collectOnSelectionSet($schema, $rootType, $operation->getSelectionSet())
        );

        foreach ($abstractTypes as $type) {
            $storage[$type] = true;
        }

        $this->abstractTypesUsing = $storage;
    }

    private static function collectOnSelectionSet(Schema $schema, Type $type, SelectionSetNode $selectionSet): array
    {
        $abstractTypes = [];

        if ($type instanceof WrappingType) {
            $type = $type->getInnermostType();
        }

        if ($type instanceof AbstractType) {
            $abstractTypes[] = $type;
        }

        foreach ($selectionSet->selections as $selection) {
            if (!$selection instanceof FieldNode && !$selection instanceof InlineFragmentNode) {
                continue;
            }

            if ($selection instanceof FieldNode) {
                assert($type instanceof HasFieldsType);

                $selectionType = $type->getField($selection->name->value)->getType();
            } else {
                $selectionType = $schema->getType($selection->typeCondition->name->value);
            }

            if (null !== $selection->selectionSet) {
                $abstractTypes = array_merge(
                    $abstractTypes,
                    self::collectOnSelectionSet($schema, $selectionType, $selection->selectionSet)
                );
            }
        }

        return $abstractTypes;
    }
}
