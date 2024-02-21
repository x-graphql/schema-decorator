<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter as BaseSchemaPrinter;

final class SchemaPrinter extends BaseSchemaPrinter
{
    public static function doPrint(Schema $schema, array $options = []): string
    {
        $directivesFilter = static function (Directive $directive) {
            if (Directive::isSpecifiedDirective($directive)) {
                return false;
            }

            return [] !== array_intersect(DirectiveLocation::EXECUTABLE_LOCATIONS, $directive->locations);
        };

        return static::printFilteredSchema(
            $schema,
            $directivesFilter,
            static fn(NamedType $type): bool => !$type->isBuiltInType(),
            $options
        );
    }

}
