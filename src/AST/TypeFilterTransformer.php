<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use XGraphQL\SchemaTransformer\Exception\InvalidArgumentException;

final readonly class TypeFilterTransformer implements TypeFilterTransformerInterface
{
    /**
     * @param string[]|null $only
     * @param string[]|null $excludes
     */
    public function __construct(private ?array $only = null, private ?array $excludes = null)
    {
        if (null === $this->only && null === $this->excludes) {
            throw new InvalidArgumentException('One of `only` or `excludes` argument should be set');
        }
    }

    public function filterType(TypeDefinitionNode $type, SchemaDefinitionNode $schema): bool
    {
        $typename = $type->getName()->value;

        if (null !== $this->only) {
            return in_array($typename, $this->only, true);
        }

        return !in_array($typename, $this->excludes, true);
    }
}
