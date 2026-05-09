<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Exception;

use Adambean\RandflakeId\RandflakeId;

final class RandflakeIdInvalidSecretException extends RandflakeIdException
{
    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct(
            "" !== $message ? $message : sprintf(
                "Secret must be exactly %d bytes long.",
                RandflakeId::SECRET_LENGTH
            ),
            $code,
            $previous
        );
    }
}
