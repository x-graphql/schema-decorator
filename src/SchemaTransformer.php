<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer;

use GraphQL\Error\Error;
use GraphQL\Error\SerializationError;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQL\Utils\BuildSchema;
use GraphQL\Validator\DocumentValidator;
use Psr\SimpleCache\InvalidArgumentException;
use XGraphQL\Delegate\SchemaDelegator;
use XGraphQL\Delegate\SchemaDelegatorInterface;
use XGraphQL\DelegateExecution\ErrorsReporterInterface;
use XGraphQL\DelegateExecution\Execution;
use XGraphQL\SchemaCache\SchemaCache;
use XGraphQL\SchemaTransformer\AST\ASTResolver;
use XGraphQL\SchemaTransformer\Execution\ExecutionDelegator;
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
        SchemaDelegatorInterface|Schema $schemaOrDelegator,
        iterable $transformers,
        SchemaCache $cache = null,
        ErrorsReporterInterface $errorsReporter = null,
    ): Schema {
        if ($schemaOrDelegator instanceof Schema) {
            $delegator = new SchemaDelegator($schemaOrDelegator);
        } else {
            $delegator = $schemaOrDelegator;
        }

        $transformedSchema = $cache?->load();

        if (null === $transformedSchema) {
            $sdl = SchemaPrinter::printSchemaExcludeTypeSystemDirectives($delegator->getSchema());
            $ast = Parser::parse($sdl, ['noLocation' => true]);
            $astResolver = new ASTResolver($transformers);

            $astResolver->resolve($ast);

            DocumentValidator::assertValidSDL($ast);

            $transformedSchema = BuildSchema::build($ast, options: ['assumeValidSDL' => true]);

            $cache?->save($transformedSchema);
        }

        $executionResolver = new ExecutionDelegator($delegator, $transformers);

        return Execution::delegate($transformedSchema, $executionResolver, $errorsReporter);
    }
}
