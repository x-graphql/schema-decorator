<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Query;

use GraphQL\Language\AST\SelectionNode;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface SelectionTransformerInterface extends TransformerInterface
{
    public function transformSelection(ObjectType|InterfaceType $type, SelectionNode $selection, QueryContext $context): void;
}
