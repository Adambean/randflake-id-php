<?php

declare(strict_types=1);

namespace Adambean\RandflakeId;

use Adambean\RandflakeId\Exception\RandflakeIdInvalidIdException;

/**
 * Randflake ID: A distributed, uniform, unpredictable, unique random ID generator.
 *
 * @see https://gosuda.org/randflake
 * PHP port based on Lemon Mint at GoSuda's specification.
 *
 * @author Adam Reece <1108717+Adambean@users.noreply.github.com>
 * @license MIT
 */
final class RandflakeId
{
    /*
     * -------------------------------------------------------------------------
     * Constants
     * -------------------------------------------------------------------------
     */

    /** @var int Unix epoch time offset: Sunday, October 27, 2024 3:33:20 AM UTC */
    public const EPOCH_OFFSET = 1730000000;

    /** @var positive-int 64 bits for entire generated ID. (Unsigned BIGINT.) */
    public const ID_BITS = 64;
    /** @var positive-int 30 bits for timestamp. (Lifetime of 34 years.) */
    public const TIMESTAMP_BITS = 30;
    /** @var positive-int 17 bits for node ID. (Max 131072 nodes.) */
    public const NODE_BITS = 17;
    /** @var positive-int 17 bits for sequence number. (Max 131072 sequences.) */
    public const SEQUENCE_BITS = 17;
    /** @var positive-int 16 bytes for secret length. */
    public const SECRET_LENGTH = 16;

    /** @var positive-int Maximum epoch time. */
    public const MAX_TIMESTAMP = self::EPOCH_OFFSET + (1 << self::TIMESTAMP_BITS) - 1;
    /** @var positive-int Maximum node ID. */
    public const MAX_NODE = (1 << self::NODE_BITS) - 1;
    /** @var positive-int Maximum sequence number. */
    public const MAX_SEQUENCE = (1 << self::SEQUENCE_BITS) - 1;

    /** @var string Regular expression for encoded IDs. */
    public const ENCODED_REGEXP = "/^[0-9a-v]{1,13}$/";

    /** @var string Silly secret string. Only used in examples and tests. NEVER use this in production! */
    public const SILLY_SECRET = "ThisIsNotSecret!";



    /*
     * -------------------------------------------------------------------------
     * Static functions
     * -------------------------------------------------------------------------
     */

    /**
     * Assert that a numerical string is valid that could potentially represent a Randflake ID.
     *
     * @throws RandflakeIdInvalidIdException
     * If the ID is not specified or not an integer numeric.
     */
    public static function assertNumericStringId(string $id): void
    {
        if ("" === ($id = trim($id))) {
            throw new RandflakeIdInvalidIdException("ID not specified.");
        }

        if (!ctype_digit($id)) {
            throw new RandflakeIdInvalidIdException("ID is not an integer numeric.");
        }
    }

    /**
     * Assert that a Base32Hex string is valid that could potentially represent an encoded Randflake ID.
     *
     * @throws RandflakeIdInvalidIdException
     * If the ID is not specified or not a Base32Hex encoded string.
     */
    public static function assertBase32HexStringId(string $id): void
    {
        if ("" === ($id = strtolower(trim($id)))) {
            throw new RandflakeIdInvalidIdException("ID not specified.");
        }

        if (preg_match(RandflakeId::ENCODED_REGEXP, $id) !== 1) {
            throw new RandflakeIdInvalidIdException("ID is not a Base32Hex encoded string.");
        }
    }

    /**
     * Assert that an ID is (potentially) valid.
     * This only performs basic checks. Use `Generator->isIdValid()` for more thorough validation at runtime.
     *
     * @param bool|null $expectEncoded
     * Whether the ID is expected to be encoded, or null to auto-detect using `ctype_digit()`.
     *
     * @see Generator::isIdValid() For more thorough validation at runtime.
     */
    public static function assertValidId(string $id, ?bool $expectEncoded = null): void
    {
        if ("" === ($id = trim($id))) {
            throw new RandflakeIdInvalidIdException("ID not specified.");
        }

        $looksEncoded = !ctype_digit($id);

        if (false === $expectEncoded || (null === $expectEncoded && !$looksEncoded)) {
            self::assertNumericStringId($id);
        }

        if (true === $expectEncoded || (null === $expectEncoded && $looksEncoded)) {
            self::assertBase32HexStringId($id);
        }
    }

    /**
     * Add null padding to a packed ID string to ensure it is the correct length for unpacking.
     *
     * @param non-empty-string $id Packed ID string.
     *
     * @return non-empty-string Packed ID string with null padding prepended.
     *
     * @throws RandflakeIdInvalidIdException
     * If the packed ID is too long.
     */
    public static function addNullPaddingToPackedId(string $id): string
    {
        $lengthExpected = self::ID_BITS / 8;
        $length = strlen($id);

        if ($length > $lengthExpected) {
            throw new RandflakeIdInvalidIdException("Packed ID is too long.");
        }

        return str_pad($id, $lengthExpected, "\0", STR_PAD_LEFT);
    }
}
