<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\AST;

use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use XGraphQL\SchemaTransformer\AST\RemoveUnusedTypeTransformer;
use XGraphQL\SchemaTransformer\Exception\LogicException;
use XGraphQL\Utils\SchemaPrinter;

class RemoveUnusedTypeTransformerTest extends TestCase
{
    #[DataProvider(methodName: 'dataProvider')]
    public function testRemoveUnusedTypes(
        RemoveUnusedTypeTransformer $transformer,
        string $sdl,
        string $sdlExpecting
    ): void {
        $ast = Parser::parse($sdl);

        $transformer->postTransform($ast);

        $actualSDL = SchemaPrinter::doPrint(
            BuildSchema::buildAST($ast, options: ['assumeValidSDL' => true]),
        );

        $this->assertEquals($sdlExpecting, $actualSDL);
    }

    public function testMissingSchemaWillThrowLogicException(): void
    {
        $transformer = new RemoveUnusedTypeTransformer();
        $ast = Parser::parse(
            <<<'SDL'
type Query {
  test: String!
}
SDL
        );

        $this->expectException(LogicException::class);

        $transformer->postTransform($ast);
    }

    public static function dataProvider(): array
    {
        $sdl = <<<'SDL'
directive @test(arg: InputObjectA!) on FIELD

schema {
  query: Query
}

type Query {
  field_a: InterfaceA
  field_b: UnionA
  field_c: ID!
}

interface InterfaceA {
  id: ID!
}

union UnionA = ObjectA | ObjectB

type ObjectA implements InterfaceA {
  id: ID!
  field_a(arg_a: InputObjectB): ID!
}

type ObjectB {
  id: ID!
  field_b: EmptyObject!
}

type UnusedType {
  field_a: InterfaceA
  field_c: ID!
}

type EmptyObject

type UnusedEmptyObject

type InputObjectA {
  field_a: String!
}

input InputObjectB {
  field_a: String!
}

input UnusedInputObject {
  field_a: String!
}
SDL;
        $transformer = new RemoveUnusedTypeTransformer();
        $transformerSkipRemoveUnusedTypes = new RemoveUnusedTypeTransformer(['UnusedInputObject']);

        return [
            'remove all unused types' => [
                $transformer,
                $sdl,
                <<<'SDL'
directive @test(arg: InputObjectA!) on FIELD

type Query {
  field_a: InterfaceA
  field_b: UnionA
  field_c: ID!
}

interface InterfaceA {
  id: ID!
}

union UnionA = ObjectA | ObjectB

type ObjectA implements InterfaceA {
  id: ID!
  field_a(arg_a: InputObjectB): ID!
}

type ObjectB {
  id: ID!
  field_b: EmptyObject!
}

type EmptyObject

type InputObjectA {
  field_a: String!
}

input InputObjectB {
  field_a: String!
}

SDL
            ],
            'remove unused types except `UnusedInputObject`' => [
                $transformerSkipRemoveUnusedTypes,
                $sdl,
                <<<'SDL'
directive @test(arg: InputObjectA!) on FIELD

type Query {
  field_a: InterfaceA
  field_b: UnionA
  field_c: ID!
}

interface InterfaceA {
  id: ID!
}

union UnionA = ObjectA | ObjectB

type ObjectA implements InterfaceA {
  id: ID!
  field_a(arg_a: InputObjectB): ID!
}

type ObjectB {
  id: ID!
  field_b: EmptyObject!
}

type EmptyObject

type InputObjectA {
  field_a: String!
}

input InputObjectB {
  field_a: String!
}

input UnusedInputObject {
  field_a: String!
}

SDL
            ],
        ];
    }
}
