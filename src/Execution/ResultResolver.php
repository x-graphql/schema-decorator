<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Executor\ExecutionResult;
use XGraphQL\SchemaTransformer\TransformerInterface;

final readonly class ResultResolver
{
    /**
     * @param TransformerInterface[] $transformers
     */
    public function __construct(private iterable $transformers)
    {
    }

    public function resolve(Context $context, ExecutionResult $result): void
    {
        foreach ($this->transformers as $transformer) {
            if (!$transformer instanceof ResultTransformerInterface) {
                continue;
            }

            $transformer->transformResult($context, $result);
        }

        if (is_array($result->data)) {
            $result->data = $this->transformResultData($context, $result->data);
        }
    }

    private function transformResultData(Context $context, array $data): array
    {

    }
}
