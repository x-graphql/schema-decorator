<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test\AST;

use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;
use XGraphQL\SchemaTransformer\AST\NameTransformedDirective;

class NameTransformedDirectiveTest extends TestCase
{
    public function testFindFieldOriginalName(): void
    {
        $ast = Parser::fieldDefinition(
            sprintf('test: String! @%s(original: "originalTest")', NameTransformedDirective::NAME)
        );

        $this->assertEquals('originalTest', NameTransformedDirective::findOriginalName($ast));
    }

    public function testFindTypeOriginalName(): void
    {
        $ast = Parser::unionTypeDefinition(
            sprintf('union TestUnion @%s(original: "OriginalTestUnion") = TestObject', NameTransformedDirective::NAME)
        );

        $this->assertEquals('OriginalTestUnion', NameTransformedDirective::findOriginalName($ast));
    }

    public function testNotFoundOriginalName(): void
    {
        $ast = Parser::fieldDefinition('test: String!');

        $this->assertNull(NameTransformedDirective::findOriginalName($ast));
    }
}
