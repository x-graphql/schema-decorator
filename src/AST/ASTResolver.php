<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\DefinitionNode;
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
use GraphQL\Language\Parser;
use XGraphQL\SchemaTransformer\Exception\LogicException;
use XGraphQL\SchemaTransformer\TransformerInterface;

final readonly class ASTResolver
{
    /**
     * @param TransformerInterface[] $transformers
     */
    public function __construct(private iterable $transformers)
    {
    }

    public function resolve(DocumentNode $ast): void
    {
        $this->addTransformDirectives($ast);

        $schema = $this->findOrAddSchemaDefinition($ast);
        $types = [];

        foreach ($ast->definitions as $pos => $definition) {
            if ($definition instanceof TypeDefinitionNode) {
                if (!$this->shouldKeepType($definition, $schema)) {
                    unset($ast->definitions[$pos]);

                    continue;
                }

                $types[$definition->getName()->value] = $definition;

                $this->transformType($definition, $schema);
            }
        }

        $this->transformReferenceTypes($ast, $types);
        $this->postTransform($ast);

        $ast->definitions->reindex();
    }

    private function addTransformDirectives(DocumentNode $ast): void
    {
        $ast->definitions[] = Parser::directiveDefinition(NameTransformedDirective::definition());
    }

    private function findOrAddSchemaDefinition(DocumentNode $ast): SchemaDefinitionNode
    {
        $sdl = '';

        foreach ($ast->definitions as $definition) {
            if ($definition instanceof SchemaDefinitionNode) {
                return $definition;
            }

            if ($definition instanceof ObjectTypeDefinitionNode) {
                $typeName = $definition->getName()->value;

                if ($typeName === 'Query') {
                    $sdl .= 'query: Query' . PHP_EOL;
                }

                if ($typeName === 'Mutation') {
                    $sdl .= 'mutation: Mutation' . PHP_EOL;
                }
            }
        }

        if ('' === $sdl) {
            throw new LogicException('Missing root operation types');
        }

        return $ast->definitions[] = Parser::schemaDefinition(sprintf('schema { %s }', $sdl));
    }

    private function shouldKeepType(TypeDefinitionNode $type, SchemaDefinitionNode $schema): bool
    {
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof TypeFilterTransformerInterface) {
                continue;
            }

            if (false === $transformer->filterType($type, $schema)) {
                return false;
            }
        }

        return true;
    }

    private function transformType(TypeDefinitionNode $type, SchemaDefinitionNode $schema): void
    {
        if (
            $type instanceof InputObjectTypeDefinitionNode
            || $type instanceof ObjectTypeDefinitionNode
            || $type instanceof InterfaceTypeDefinitionNode
        ) {
            $this->transformTypeFields($type, $schema);
        }

        $this->transformNameOfType($type, $schema);
    }

    private function transformTypeFields(
        ObjectTypeDefinitionNode|InputObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $type,
        SchemaDefinitionNode $schema,
    ): void {
        foreach ($type->fields as $pos => $field) {
            if (!$this->shouldKeepField($field, $type, $schema)) {
                unset($type->fields[$pos]);

                continue;
            }

            $this->transformNameOfField($field, $type, $schema);
        }

        $type->fields->reindex();
    }

    private function shouldKeepField(
        FieldDefinitionNode|InputValueDefinitionNode $field,
        ObjectTypeDefinitionNode|InputObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $type,
        SchemaDefinitionNode $schema,
    ): bool {
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof FieldFilterTransformerInterface) {
                continue;
            }

            if (false === $transformer->filterField($field, $type, $schema)) {
                return false;
            }
        }

        return true;
    }

    private function transformNameOfField(
        FieldDefinitionNode|InputValueDefinitionNode $field,
        ObjectTypeDefinitionNode|InputObjectTypeDefinitionNode|InterfaceTypeDefinitionNode $type,
        SchemaDefinitionNode $schema,
    ): void {
        $original = $transformed = $field->name->value;

        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof FieldNameTransformerInterface) {
                continue;
            }

            $transformed = $transformer->transformFieldName($transformed, $field, $type, $schema);
        }

        if ($original === $transformed) {
            return;
        }

        $field->directives[] = $this->makeNameTransformedDirective($original);

        $field->name->value = $transformed;
    }

    private function transformNameOfType(TypeDefinitionNode $type, SchemaDefinitionNode $schema): void
    {
        $original = $transformed = $type->getName()->value;

        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof TypenameTransformerInterface) {
                continue;
            }

            $transformed = $transformer->transformTypename($transformed, $type, $schema);
        }

        if ($transformed === $original || !property_exists($type, 'directives')) {
            return;
        }

        $type->getName()->value = $transformed;
        $type->directives[] = $this->makeNameTransformedDirective($original);
    }

    private function makeNameTransformedDirective(string $original): DirectiveNode
    {
        return Parser::directive(
            sprintf('@%s(original: "%s")', NameTransformedDirective::NAME, $original),
        );
    }

    /**
     * @param DocumentNode $ast
     * @param TypeDefinitionNode[] $types
     * @return void
     */
    private function transformReferenceTypes(DocumentNode $ast, array $types): void
    {
        foreach ($ast->definitions as $definition) {
            $this->transformNameOfReferences($definition, $types);
        }
    }

    /**
     * @param DefinitionNode $definition
     * @param TypeDefinitionNode[] $types
     * @return void
     */
    private function transformNameOfReferences(DefinitionNode $definition, array $types): void
    {
        if ($definition instanceof SchemaDefinitionNode) {
            foreach ($definition->operationTypes as $operationType) {
                /** @var OperationTypeDefinitionNode $operationType */

                $namedType = $operationType->type;
                $type = $types[$namedType->name->value] ?? null;

                $namedType->name->value = $type?->getName()->value ?? $namedType->name->value;
            }
        }

        if ($definition instanceof UnionTypeDefinitionNode) {
            foreach ($definition->types as $namedType) {
                /** @var NamedTypeNode $namedType */
                $type = $types[$namedType->name->value] ?? null;

                $namedType->name->value = $type?->getName()->value ?? $namedType->name->value;
            }
        }

        if ($definition instanceof DirectiveNode) {
            foreach ($definition->arguments as $arg) {
                $this->transformNameOfReferenceFieldOrArg($arg, $types);
            }
        }

        if (
            $definition instanceof InterfaceTypeDefinitionNode
            || $definition instanceof ObjectTypeDefinitionNode
            || $definition instanceof InputObjectTypeDefinitionNode
        ) {
            foreach ($definition->fields as $field) {
                if ($field instanceof FieldDefinitionNode) {
                    foreach ($field->arguments as $arg) {
                        $this->transformNameOfReferenceFieldOrArg($arg, $types);
                    }
                }

                $this->transformNameOfReferenceFieldOrArg($field, $types);
            }

            if (property_exists($definition, 'interfaces')) {
                foreach ($definition->interfaces as $namedType) {
                    /** @var NamedTypeNode $namedType */

                    $type = $types[$namedType->name->value] ?? null;

                    $namedType->name->value = $type?->getName()->value ?? $namedType->name->value;
                }
            }
        }
    }

    /**
     * @param FieldDefinitionNode|InputValueDefinitionNode $fieldOrArg
     * @param TypeDefinitionNode[] $types
     * @return void
     */
    private function transformNameOfReferenceFieldOrArg(
        FieldDefinitionNode|InputValueDefinitionNode $fieldOrArg,
        array $types
    ): void {
        $namedType = $fieldOrArg->type;

        while (!$namedType instanceof NamedTypeNode) {
            $namedType = $namedType->type;
        }

        $type = $types[$namedType->name->value] ?? null;

        $namedType->name->value = $type?->getName()->value ?? $namedType->name->value;
    }

    private function postTransform(DocumentNode $ast): void
    {
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof PostTransformerInterface) {
                continue;
            }

            $transformer->postTransform($ast);
        }
    }
}
