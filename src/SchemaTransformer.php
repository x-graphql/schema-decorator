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
use XGraphQL\SchemaTransformer\Execution\ExecutionDelegator;
use XGraphQL\Utils\SchemaPrinter;

final readonly class SchemaTransformer
{
    public const CACHE_KEY = '_x_graphql_ast_transformed_schema';

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

        if (!$cache?->has(self::CACHE_KEY)) {
            $sdl = SchemaPrinter::printSchemaExcludeTypeSystemDirectives($delegator->getSchema());
            $ast = Parser::parse($sdl, ['noLocation' => true]);
            $astResolver = new ASTResolver($transformers);

            $astResolver->resolve($ast);

            DocumentValidator::assertValidSDL($ast);

            $cache?->set(self::CACHE_KEY, AST::toArray($ast));
        } else {
            $astNormalized = $cache->get(self::CACHE_KEY);
            $ast = AST::fromArray($astNormalized);
        }

        $schema = BuildSchema::build($ast, options: ['assumeValidSDL' => true]);
        $executionResolver = new ExecutionDelegator($delegator, $transformers);

        Execution::delegate($schema, $executionResolver, $errorsReporter);

        return $schema;
    }
}
