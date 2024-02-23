<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test;

use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use XGraphQL\DelegateExecution\SchemaExecutionDelegatorInterface;
use XGraphQL\HttpSchema\HttpExecutionDelegator;
use XGraphQL\HttpSchema\SchemaFactory;
use XGraphQL\SchemaTransformer\AST\PrefixRootFieldsNameTransformer;
use XGraphQL\SchemaTransformer\SchemaTransformer;
use XGraphQL\SchemaTransformer\TransformerInterface;

class SchemaTransformerTest extends TestCase
{
    public function testConstructor(): void
    {
        $schema = $this->createStub(Schema::class);
        $delegator = $this->createStub(SchemaExecutionDelegatorInterface::class);
        $transformer = $this->createStub(TransformerInterface::class);
        $cache = $this->createStub(CacheInterface::class);

        $instanceCreatedWithSchema = new SchemaTransformer($schema, []);

        $this->assertInstanceOf(SchemaTransformer::class, $instanceCreatedWithSchema);

        $instanceCreatedWithDelegator = new SchemaTransformer($delegator, []);

        $this->assertInstanceOf(SchemaTransformer::class, $instanceCreatedWithDelegator);

        $instanceCreatedWithAllDependencies = new SchemaTransformer($schema, [$transformer], $cache);

        $this->assertInstanceOf(SchemaTransformer::class, $instanceCreatedWithAllDependencies);
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
        $schemaTransformer = new SchemaTransformer($schema, []);
        $schemaTransformed = $schemaTransformer->transform();

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
            ->willReturn(false, true);

        $cache
            ->expects($this->once())
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
        $schemaTransformer = new SchemaTransformer($schema, [$transformer], $cache);
        $schemaTransformed = $schemaTransformer->transform();

        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertNotEquals($schema, $schemaTransformed);
        $this->assertNotNull($astCached);

        $schemaTransformedFromCache = $schemaTransformer->transform();

        $this->assertNotEquals($schemaTransformed, $schemaTransformedFromCache);
    }
}
