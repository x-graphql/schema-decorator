<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\AST;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;
use XGraphQL\SchemaTransformer\AST\PrefixRootFieldsNameTransformer;

class PrefixRootFieldsNameTransformerTest extends TestCase
{
    public function testCanPrefixRootFieldsName(): void
    {
        $transformer = new PrefixRootFieldsNameTransformer('prefix_');
        $schemaAst = Parser::schemaDefinition('schema { query: Query }');
        $typeAst = Parser::objectTypeDefinition('type Query { test: String! }');
        $fieldAst = $typeAst->fields[0];

        $this->assertInstanceOf(FieldDefinitionNode::class, $fieldAst);

        $fieldNameTransformed = $transformer->transformFieldName('test', $fieldAst, $typeAst, $schemaAst);

        $this->assertEquals('prefix_test', $fieldNameTransformed);
    }

    public function testExcludePrefixNormalFieldsName(): void
    {
        $transformer = new PrefixRootFieldsNameTransformer('prefix_');
        $schemaAst = Parser::schemaDefinition('schema { query: Query }');
        $typeAst = Parser::objectTypeDefinition('type ObjectShouldExcludePrefixFieldName { test: String! }');
        $fieldAst = $typeAst->fields[0];

        $this->assertInstanceOf(FieldDefinitionNode::class, $fieldAst);

        $fieldNameTransformed = $transformer->transformFieldName('test', $fieldAst, $typeAst, $schemaAst);

        $this->assertEquals('test', $fieldNameTransformed);
    }
}
