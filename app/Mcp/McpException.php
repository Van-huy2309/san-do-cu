<?php

namespace App\Mcp;

use RuntimeException;

class McpException extends RuntimeException
{
    public function __construct(
        public readonly int $rpcCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
