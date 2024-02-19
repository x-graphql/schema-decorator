<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Result;

use GraphQL\Executor\ExecutionResult;
use XGraphQL\SchemaTransformer\Query\QueryContext;
use XGraphQL\SchemaTransformer\TransformerInterface;

final readonly class ResultResolver
{
    /**
     * @param TransformerInterface[] $transformers
     */
    public function __construct(private iterable $transformers)
    {
    }

    public function resolve(QueryContext $context, ExecutionResult $result): ExecutionResult
    {
    }
}
