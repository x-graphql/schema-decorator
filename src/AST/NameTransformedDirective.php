<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\TypeDefinitionNode;

/**
 * @internal
 */
final class NameTransformedDirective
{
    public const NAME = 'nameTransformed';

    public static function definition(): string
    {
        return sprintf(
            'directive @%s(original: String!) on INTERFACE | OBJECT | INPUT_OBJECT | FIELD_DEFINITION | INPUT_FIELD_DEFINITION | SCALAR | ENUM',
            self::NAME
        );
    }

    public static function findOriginalName(TypeDefinitionNode|FieldDefinitionNode $node): ?string
    {
        $args = [];

        foreach ($node->directives as $directive) {
            /** @var DirectiveNode $directive */
            if ($directive->name->value !== self::NAME) {
                continue;
            }

            foreach ($directive->arguments as $arg) {
                /** @var ArgumentNode $arg */
                $name = $arg->name->value;
                $value = $arg->value;

                assert($value instanceof StringValueNode);

                $args[$name] = $value->value;
            }

            break;
        }

        return $args['original'] ?? null;
    }
}
