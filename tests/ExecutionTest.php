<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test;

use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use XGraphQL\HttpSchema\HttpExecutionDelegator;
use XGraphQL\HttpSchema\HttpSchemaFactory;
use XGraphQL\SchemaTransformer\AST\PrefixRootFieldsNameTransformer;
use XGraphQL\SchemaTransformer\AST\PrefixTypenameTransformer;
use XGraphQL\SchemaTransformer\AST\RemoveUnusedTypeTransformer;
use XGraphQL\SchemaTransformer\AST\TypesFieldsFilterTransformer;
use XGraphQL\SchemaTransformer\Exception\RuntimeException;
use XGraphQL\SchemaTransformer\SchemaTransformer;

class ExecutionTest extends TestCase
{
    private ?Schema $transformedSchema = null;

    public function setUp(): void
    {
        parent::setUp();

        if (null === $this->transformedSchema) {
            $delegator = new HttpExecutionDelegator('https://countries.trevorblades.com/');

            $this->transformedSchema = SchemaTransformer::transform(
                HttpSchemaFactory::createFromIntrospectionQuery($delegator),
                [
                    new TypesFieldsFilterTransformer(['Query' => ['countries']]),
                    new PrefixRootFieldsNameTransformer('x_graphql_'),
                    new PrefixTypenameTransformer('XGraphQL'),
                    new RemoveUnusedTypeTransformer(),
                ],
            );
        }
    }

    #[DataProvider(methodName: 'executionDataProvider')]
    public function testExecuteTransformedSchema(string $query, array $variables, array $dataExpecting): void
    {
        $result = GraphQL::executeQuery(
            schema: $this->transformedSchema,
            source: $query,
            variableValues: $variables,
        );

        $result->toArray(DebugFlag::RETHROW_UNSAFE_EXCEPTIONS | DebugFlag::RETHROW_INTERNAL_EXCEPTIONS);

        $this->assertNotNull($result->data);
        $this->assertSame($dataExpecting, $result->data);
    }

    public static function executionDataProvider(): array
    {
        return [
            'select transformed field ' => [
                <<<'GQL'
query getCountries($filter: XGraphQLCountryFilterInput!) {
    x_graphql_countries(filter: $filter) {
        __typename
        name
    }
}
GQL,
                [
                    'filter' => [
                        'code' => [
                            'eq' => 'VN'
                        ]
                    ]
                ],
                [
                    'x_graphql_countries' => [
                        [
                            '__typename' => 'XGraphQLCountry',
                            'name' => 'Vietnam'
                        ]
                    ]
                ]
            ],
            'using fragments ' => [
                <<<'GQL'
fragment a on XGraphQLCountry {
  name
  languages {
    ...b
  }
}

fragment b on XGraphQLLanguage {
  name
}

query getCountries($filter: XGraphQLCountryFilterInput!) {
    x_graphql_countries(filter: $filter) {
        ...a
    }
}
GQL,
                [
                    'filter' => [
                        'code' => [
                            'eq' => 'VN'
                        ]
                    ]
                ],
                [
                    'x_graphql_countries' => [
                        [
                            'name' => 'Vietnam',
                            'languages' => [
                                [
                                    'name' => 'Vietnamese'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'using inline fragments ' => [
                <<<'GQL'
query getCountries($filter: XGraphQLCountryFilterInput!) {
    x_graphql_countries(filter: $filter) {
        ... on XGraphQLCountry {
            name
            languages {
              ... on XGraphQLLanguage {
                 name
              }
            }
        }
    }
}
GQL,
                [
                    'filter' => [
                        'code' => [
                            'eq' => 'VN'
                        ]
                    ]
                ],
                [
                    'x_graphql_countries' => [
                        [
                            'name' => 'Vietnam',
                            'languages' => [
                                [
                                    'name' => 'Vietnamese'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
        ];
    }

    public function testExecuteSelectRemovedFields(): void
    {
        $result = GraphQL::executeQuery(
            $this->transformedSchema,
            <<<'SDL'
{
    x_graphql_country(code: "US") {
        __typename
        name
    }
    country(code: "US") {
        __typename
        name
    }
}
SDL
        );

        $this->assertCount(2, $result->errors);
        $this->assertNull($result->data);
    }

    public function testUsing__typenameAliasOnTransformedTypeWillThrowException(): void
    {
        $this->expectException(RuntimeException::class);

        GraphQL::executeQuery(
            $this->transformedSchema,
            <<<'SDL'
query getCountries($filter: XGraphQLCountryFilterInput!) {
    x_graphql_countries(filter: $filter) {
        __typename: name
        name
    }
}
SDL,
            variableValues: [
                'filter' => [
                    'code' => [
                        'eq' => 'VN'
                    ]
                ]
            ]
        )->toArray(DebugFlag::RETHROW_INTERNAL_EXCEPTIONS | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS);
    }
}
