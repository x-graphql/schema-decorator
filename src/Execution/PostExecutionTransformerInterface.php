<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Executor\ExecutionResult;
use XGraphQL\SchemaTransformer\TransformerInterface;

interface PostExecutionTransformerInterface extends TransformerInterface
{
    public function postExecute(TransformContext $context, ExecutionResult $result): void;
}
