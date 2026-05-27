<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Exception;

final class RandflakeIdUnsupportedIntSizeException extends RandflakeIdException
{
    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct(
            "" !== $message ? $message : "Unsupported integer size. Randflake ID generator requires 64-bit integer support.",
            $code,
            $previous
        );
    }
}
