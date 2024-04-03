<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use XGraphQL\Delegate\SchemaDelegator;
use XGraphQL\SchemaCache\SchemaCache;
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
        $delegator = new SchemaDelegator($schema);
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
        $arrayCache = new ArrayAdapter();
        $schemaCache = new SchemaCache(new Psr16Cache($arrayCache));

        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
  test: String!
}
SDL
        );

        $transformer = new PrefixRootFieldsNameTransformer('XGraphQL_');

        $this->assertEmpty($arrayCache->getValues());

        $schemaTransformed = SchemaTransformer::transform($schema, [$transformer], $schemaCache);

        $this->assertInstanceOf(Schema::class, $schemaTransformed);
        $this->assertNotEquals($schema, $schemaTransformed);

        $schemaTransformedFromCache = SchemaTransformer::transform($schema, [$transformer], $schemaCache);

        $this->assertNotEmpty($arrayCache->getValues());
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
