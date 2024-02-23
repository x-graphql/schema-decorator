<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\AST;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;
use XGraphQL\SchemaTransformer\AST\TypesFieldsFilterTransformer;

class TypesFieldsFilterTransformerTest extends TestCase
{
    public function testCanFilterTypeField(): void
    {
        $schema = $this->createStub(SchemaDefinitionNode::class);
        $type = Parser::objectTypeDefinition('type TestType { field: String! field_keep: String! }');
        $fieldShouldRemove = $type->fields[0];
        $fieldShouldKeep = $type->fields[1];
        $transformer = new TypesFieldsFilterTransformer(['TestType' => ['field']]);

        $this->assertInstanceOf(FieldDefinitionNode::class, $fieldShouldRemove);
        $this->assertInstanceOf(FieldDefinitionNode::class, $fieldShouldKeep);
        $this->assertTrue($transformer->filterField($fieldShouldRemove, $type, $schema));
        $this->assertFalse($transformer->filterField($fieldShouldKeep, $type, $schema));
    }

    public function testCanExcludeFilterByTypename(): void
    {
        $schema = $this->createStub(SchemaDefinitionNode::class);
        $type = Parser::objectTypeDefinition('type ExcludeType { exclude_field: String! }');
        $field = $type->fields[0];
        $transformer = new TypesFieldsFilterTransformer(['TestType' => ['field']]);

        $this->assertInstanceOf(FieldDefinitionNode::class, $field);
        $this->assertFalse($transformer->filterField($field, $type, $schema));
    }
}
