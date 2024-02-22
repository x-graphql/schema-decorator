<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\AST;

use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;
use XGraphQL\SchemaTransformer\AST\PrefixTypenameTransformer;

class PrefixTypenameTransformerTest extends TestCase
{
    public function testCanPrefixTypename(): void
    {
        $transformer = new PrefixTypenameTransformer('prefix_');
        $type = Parser::objectTypeDefinition('type Query { test: String! }');
        $schema = $this->createStub(SchemaDefinitionNode::class);

        $typenameTransformed = $transformer->transformTypename('Query', $type, $schema);

        $this->assertEquals('prefix_Query', $typenameTransformed);
    }

    public function testCanSkipPrefixTypename(): void
    {
        $transformer = new PrefixTypenameTransformer('prefix_', ['Query']);
        $type = Parser::objectTypeDefinition('type Query { test: String! }');
        $schema = $this->createStub(SchemaDefinitionNode::class);

        $typenameTransformed = $transformer->transformTypename('Query', $type, $schema);

        $this->assertEquals('Query', $typenameTransformed);
    }
}
