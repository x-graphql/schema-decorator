<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use XGraphQL\Delegate\DelegatorInterface;
use XGraphQL\Delegate\SchemaDelegatorInterface;
use XGraphQL\SchemaTransformer\AST\NameTransformedDirective;
use XGraphQL\SchemaTransformer\Exception\InvalidArgumentException;
use XGraphQL\SchemaTransformer\Exception\RuntimeException;
use XGraphQL\SchemaTransformer\TransformerInterface;

final readonly class ExecutionDelegator implements DelegatorInterface
{
    /**
     * @param SchemaDelegatorInterface $delegator
     * @param iterable<TransformerInterface> $transformers
     */
    public function __construct(
        private SchemaDelegatorInterface $delegator,
        private iterable $transformers,
    ) {
    }

    public function delegateToExecute(
        Schema $executionSchema,
        OperationDefinitionNode $operation,
        array $fragments = [],
        array $variables = []
    ): Promise {
        $context = new TransformContext($executionSchema, $operation, $fragments, $variables);
        $transformedTypenameMapping = new \SplObjectStorage();

        $this->transformSelectionSet(
            $context->executionSchema->getOperationType($context->operation->operation),
            $context->operation->selectionSet,
            $context,
            $transformedTypenameMapping,
        );
        $this->transformFragments($context, $transformedTypenameMapping);
        $this->transformVariableDefinitions($context);

        $this->preExecute($context);

        $promise = $this->delegator->delegateToExecute(
            $executionSchema,
            $context->operation,
            $context->fragments,
            $context->variableValues
        );

        return $promise->then(
            fn (ExecutionResult $result) => $this->postExecute(
                $context,
                $result,
                $transformedTypenameMapping,
            )
        );
    }

    public function getPromiseAdapter(): PromiseAdapter
    {
        return $this->delegator->getPromiseAdapter();
    }

    private function transformVariableDefinitions(TransformContext $context): void
    {
        foreach ($context->operation->variableDefinitions as $definition) {
            /** @var VariableDefinitionNode $definition */
            $typeNode = $definition->type;

            while (!$typeNode instanceof NamedTypeNode) {
                $typeNode = $typeNode->type;
            }

            $nameNode = $typeNode->name;
            $ast = $context->executionSchema->getType($nameNode->value)->astNode();

            if (null !== $ast) {
                $this->transformNameNode($nameNode, $ast);
            }
        }
    }

    private function transformFragments(
        TransformContext $context,
        \SplObjectStorage $transformedTypenameMapping
    ): void {
        foreach ($context->fragments as $fragment) {
            $nameNode = $fragment->typeCondition->name;
            $type = $context->executionSchema->getType($nameNode->value);
            $ast = $type->astNode();

            $this->transformSelectionSet($type, $fragment->selectionSet, $context, $transformedTypenameMapping);

            if (null !== $ast) {
                $this->transformNameNode($nameNode, $type->astNode());
            }
        }
    }

    private function transformSelectionSet(
        Type $type,
        SelectionSetNode $selectionSet,
        TransformContext $context,
        \SplObjectStorage $transformedTypenameMapping
    ): void {
        if ($type instanceof WrappingType) {
            $type = $type->getInnermostType();
        }

        if (!$type instanceof ObjectType && !$type instanceof InterfaceType && !$type instanceof UnionType) {
            throw new InvalidArgumentException('Type should be object, interface, or union.');
        }

        $this->trackingTransformedTypename($context, $type, $transformedTypenameMapping);

        foreach ($selectionSet->selections as $selection) {
            $nameNode = $ast = null;

            if ($selection instanceof FieldNode) {
                $nameNode = $selection->name;

                if (Introspection::TYPE_NAME_FIELD_NAME === $selection->alias?->value) {
                    /// Can not resolve impl type of execution result
                    /// if any fields with an alias `__typename` exists.
                    throw new RuntimeException(
                        sprintf(
                            'Using alias `%s` for select field `%s` on type `%s` is not allowed.',
                            Introspection::TYPE_NAME_FIELD_NAME,
                            $selection->name->value,
                            $type->name(),
                        )
                    );
                }

                /// Skip transform system field
                if (Introspection::TYPE_NAME_FIELD_NAME === $nameNode->value) {
                    continue;
                }

                $fieldDefinition = $type->getField($nameNode->value);
                $ast = $fieldDefinition->astNode;

                if (null !== $selection->selectionSet) {
                    $this->transformSelectionSet(
                        $fieldDefinition->getType(),
                        $selection->selectionSet,
                        $context,
                        $transformedTypenameMapping
                    );
                }
            }

            if ($selection instanceof InlineFragmentNode) {
                $nameNode = $selection->typeCondition->name;
                $selectionType = $context->executionSchema->getType($nameNode->value);
                $ast = $selectionType->astNode();

                $this->transformSelectionSet(
                    $selectionType,
                    $selection->selectionSet,
                    $context,
                    $transformedTypenameMapping,
                );
            }

            $this->transformSelection($type, $selection, $context);

            if (null !== $nameNode && null !== $ast) {
                $aliasValue = $nameNode->value;

                $this->transformNameNode($nameNode, $ast);

                $nameValue = $nameNode->value;

                if ($selection instanceof FieldNode && null === $selection->alias && $aliasValue !== $nameValue) {
                    $selection->alias = Parser::name($aliasValue);
                }
            }
        }

        $selectionSet->selections->reindex();
    }

    private function trackingTransformedTypename(
        TransformContext $context,
        ObjectType|InterfaceType|UnionType $type,
        \SplObjectStorage $transformedTypenameMapping
    ): void {
        if ($type instanceof AbstractType) {
            foreach ($context->executionSchema->getPossibleTypes($type) as $objectType) {
                $this->trackingTransformedTypename($context, $objectType, $transformedTypenameMapping);
            }
        }

        $originalTypename = NameTransformedDirective::findOriginalName($type->astNode);

        if (null !== $originalTypename) {
            $transformedTypenameMapping[$type] = $originalTypename;
        }
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
        TransformContext $context
    ): void {
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof SelectionTransformerInterface) {
                continue;
            }

            $transformer->transformSelection($type, $selection, $context);
        }
    }

    private function preExecute(TransformContext $context): void
    {
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof PreExecutionTransformerInterface) {
                continue;
            }

            $transformer->preExecute($context);
        }
    }

    /**
     * @param TransformContext $context
     * @param ExecutionResult $result
     * @param \SplObjectStorage<ObjectType|InterfaceType|UnionType> $transformedTypenameMapping
     * @return ExecutionResult
     */
    private function postExecute(
        TransformContext $context,
        ExecutionResult $result,
        \SplObjectStorage $transformedTypenameMapping
    ): ExecutionResult {
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof PostExecutionTransformerInterface) {
                continue;
            }

            $transformer->postExecute($context, $result);
        }

        $typenameMapping = [];

        foreach ($transformedTypenameMapping as $type) {
            $originalName = $transformedTypenameMapping[$type];
            $typenameMapping[$originalName] = $type->name();
        }

        if (is_array($result->data)) {
            array_walk_recursive(
                $result->data,
                function (mixed &$value, string $key) use ($typenameMapping) {
                    if (Introspection::TYPE_NAME_FIELD_NAME !== $key) {
                        return;
                    }

                    $value = $typenameMapping[$value] ?? $value;
                }
            );
        }

        return $result;
    }
}
