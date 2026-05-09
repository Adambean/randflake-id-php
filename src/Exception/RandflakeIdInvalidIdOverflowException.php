<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Exception;

final class RandflakeIdInvalidIdOverflowException extends RandflakeIdException
{
    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct(
            "" !== $message ? $message : "ID is greater than the maximum possible value.",
            $code,
            $previous
        );
    }
}
