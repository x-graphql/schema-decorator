<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;

final readonly class PrefixTypenameTransformer implements TypenameTransformerInterface
{
    public function __construct(private string $prefix, private array $skips = [])
    {
    }

    public function transformTypename(string $name, TypeDefinitionNode $type, SchemaDefinitionNode $schema): string
    {
        if (in_array($name, $this->skips, true)) {
            return $name;
        }

        return $this->prefix . $name;
    }
}
