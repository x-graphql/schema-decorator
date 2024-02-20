<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;
use XGraphQL\DelegateExecution\ExecutionDelegatorInterface;
use XGraphQL\SchemaTransformer\TransformerInterface;

final readonly class TransformExecutionDelegator implements ExecutionDelegatorInterface
{
    /**
     * @param ExecutionDelegatorInterface $decorated
     * @param iterable<TransformerInterface> $transformers
     */
    public function __construct(
        private ExecutionDelegatorInterface $decorated,
        private iterable $transformers,
    ) {
    }

    public function delegate(
        Schema $executionSchema,
        OperationDefinitionNode $operation,
        array $fragments = [],
        array $variables = []
    ): Promise {
        $context = new Context($executionSchema, $operation, $fragments, $variables);
        $queryResolver = new QueryResolver($this->transformers);
        $resultResolver = new ResultResolver($this->transformers);

        $queryResolver->resolve($context);

        $promise = $this->decorated->delegate(
            $executionSchema,
            $context->operation,
            $context->fragments,
            $context->variableValues
        );

        return $promise->then(
            function (ExecutionResult $result) use ($resultResolver, $context) {
                $resultResolver->resolve($context, $result);

                return $result;
            }
        );
    }

    public function getPromiseAdapter(): PromiseAdapter
    {
        return $this->decorated->getPromiseAdapter();
    }
}
