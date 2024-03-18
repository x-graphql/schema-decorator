<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

interface PreExecutionTransformerInterface
{
    public function preExecute(TransformContext $context): void;
}
