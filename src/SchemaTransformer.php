<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\AST;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\DocumentValidator;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use XGraphQL\DelegateExecution\DelegatedErrorsReporterInterface;
use XGraphQL\DelegateExecution\Execution;
use XGraphQL\DelegateExecution\SchemaExecutionDelegator;
use XGraphQL\DelegateExecution\SchemaExecutionDelegatorInterface;
use XGraphQL\SchemaTransformer\AST\ASTResolver;
use XGraphQL\SchemaTransformer\Execution\ExecutionResolver;
use XGraphQL\Utils\SchemaPrinter;

final readonly class SchemaTransformer
{
    /**
     * @throws SerializationError
     * @throws InvalidArgumentException
     * @throws \JsonException
     * @throws SyntaxError
     * @throws Error
     * @throws \ReflectionException
     */
    public static function transform(
        SchemaExecutionDelegatorInterface|Schema $schemaOrDelegator,
        iterable $transformers,
        CacheInterface $cache = null,
        DelegatedErrorsReporterInterface $errorsReporter = null,
    ): Schema {
        if ($schemaOrDelegator instanceof Schema) {
            $delegator = new SchemaExecutionDelegator($schemaOrDelegator);
        } else {
            $delegator = $schemaOrDelegator;
        }

        $cacheKey = '_x_graphql_transformed_ast';

        if (true === $cache?->has($cacheKey)) {
            $astNormalized = $cache->get($cacheKey);
            $ast = AST::fromArray($astNormalized);
        } else {
            $sdl = SchemaPrinter::printSchemaExcludeTypeSystemDirectives($delegator->getSchema());
            $ast = Parser::parse($sdl, ['noLocation' => true]);
            $astResolver = new ASTResolver($transformers);

            $astResolver->resolve($ast);

            DocumentValidator::assertValidSDL($ast);

            $cache?->set($cacheKey, AST::toArray($ast));
        }

        $schema = BuildSchema::build($ast, options: ['assumeValidSDL' => true]);
        $executionResolver = new ExecutionResolver($delegator, $transformers);

        Execution::delegate($schema, $executionResolver, $errorsReporter);

        return $schema;
    }
}
