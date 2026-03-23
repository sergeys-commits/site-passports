<?php

namespace App\Exceptions;

use RuntimeException;

class DeploymentScriptException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $exitCode = 1,
        public readonly ?int $deploymentRunId = null,
    ) {
        parent::__construct($message);
    }
}
