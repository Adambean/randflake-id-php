<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Exception;

final class RandflakeIdResourceExhaustedException extends RandflakeIdException
{
    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct(
            "" !== $message ? $message : "Resource exhausted, generator can't handle current throughput. Try using multiple Randflake instances.",
            $code,
            $previous
        );
    }
}
