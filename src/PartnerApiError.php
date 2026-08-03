<?php

declare(strict_types=1);

namespace Peccancy\Partner;

/** Thrown when the API responds with a non-2xx status (or the request fails). */
class PartnerApiError extends \RuntimeException
{
    public int $statusCode;
    public string $body;

    public function __construct(string $message, int $statusCode, string $body)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->body = $body;
    }
}
