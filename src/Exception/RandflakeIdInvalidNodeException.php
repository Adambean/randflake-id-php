<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Exception;

use Adambean\RandflakeId\RandflakeId;

final class RandflakeIdInvalidNodeException extends RandflakeIdException
{
    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct(
            "" !== $message ? $message : sprintf(
                "Node ID must be between 0 and %d inclusive.",
                RandflakeId::MAX_NODE
            ),
            $code,
            $previous
        );
    }
}
