<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\GraphQL;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\SelectionNode;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use XGraphQL\HttpSchema\HttpDelegator;
use XGraphQL\HttpSchema\HttpSchemaFactory;
use XGraphQL\SchemaTransformer\AST\PrefixRootFieldsNameTransformer;
use XGraphQL\SchemaTransformer\AST\PrefixTypenameTransformer;
use XGraphQL\SchemaTransformer\AST\RemoveUnusedTypeTransformer;
use XGraphQL\SchemaTransformer\AST\TypesFieldsFilterTransformer;
use XGraphQL\SchemaTransformer\Exception\RuntimeException;
use XGraphQL\SchemaTransformer\Execution\RemoveUnusedVariablesTransformer;
use XGraphQL\SchemaTransformer\Execution\SelectionTransformerInterface;
use XGraphQL\SchemaTransformer\Execution\TransformContext;
use XGraphQL\SchemaTransformer\SchemaTransformer;

class ExecutionTest extends TestCase
{
    private ?Schema $transformedSchema = null;

    private ?ErrorsReporter $errorsReporter = null;

    public function setUp(): void
    {
        parent::setUp();

        if (null === $this->transformedSchema) {
            $delegator = new HttpDelegator('https://countries.trevorblades.com/');

            $this->errorsReporter = new ErrorsReporter();
            $this->transformedSchema = SchemaTransformer::transform(
                HttpSchemaFactory::createFromIntrospectionQuery($delegator),
                [
                    new TypesFieldsFilterTransformer(['Query' => ['countries', 'continents']]),
                    new PrefixRootFieldsNameTransformer('x_graphql_'),
                    new PrefixTypenameTransformer('XGraphQL'),
                    new RemoveUnusedTypeTransformer(),
                    new RemoveUnusedVariablesTransformer(),
                    new class() implements SelectionTransformerInterface {
                        public function transformSelection(ObjectType|InterfaceType|UnionType $type, SelectionNode $selection, TransformContext $context): void
                        {
                            if (
                                $type->name() === 'XGraphQLQuery'
                                && $selection instanceof FieldNode
                                && $selection->name->value === 'x_graphql_continents'
                            ) {
                                $selection->arguments = new NodeList([]); /// remove filter
                            }
                        }
                    }
                ],
                errorsReporter: $this->errorsReporter,
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
            'auto remove unused variables' => [
                <<<'GQL'
query getContinents($willBeRemove: XGraphQLContinentFilterInput!) {
    x_graphql_continents(filter: $willBeRemove) {
        name
    }
}
GQL,
                [
                    'willBeRemove' => [
                        'code' => [
                            'eq' => 'AS'
                        ]
                    ],
                ],
                [
                    'x_graphql_continents' => [
                        [
                            'name' => 'Africa',
                        ],
                        [
                            'name' => 'Antarctica',
                        ],
                        [
                            'name' => 'Asia',
                        ],
                        [
                            'name' => 'Europe',
                        ],
                        [
                            'name' => 'North America',
                        ],
                        [
                            'name' => 'Oceania',
                        ],
                        [
                            'name' => 'South America',
                        ]
                    ]
                ]
            ]
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
        )->toArray();

        $this->assertCount(1, $this->errorsReporter->lastErrors);

        $lastError = $this->errorsReporter->lastErrors[0];

        $this->assertInstanceOf(Error::class, $lastError);
        $this->assertEquals('Error during delegate execution', $lastError->getMessage());
        $this->assertInstanceOf(RuntimeException::class, $lastError->getPrevious());
        $this->assertStringContainsString('__typename', $lastError->getPrevious()->getMessage());
    }
}
