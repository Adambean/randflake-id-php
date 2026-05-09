<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Tests\Exception;

use Adambean\RandflakeId\Exception\RandflakeIdConsistencyViolationException;
use Adambean\RandflakeId\Exception\RandflakeIdDeadException;
use Adambean\RandflakeId\Exception\RandflakeIdDecodingErrorException;
use Adambean\RandflakeId\Exception\RandflakeIdDecryptionErrorException;
use Adambean\RandflakeId\Exception\RandflakeIdEncodingErrorException;
use Adambean\RandflakeId\Exception\RandflakeIdEncryptionErrorException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidIdException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidIdOverflowException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidIdUnderflowException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidLeaseException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidNodeException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidSecretException;
use Adambean\RandflakeId\Exception\RandflakeIdResourceExhaustedException;
use Adambean\RandflakeId\Exception\RandflakeIdUnsupportedIntSizeException;
use Adambean\RandflakeId\RandflakeId;
use PHPUnit\Framework\TestCase;

final class RandflakeIdExceptionTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<\RuntimeException>, 1: string}>
     */
    public static function defaultMessageProvider(): array
    {
        return [
            "dead" => [
                RandflakeIdDeadException::class,
                "Randflake ID generator is dead after 34 years. No more IDs can be generated.",
            ],
            "invalid_secret" => [
                RandflakeIdInvalidSecretException::class,
                sprintf(
                    "Secret must be exactly %d bytes long.",
                    RandflakeId::SECRET_LENGTH
                ),
            ],
            "invalid_lease" => [
                RandflakeIdInvalidLeaseException::class,
                "Lease has expired or not started yet.",
            ],
            "invalid_node" => [
                RandflakeIdInvalidNodeException::class,
                sprintf(
                    "Node ID must be between 0 and %d inclusive.",
                    RandflakeId::MAX_NODE
                ),
            ],
            "resource_exhausted" => [
                RandflakeIdResourceExhaustedException::class,
                "Resource exhausted, generator can't handle current throughput. Try using multiple Randflake instances.",
            ],
            "consistency_violation" => [
                RandflakeIdConsistencyViolationException::class,
                "Timestamp consistency violation. The current time is less than the previous time.",
            ],
            "unsupported_int_size" => [
                RandflakeIdUnsupportedIntSizeException::class,
                "Unsupported integer size. Randflake ID generator requires 64-bit integer support.",
            ],
            "encryption_error" => [
                RandflakeIdEncryptionErrorException::class,
                "Failed to encrypt ID.",
            ],
            "decryption_error" => [
                RandflakeIdDecryptionErrorException::class,
                "Failed to decrypt ID.",
            ],
            "encoding_error" => [
                RandflakeIdEncodingErrorException::class,
                "Failed to encode ID.",
            ],
            "decoding_error" => [
                RandflakeIdDecodingErrorException::class,
                "Failed to decode ID.",
            ],
            "invalid_id" => [
                RandflakeIdInvalidIdException::class,
                "ID is invalid.",
            ],
            "invalid_id_underflow" => [
                RandflakeIdInvalidIdUnderflowException::class,
                "ID is less than 0.",
            ],
            "invalid_id_overflow" => [
                RandflakeIdInvalidIdOverflowException::class,
                "ID is greater than maximum possible value.",
            ],
        ];
    }

    /**
     * @dataProvider defaultMessageProvider
     * @param class-string<\RuntimeException> $className
     */
    public function testDefaultMessage(string $className, string $expectedMessage): void
    {
        $exception = new $className();

        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /**
     * @dataProvider defaultMessageProvider
     * @param class-string<\RuntimeException> $className
     */
    public function testCustomMessageOverridesDefault(string $className): void
    {
        $message = "Custom message.";
        $exception = new $className($message);

        self::assertSame($message, $exception->getMessage());
    }
}
