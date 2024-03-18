<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\Execution;

use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;
use XGraphQL\SchemaTransformer\Execution\RemoveUnusedVariablesTransformer;
use XGraphQL\SchemaTransformer\Execution\TransformContext;

class RemoveUnusedVariablesTransformerTest extends TestCase
{
    public function testCanRemoveUnusedVariables()
    {
        $transformer = new RemoveUnusedVariablesTransformer();

        $operation = Parser::operationDefinition(
            <<<'GQL'
query test($unused: Boolean!, $useInFragment: Boolean!) {
  dummy
  ...test
}
GQL
        );

        $fragment = Parser::fragmentDefinition(
            <<<'GQL'
fragment test on Query {
  dummy2 @include(if: $useInFragment)
}
GQL
        );

        $context = new TransformContext(
            new Schema([]),
            $operation,
            ['test' => $fragment],
            ['unused' => true, 'useInFragment' => true],
        );

        $transformer->preExecute($context);

        $this->assertEquals(['useInFragment' => true], $context->variableValues);
        $this->assertCount(1, $context->operation->variableDefinitions);
        $this->assertEquals('useInFragment', $context->operation->variableDefinitions[0]->variable->name->value);
    }
}
