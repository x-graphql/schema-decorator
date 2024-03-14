<?php

declare(strict_types=1);

namespace XGraphQL\SchemaTransformer\Test;

use XGraphQL\DelegateExecution\ErrorsReporterInterface;

class ErrorsReporter implements ErrorsReporterInterface
{
    public array $lastErrors;

    public function reportErrors(array $errors): void
    {
        $this->lastErrors = $errors;
    }
}
