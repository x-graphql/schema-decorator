<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\DocumentValidator;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use XGraphQL\DelegateExecution\Execution;
use XGraphQL\DelegateExecution\SchemaExecutionDelegator;
use XGraphQL\DelegateExecution\SchemaExecutionDelegatorInterface;
use XGraphQL\SchemaTransformer\AST\ASTResolver;
use XGraphQL\SchemaTransformer\Execution\ExecutionResolver;
use XGraphQL\Utils\SchemaPrinter;

final readonly class SchemaTransformer
{
    private SchemaExecutionDelegatorInterface $delegator;

    /**
     * @param TransformerInterface[] $transformers
     */
    public function __construct(
        SchemaExecutionDelegatorInterface|Schema $schemaOrDelegator,
        private iterable $transformers,
        private ?CacheInterface $astCache = null,
    ) {
        if ($schemaOrDelegator instanceof Schema) {
            $schemaDelegator = new SchemaExecutionDelegator($schemaOrDelegator);
        } else {
            $schemaDelegator = $schemaOrDelegator;
        }

        $this->delegator = $schemaDelegator;
    }

    /**
     * @throws SerializationError
     * @throws InvalidArgumentException
     * @throws \JsonException
     * @throws SyntaxError
     * @throws Error
     */
    public function transform(bool $force = false): Schema
    {
        if (false === $force && true === $this->astCache?->has(__METHOD__)) {
            $astNormalized = $this->astCache->get(__METHOD__);
            $ast = AST::fromArray($astNormalized);

            \assert($ast instanceof DocumentNode);

            return $this->createSchemaFromAST($ast);
        }

        $sdl = SchemaPrinter::printSchemaExcludeTypeSystemDirectives($this->delegator->getSchema());
        $ast = Parser::parse($sdl, ['noLocation' => true]);
        $astResolver = new ASTResolver($this->transformers);

        $astResolver->resolve($ast);

        DocumentValidator::assertValidSDL($ast);

        $this->astCache?->set(__METHOD__, AST::toArray($ast));

        return $this->createSchemaFromAST($ast);
    }

    private function createSchemaFromAST(DocumentNode $ast): Schema
    {
        $schema = BuildSchema::build($ast, options: ['assumeValidSDL' => true]);
        $executionResolver = new ExecutionResolver($this->delegator, $this->transformers);

        Execution::delegate($schema, $executionResolver);

        return $schema;
    }
}
