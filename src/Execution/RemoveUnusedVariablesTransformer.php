<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Execution;

use GraphQL\Language\AST\VariableDefinitionNode;
use XGraphQL\Utils\Variable;

final readonly class RemoveUnusedVariablesTransformer implements PreExecutionTransformerInterface
{
    public function preExecute(TransformContext $context): void
    {
        $variablesUsing = array_fill_keys($this->getVariablesUsing($context), true);

        foreach ($context->variableValues as $name => $value) {
            if (!array_key_exists($name, $variablesUsing)) {
                unset($context->variableValues[$name]);
            }
        }

        $variableDefinitions = $context->operation->variableDefinitions;

        foreach ($variableDefinitions as $pos => $definition) {
            /** @var VariableDefinitionNode $definition */

            if (!array_key_exists($definition->variable->name->value, $variablesUsing)) {
                unset($variableDefinitions[$pos]);
            }
        }

        $variableDefinitions->reindex();
    }

    private function getVariablesUsing(TransformContext $context): array
    {
        $variables = array_merge(
            Variable::getVariablesInOperation($context->operation),
            Variable::getVariablesInFragments($context->fragments),
        );

        return array_unique($variables);
    }
}
