<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\AST\OperationDefinitionNode;
use XGraphQL\DelegateExecution\ExecutionDelegatorInterface;

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

    public function delegate(OperationDefinitionNode $operation, array $fragments = [], array $variables = []): Promise
    {
        // TODO: Implement delegate() method.
    }

    public function getPromiseAdapter(): PromiseAdapter
    {
        return $this->decorated->getPromiseAdapter();
    }
}
