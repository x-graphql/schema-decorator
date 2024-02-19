<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\AST;

use GraphQL\Language\AST\DocumentNode;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface PostTransformerInterface extends TransformerInterface
{
    public function postTransform(DocumentNode $ast): void;
}
