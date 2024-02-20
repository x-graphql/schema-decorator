<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Executor\ExecutionResult;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface ResultTransformerInterface extends TransformerInterface
{
    public function transformResult(Context $context, ExecutionResult $result): void;
}
