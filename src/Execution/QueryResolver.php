<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;
use XGraphQL\SchemaTransformer\AST\NameTransformedDirective;
use XGraphQL\SchemaTransformer\Exception\InvalidArgumentException;
use XGraphQL\SchemaTransformer\TransformerInterface;
use XGraphQL\Utils\Variable;

final readonly class QueryResolver
{
    /**
     * @param TransformerInterface[] $transformers
     */
    public function __construct(private iterable $transformers)
    {
    }

    public function resolve(Context $context): void
    {
        $this->transformVariableDefinitions($context);
        $this->transformFragments($context);
        $this->transformSelectionSet(
            $context->schema->getOperationType($context->operation->operation),
            $context->operation->selectionSet,
            $context
        );

        /// Need to clean up unused variable values after transformed.
        $this->removeUnusedVariableValues($context);
    }

    private function transformVariableDefinitions(Context $context): void
    {
        foreach ($context->operation->variableDefinitions as $definition) {
            /** @var VariableDefinitionNode $definition */
            $typeNode = $definition->type;

            while (!$typeNode instanceof NamedTypeNode) {
                $typeNode = $typeNode->type;
            }

            $nameNode = $typeNode->name;
            $ast = $context->schema->getType($nameNode->value)->astNode();

            if (null !== $ast) {
                $this->transformNameNode($nameNode, $ast);
            }
        }
    }

    private function transformFragments(Context $context): void
    {
        foreach ($context->fragments as $fragment) {
            $nameNode = $fragment->typeCondition->name;
            $type = $context->schema->getType($nameNode->value);
            $ast = $type->astNode();

            $this->transformSelectionSet($type, $fragment->selectionSet, $context);

            if (null !== $ast) {
                $this->transformNameNode($nameNode, $type->astNode());
            }
        }
    }

    private function transformSelectionSet(Type $type, SelectionSetNode $selectionSet, Context $context): void
    {
        if ($type instanceof WrappingType) {
            $type = $type->getInnermostType();
        }

        if (!$type instanceof ObjectType && !$type instanceof InterfaceType && !$type instanceof UnionType) {
            throw new InvalidArgumentException('Type should be object, interface, or union.');
        }

        foreach ($selectionSet->selections as $selection) {
            $nameNode = $ast = null;

            if ($selection instanceof FieldNode) {
                $nameNode = $selection->name;

                /// Skip transform system field
                if (Introspection::TYPE_NAME_FIELD_NAME === $nameNode->value) {
                    continue;
                }

                $fieldDefinition = $type->getField($nameNode->value);
                $ast = $fieldDefinition->astNode;

                if (null !== $selection->selectionSet) {
                    $this->transformSelectionSet($fieldDefinition->getType(), $selection->selectionSet, $context);
                }
            }

            if ($selection instanceof InlineFragmentNode) {
                $nameNode = $selection->typeCondition->name;
                $selectionType = $context->schema->getType($nameNode->value);
                $ast = $selectionType->astNode();

                $this->transformSelectionSet($selectionType, $selection->selectionSet, $context);
            }

            $this->transformSelection($type, $selection, $context);

            if (null !== $nameNode && null !== $ast) {
                $alias = $nameNode->value;

                $this->transformNameNode($nameNode, $ast);

                if ($selection instanceof FieldNode && null === $selection->alias) {
                    $selection->alias = Parser::name($alias);
                }
            }
        }

        $selectionSet->selections->reindex();
    }

    private function transformNameNode(NameNode $nameNode, TypeDefinitionNode|FieldDefinitionNode $ast): void
    {
        $originalName = NameTransformedDirective::findOriginalName($ast);

        if (null !== $originalName) {
            $nameNode->value = $originalName;
        }
    }

    private function transformSelection(
        ObjectType|InterfaceType|UnionType $type,
        SelectionNode $selection,
        Context $context
    ): void {
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof SelectionTransformerInterface) {
                continue;
            }

            $transformer->transformSelection($type, $selection, $context);
        }
    }

    private function removeUnusedVariableValues(Context $context): void
    {
        $variablesUsing = array_fill_keys($this->getVariablesUsing($context), true);

        foreach ($context->variableValues as $name => $value) {
            if (!array_key_exists($name, $variablesUsing)) {
                unset($context->variableValues[$name]);
            }
        }

        $variableDefinitions = $context->operation->variableDefinitions;

        foreach ($variableDefinitions as $pos => $definition) {
            /** @var VariableDefinitionNode $definition */

            if (!array_key_exists($definition->variable->name->value, $variablesUsing)) {
                unset($variableDefinitions[$pos]);
            }
        }

        $variableDefinitions->reindex();
    }

    private function getVariablesUsing(Context $context): array
    {
        $variables = array_merge(
            Variable::getVariablesInOperation($context->operation),
            Variable::getVariablesInFragments($context->fragments),
        );

        return array_unique($variables);
    }
}
