<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Language\AST\SelectionNode;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface SelectionTransformerInterface extends TransformerInterface
{
    public function transformSelection(ObjectType|InterfaceType|UnionType $type, SelectionNode $selection, TransformContext $context): void;
}
