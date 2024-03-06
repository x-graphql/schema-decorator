<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use XGraphQL\DelegateExecution\SchemaExecutionDelegator;
use XGraphQL\SchemaTransformer\AST\PrefixRootFieldsNameTransformer;
use XGraphQL\SchemaTransformer\SchemaTransformer;
use XGraphQL\Utils\SchemaPrinter;

class SchemaTransformerTest extends TestCase
{
    public function testCreateTransformedSchemaWithSchemaDelegator(): void
    {
        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
  test: String!
}
SDL
        );
        $delegator = new SchemaExecutionDelegator($schema);
        $schemaTransformed = SchemaTransformer::transform($delegator, []);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertNotEquals($schema, $schemaTransformed);
    }

    public function testCreateTransformedSchemaWithoutCache(): void
    {
        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
  test: String!
}
SDL
        );
        $schemaTransformed = SchemaTransformer::transform($schema, []);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertNotEquals($schema, $schemaTransformed);
    }

    public function testCreateTransformedSchemaCache(): void
    {
        $cache = new ArrayAdapter();
        $psr16Cache = new Psr16Cache($cache);

        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
  test: String!
}
SDL
        );

        $transformer = new PrefixRootFieldsNameTransformer('XGraphQL_');

        $this->assertFalse($psr16Cache->has('_x_graphql_transformed_ast'));

        $schemaTransformed = SchemaTransformer::transform($schema, [$transformer], $psr16Cache);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertNotEquals($schema, $schemaTransformed);

        $schemaTransformedFromCache = SchemaTransformer::transform($schema, [$transformer], $psr16Cache);

        $this->assertTrue($psr16Cache->has('_x_graphql_transformed_ast'));
        $this->assertNotEquals($schemaTransformed, $schemaTransformedFromCache);

        $expectingSDL = <<<'SDL'
directive @nameTransformed(original: String!) on INTERFACE | OBJECT | INPUT_OBJECT | FIELD_DEFINITION | INPUT_FIELD_DEFINITION | SCALAR | ENUM | UNION

type Query {
  XGraphQL_test: String!
}

SDL;
        $this->assertEquals($expectingSDL, SchemaPrinter::doPrint($schemaTransformed));
        $this->assertEquals($expectingSDL, SchemaPrinter::doPrint($schemaTransformedFromCache));
    }
}
