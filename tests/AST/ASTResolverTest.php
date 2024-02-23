<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\AST;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use XGraphQL\SchemaTransformer\AST\ASTResolver;
use XGraphQL\SchemaTransformer\AST\PrefixRootFieldsNameTransformer;
use XGraphQL\SchemaTransformer\AST\PrefixTypenameTransformer;
use XGraphQL\SchemaTransformer\AST\RemoveUnusedTypeTransformer;
use XGraphQL\SchemaTransformer\AST\TypesFieldsFilterTransformer;
use XGraphQL\SchemaTransformer\Exception\LogicException;
use XGraphQL\SchemaTransformer\TransformerInterface;
use XGraphQL\Utils\SchemaPrinter;

class ASTResolverTest extends TestCase
{
    /**
     * @param TransformerInterface[] $transformers
     * @param DocumentNode $ast
     * @param string $expectingSDL
     * @return void
     */
    #[DataProvider(methodName: 'dataResolvableProvider')]
    public function testResolveAST(iterable $transformers, DocumentNode $ast, string $expectingSDL): void
    {
        $resolver = new ASTResolver($transformers);
        $resolver->resolve($ast);

        $schema = BuildSchema::buildAST($ast, options: ['assumeValidSDL' => true]);
        $actualSDL = SchemaPrinter::printSchemaExcludeTypeSystemDirectives($schema);

        $this->assertEquals(trim($expectingSDL), trim($actualSDL));
    }

    public function testMissingRootOperationType(): void
    {
        $resolver = new ASTResolver([]);
        $ast = Parser::parse('type query_root { test: String! }');

        $this->expectException(LogicException::class);

        $resolver->resolve($ast);
    }

    public static function dataResolvableProvider(): array
    {
        $sdl = <<<'SDL'
directive @cached(options: CacheOptions!) on FIELD

input CacheOptions {
  ttl: Int!
  key: String!
}

type Query {
  items(filter: ItemFilter!): [ItemInterface!]!
  categories: [Category!]!
}

input ItemFilter {
  name: ItemNameFilter!
  category: String!
}

input ItemNameFilter {
  equal: String!
  like: String!
}

type Mutation {
  addShoes(name: String!): Shoes!
  addShirt(name: String!): Shirt!
}

type Category {
  name: String!
  group: CategoryGroup!
  status: Status!
  updatedAt: DateTimeImmutable!
  createdAt: DateTimeImmutable!
}

interface ItemInterface {
  id: ID!
  name: String!
  category: Category!
}

type Shoes implements ItemInterface {
  id: ID!
  name: String!
  category: Category!
}

type Shirt implements ItemInterface {
  id: ID!
  name: String!
  category: Category!
}

type Belt implements ItemInterface {
  id: ID!
  name: String!
  category: Category!
}

type Glasses {
  id: ID!
  name: String!
  category: Category!
}

union CategoryGroup = CategoryStandardGroup | CategoryLuxuryGroup

type CategoryStandardGroup {
  name: String!
}

type CategoryLuxuryGroup {
  name: String!
}

scalar DateTime

scalar DateTimeImmutable

enum Status {
  ACTIVE
  INACTIVE
}
SDL;
        $ast = Parser::parse($sdl);

        return [
            'immutable ast' => [
                [],
                $ast,
                $sdl,
            ],
            'mutate ast by transformers' => [
                [
                    new PrefixRootFieldsNameTransformer('xGraphQL_'),
                    new PrefixTypenameTransformer('XGraphQL'),
                    new TypesFieldsFilterTransformer(['Category' => ['name']]),
                    new RemoveUnusedTypeTransformer(),
                ],
                $ast,
                <<<'SDL'
schema {
  query: XGraphQLQuery
  mutation: XGraphQLMutation
}

directive @cached(options: XGraphQLCacheOptions!) on FIELD

input XGraphQLCacheOptions {
  ttl: Int!
  key: String!
}

type XGraphQLQuery {
  xGraphQL_items(filter: XGraphQLItemFilter!): [XGraphQLItemInterface!]!
  xGraphQL_categories: [XGraphQLCategory!]!
}

input XGraphQLItemFilter {
  name: XGraphQLItemNameFilter!
  category: String!
}

input XGraphQLItemNameFilter {
  equal: String!
  like: String!
}

type XGraphQLMutation {
  xGraphQL_addShoes(name: String!): XGraphQLShoes!
  xGraphQL_addShirt(name: String!): XGraphQLShirt!
}

type XGraphQLCategory {
  name: String!
}

interface XGraphQLItemInterface {
  id: ID!
  name: String!
  category: XGraphQLCategory!
}

type XGraphQLShoes implements XGraphQLItemInterface {
  id: ID!
  name: String!
  category: XGraphQLCategory!
}

type XGraphQLShirt implements XGraphQLItemInterface {
  id: ID!
  name: String!
  category: XGraphQLCategory!
}

type XGraphQLBelt implements XGraphQLItemInterface {
  id: ID!
  name: String!
  category: XGraphQLCategory!
}
SDL
            ],
        ];
    }
}
