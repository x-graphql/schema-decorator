<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\AST;

use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;
use XGraphQL\SchemaTransformer\AST\TypeFilterTransformer;
use XGraphQL\SchemaTransformer\Exception\InvalidArgumentException;

class TypeFilterTransformerTest extends TestCase
{
    public function testConstructor(): void
    {
        $instance1 = new TypeFilterTransformer(['A']);
        $instance2 = new TypeFilterTransformer(['A'], ['B']);
        $instance3 = new TypeFilterTransformer(null, ['B']);

        $this->assertInstanceOf(TypeFilterTransformer::class, $instance1);
        $this->assertInstanceOf(TypeFilterTransformer::class, $instance2);
        $this->assertInstanceOf(TypeFilterTransformer::class, $instance3);

        $this->expectException(InvalidArgumentException::class);

        $instance4 = new TypeFilterTransformer();
    }

    public function testExcludesFilterTypes(): void
    {
        $schema = $this->createStub(SchemaDefinitionNode::class);
        $transformer = new TypeFilterTransformer(excludes: ['A', 'B', 'C']);


        $this->assertFalse($transformer->filterType(Parser::objectTypeDefinition('type A'), $schema));
        $this->assertFalse($transformer->filterType(Parser::objectTypeDefinition('type B'), $schema));
        $this->assertFalse($transformer->filterType(Parser::objectTypeDefinition('type C'), $schema));
        $this->assertTrue($transformer->filterType(Parser::objectTypeDefinition('type D'), $schema));
    }

    public function testOnlyFilterTypes(): void
    {
        $schema = $this->createStub(SchemaDefinitionNode::class);
        $transformer = new TypeFilterTransformer(only: ['A', 'B', 'C']);


        $this->assertTrue($transformer->filterType(Parser::objectTypeDefinition('type A'), $schema));
        $this->assertTrue($transformer->filterType(Parser::objectTypeDefinition('type B'), $schema));
        $this->assertTrue($transformer->filterType(Parser::objectTypeDefinition('type C'), $schema));
        $this->assertFalse($transformer->filterType(Parser::objectTypeDefinition('type D'), $schema));
    }

    public function testOnlyPrecedenceFilter(): void
    {
        $schema = $this->createStub(SchemaDefinitionNode::class);
        $transformer = new TypeFilterTransformer(excludes: ['A'], only: ['A', 'B', 'C']);

        $this->assertTrue($transformer->filterType(Parser::objectTypeDefinition('type A'), $schema));
    }
}
