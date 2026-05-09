<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Exception;

final class RandflakeIdInvalidIdUnderflowException extends RandflakeIdException
{
    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct(
            "" !== $message ? $message : "ID is less than 0.",
            $code,
            $previous
        );
    }
}
