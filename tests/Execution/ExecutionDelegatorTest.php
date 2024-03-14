<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\Execution;

use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;
use XGraphQL\Delegate\SchemaDelegatorInterface;
use XGraphQL\SchemaTransformer\Execution\ExecutionDelegator;
use XGraphQL\SchemaTransformer\Execution\ResultTransformerInterface;
use XGraphQL\SchemaTransformer\Execution\SelectionTransformerInterface;
use XGraphQL\SchemaTransformer\Execution\TransformContext;

class ExecutionDelegatorTest extends TestCase
{
    public function testConstructor(): void
    {
        $promiseAdapter = new SyncPromiseAdapter();
        $delegator = $this->createMock(SchemaDelegatorInterface::class);
        $delegator->expects($this->once())->method('getPromiseAdapter')->willReturn($promiseAdapter);

        $resolver = new ExecutionDelegator(
            $delegator,
            [],
        );

        $this->assertInstanceOf(ExecutionDelegator::class, $resolver);
        $this->assertEquals($promiseAdapter, $resolver->getPromiseAdapter());
    }

    public function testDelegate(): void
    {
        $adapter = new SyncPromiseAdapter();
        $schema = $this->createTransformedSchema();
        $delegator = $this->createMock(SchemaDelegatorInterface::class);

        $delegator
            ->expects($this->once())
            ->method('delegateToExecute')
            ->willReturn($adapter->createFulfilled(new ExecutionResult()));

        $resultTransformer = $this->createMock(ResultTransformerInterface::class);

        $resultTransformer
            ->expects($this->once())
            ->method('transformResult')
            ->willReturnCallback(function (TransformContext $context, ExecutionResult $result): void {
                $result->data = ['transformed'];
            });

        $selectionTransformer = $this->createMock(SelectionTransformerInterface::class);

        $selectionTransformer
            ->expects($this->any())
            ->method('transformSelection')
            ->willReturnCallback(
                function (
                    ObjectType|InterfaceType|UnionType $type,
                    SelectionNode $selection,
                    TransformContext $context
                ) {
                    if (
                        $type->name() === 'XGraphQLQuery'
                        && $selection instanceof FieldNode
                        && $selection->name->value === 'xGraphQL_items'
                    ) {
                        /** @var ObjectValueNode $value */
                        $value = $selection->arguments[0]->value;
                        $value->fields[] = Parser::objectField('category: "test"');
                    }
                }
            );

        $resolver = new ExecutionDelegator(
            $delegator,
            [$resultTransformer, $selectionTransformer],
        );
        $fragment = Parser::fragmentDefinition(
            <<<'GQL'
fragment Shirt on XGraphQLShirt {
  id
}
GQL
        );
        $operation = Parser::operationDefinition(
            <<<'GQL'
query test($unusedVar: Boolean!) {
  xGraphQL_items(filter: { name: { eq: "test" } }) {
    name
    ...on XGraphQLShoes {
      category {
        name
      }
    }
    ...Shirt
  }
}
GQL
        );

        $promise = $resolver->delegateToExecute($schema, $operation, [$fragment], ['unusedVar' => true]);

        /** @var ExecutionResult $result */
        $result = $adapter->wait($promise);

        $expectingFragmentTransformed = <<<'GQL'
fragment Shirt on Shirt {
  id
}
GQL;
        $actualFragmentTransformed = Printer::doPrint($fragment);

        $expectingQueryTransformed = <<<'GQL'
query test {
  xGraphQL_items: items(filter: { name: { eq: "test" }, category: "test" }) {
    name
    ... on Shoes {
      category {
        name
      }
    }
    ...Shirt
  }
}
GQL;
        $actualQueryTransformed = Printer::doPrint($operation);

        $this->assertEquals($expectingFragmentTransformed, $actualFragmentTransformed);
        $this->assertEquals($expectingQueryTransformed, $actualQueryTransformed);
        $this->assertEquals(['transformed'], $result->data);
    }

    private function createTransformedSchema(): Schema
    {
        $sdl = <<<'SDL'
type XGraphQLQuery @nameTransformed(original: "Query") {
  xGraphQL_items(filter: XGraphQLItemFilter!): [XGraphQLItemInterface!]! @nameTransformed(original: "items")
  xGraphQL_categories: [XGraphQLCategory!]! @nameTransformed(original: "categories")
}

input XGraphQLItemFilter @nameTransformed(original: "ItemFilter") {
  name: XGraphQLItemNameFilter!
  category: String
}

input XGraphQLItemNameFilter @nameTransformed(original: "ItemNameFilter") {
  equal: String
  like: String
}

type XGraphQLMutation @nameTransformed(original: "Mutation") {
  xGraphQL_addShoes(name: String!): XGraphQLShoes! @nameTransformed(original: "addShoes")
  xGraphQL_addShirt(name: String!): XGraphQLShirt! @nameTransformed(original: "addShirt")
}

type XGraphQLCategory @nameTransformed(original: "Category") {
  name: String!
}

interface XGraphQLItemInterface @nameTransformed(original: "ItemInterface") {
  id: ID!
  name: String!
  category: XGraphQLCategory!
}

type XGraphQLShoes implements XGraphQLItemInterface @nameTransformed(original: "Shoes") {
  id: ID!
  name: String!
  category: XGraphQLCategory!
}

type XGraphQLShirt implements XGraphQLItemInterface @nameTransformed(original: "Shirt") {
  id: ID!
  name: String!
  category: XGraphQLCategory!
}

type XGraphQLBelt implements XGraphQLItemInterface @nameTransformed(original: "Belt") {
  id: ID!
  name: String!
  category: XGraphQLCategory!
}

directive @nameTransformed(original: String!) on INTERFACE | OBJECT | INPUT_OBJECT | FIELD_DEFINITION | INPUT_FIELD_DEFINITION | SCALAR | ENUM | UNION

schema {
  query: XGraphQLQuery
  mutation: XGraphQLMutation
}
SDL;
        return BuildSchema::build($sdl);
    }
}
