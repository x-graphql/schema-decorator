<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Result;

use GraphQL\Executor\ExecutionResult;
use XGraphQL\SchemaTransformer\Query\QueryContext;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface ResultTransformerInterface extends TransformerInterface
{
    public function transformResult(QueryContext $context, ExecutionResult $result);
}
