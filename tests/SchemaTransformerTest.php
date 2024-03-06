<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use XGraphQL\DelegateExecution\SchemaExecutionDelegatorInterface;
use XGraphQL\SchemaTransformer\AST\PrefixRootFieldsNameTransformer;
use XGraphQL\SchemaTransformer\SchemaTransformer;
use XGraphQL\SchemaTransformer\TransformerInterface;
use XGraphQL\Utils\SchemaPrinter;

class SchemaTransformerTest extends TestCase
{
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
        $astCached = null;
        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects($this->exactly(2))
            ->method('has')
            ->willReturn(false, true, true);

        $cache
            ->expects($this->exactly(1))
            ->method('set')
            ->willReturnCallback(
                static function (string $key, array $ast) use (&$astCached): bool {
                    $astCached = $ast;

                    return true;
                }
            );

        $cache
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(
                function () use (&$astCached) {
                    return $astCached;
                }
            );

        $schema = BuildSchema::build(
            <<<'SDL'
type Query {
  test: String!
}
SDL
        );

        $transformer = new PrefixRootFieldsNameTransformer('XGraphQL_');
        $schemaTransformed = SchemaTransformer::transform($schema, [$transformer], $cache);

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertNotEquals($schema, $schemaTransformed);
        $this->assertNotNull($astCached);

        $schemaTransformedFromCache = SchemaTransformer::transform($schema, [$transformer], $cache);

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
