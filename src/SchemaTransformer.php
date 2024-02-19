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
use GraphQL\Utils\SchemaPrinter;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use XGraphQL\DelegateExecution\Execution;
use XGraphQL\DelegateExecution\SchemaExecutionDelegator;
use XGraphQL\DelegateExecution\SchemaExecutionDelegatorInterface;
use XGraphQL\SchemaTransformer\AST\ASTResolver;

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
            $this->delegator = new SchemaExecutionDelegator($schemaOrDelegator);
        } else {
            $this->delegator = $schemaOrDelegator;
        }
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

        $sdl = SchemaPrinter::doPrint($this->delegator->getSchema());
        $ast = Parser::parse($sdl, ['noLocation' => true]);
        $resolver = new ASTResolver($this->transformers);

        $resolver->resolve($ast);

        $this->astCache?->set(__METHOD__, AST::toArray($ast));

        return $this->createSchemaFromAST($ast);
    }

    private function createSchemaFromAST(DocumentNode $ast): Schema
    {
        $schema = BuildSchema::build($ast, options: ['assumeValidSDL' => true]);
        $delegator = new TransformExecutionDelegator($this->delegator, $this->transformers);

        Execution::delegate($schema, $delegator);

        return $schema;
    }
}
