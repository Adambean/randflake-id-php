<?php

declare(strict_types=1);

namespace Adambean\RandflakeId;

use Adambean\RandflakeId\Exception\RandflakeIdConsistencyViolationException;
use Adambean\RandflakeId\Exception\RandflakeIdDeadException;
use Adambean\RandflakeId\Exception\RandflakeIdDecodingErrorException;
use Adambean\RandflakeId\Exception\RandflakeIdDecryptionErrorException;
use Adambean\RandflakeId\Exception\RandflakeIdEncodingErrorException;
use Adambean\RandflakeId\Exception\RandflakeIdEncryptionErrorException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidIdOverflowException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidIdUnderflowException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidLeaseException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidNodeException;
use Adambean\RandflakeId\Exception\RandflakeIdInvalidSecretException;
use Adambean\RandflakeId\Exception\RandflakeIdResourceExhaustedException;
use Adambean\RandflakeId\Exception\RandflakeIdUnsupportedIntSizeException;

/**
 * Randflake ID generator.
 *
 * @see https://github.com/gosuda/randflake/blob/main/src/randflake-ts/randflake/src/index.ts
 * Primarily based on the TypeScript implementation.
 *
 * @author Adam Reece <1108717+Adambean@users.noreply.github.com>
 * @license MIT
 *
 * @phpstan-type RandflakeIdDetailsArray = array{
 *  timestamp: numeric-string,
 *  timestampUtc: numeric-string,
 *  nodeId: numeric-string,
 *  sequence: numeric-string,
 * }
 */
final class Generator
{
    /*
     * -------------------------------------------------------------------------
     * Constants
     * -------------------------------------------------------------------------
     */

    /** @var string Character set used in Base32Hex strings. */
    private const B32HEX_CHARS = "0123456789abcdefghijklmnopqrstuv";



    /*
     * -------------------------------------------------------------------------
     * Variables
     * -------------------------------------------------------------------------
     */

    /**
     * @var numeric-string $numMid Middle possible number.
     * Constructor sets this to `2 ** ID_BITS`, do not change!
     */
    private string $numMid = "0";

    /**
     * @var numeric-string $numMidMinus1 Middle possible number minus 1.
     * Constructor sets this to `(2 ** ID_BITS) - 1`, do not change!
     */
    private string $numMidMinus1 = "0";

    /**
     * @var numeric-string $numMax Maximum possible number.
     * Constructor sets this to `(2 ** ID_BITS) / 2`, do not change!
     */
    private string $numMax = "0";

    /**
     * @var numeric-string $numMaxMinus1 Maximum possible number minus 1.
     * Constructor sets this to `((2 ** ID_BITS) / 2) - 1`, do not change!
     */
    private string $numMaxMinus1 = "0";

    /** @var non-negative-int nodeId Node ID. */
    private int $nodeId = 0;

    /** @var string $secret Secret key. */
    private string $secret = "";

    /** @var Sparx64|null $secretBox Secret handler. */
    private ?Sparx64 $secretBox = null;

    /** @var int $leaseStart Lease start timestamp relative to epoch offset. */
    private int $leaseStart = 0;

    /** @var int $leaseEnd Lease end timestamp relative to epoch offset. */
    private int $leaseEnd = 0;

    /** @var non-negative-int $sequence Sequence number. */
    private int $sequence = 0;

    /** @var int $rollover Rollover timestamp. */
    private int $rollover = 0;

    /** @var int|null $timeSource Time source. (Alternative to `time()`.) */
    private ?int $timeSource = null;



    /*
     * -------------------------------------------------------------------------
     * Life cycle functions
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor.
     *
     * @param int $nodeId
     * Node ID. (Between 0 to 131071 inclusive.)
     *
     * @param non-empty-string $secret
     * Secret key. (Must be 16 bytes long.)
     *
     * @param int $leaseStart
     * Lease start timestamp relative to epoch offset. If not provided, the epoch offset will be assumed.
     *
     * @param int $leaseEnd
     * Lease end timestamp relative to epoch offset. If not provided, the maximum timestamp will be assumed.
     *
     * @param int|null $timeSource
     * Optional time source for testing. If not provided, `time()` will be used. (Not required outside of testing.)
     *
     * @throws RandflakeIdInvalidNodeException
     * If the node ID is out of range.
     *
     * @throws RandflakeIdInvalidLeaseException
     * If the lease timestamps are out of range.
     *
     * @throws RandflakeIdDeadException
     * If the lease end timestamp exceeds the maximum timestamp.
     *
     * @throws RandflakeIdInvalidSecretException
     * If the secret is not the right length.
     */
    public function __construct(
        int $nodeId,
        string $secret,
        int $leaseStart = RandflakeId::EPOCH_OFFSET,
        int $leaseEnd = RandflakeId::MAX_TIMESTAMP,
        ?int $timeSource = null
    ) {
        if (PHP_INT_SIZE * 8 < RandflakeId::ID_BITS) {
            throw new RandflakeIdUnsupportedIntSizeException();
        }

        $this->numMax       = bcpow("2", strval(RandflakeId::ID_BITS), 0);
        $this->numMaxMinus1 = bcsub($this->numMax, "1", 0);
        $this->numMid       = bcdiv($this->numMax, "2", 0);
        $this->numMidMinus1 = bcsub($this->numMid, "1", 0);

        if ($nodeId < 0 || $nodeId > RandflakeId::MAX_NODE) {
            throw new RandflakeIdInvalidNodeException();
        }

        if (strlen($secret) !== RandflakeId::SECRET_LENGTH) {
            throw new RandflakeIdInvalidSecretException();
        }

        // Assumption: If the start and end lease times are both zero, set to the maximum timestamp bounds
        if (0 === $leaseStart && 0 === $leaseEnd) {
            $leaseStart = RandflakeId::EPOCH_OFFSET;
            $leaseEnd   = RandflakeId::MAX_TIMESTAMP;
        }

        if ($leaseEnd < $leaseStart || $leaseStart < RandflakeId::EPOCH_OFFSET) {
            throw new RandflakeIdInvalidLeaseException();
        }

        if ($leaseEnd > RandflakeId::MAX_TIMESTAMP) {
            throw new RandflakeIdDeadException();
        }

        $this->nodeId       = $nodeId;
        $this->secret       = $secret;
        $this->secretBox    = new Sparx64($this->secret);
        $this->leaseStart   = $leaseStart;
        $this->leaseEnd     = $leaseEnd;
        $this->sequence     = 0;
        $this->rollover     = $leaseStart;
        $this->timeSource   = $timeSource;
    }



    /*
     * -------------------------------------------------------------------------
     * Static functions
     * -------------------------------------------------------------------------
     */

    /**
     * Generate a random secret.
     * Do not pipe this directly into a new instance of this class. Keep it safe for your configuration.
     *
     * @param bool $excludeSymbols Exclude symbols from the generated secret, use numbers and letters only.
     * Sometimes useful for configuration formats which don't play too nicely with some symbols, however this reduces
     * the overall security of the secret.
     *
     * @return literal-string
     */
    public static function generateSecret(bool $excludeSymbols = false): string
    {
        $characters = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        if (!$excludeSymbols) {
            $characters .= "!@#$%^&*()-_=+[]{}|;:,.<>?/~`";
        }
        $charactersLength = strlen($characters);

        $randomString = "";
        for ($i = 0; $i < RandflakeId::SECRET_LENGTH; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        if ("" === $randomString || strlen($randomString) !== RandflakeId::SECRET_LENGTH) {
            throw new \RuntimeException("Failed to generate secret.");
        }

        return $randomString;
    }



    /*
     * -------------------------------------------------------------------------
     * Helper functions
     * -------------------------------------------------------------------------
     */

    /**
     * Make a new raw Randflake ID (as a numeric string).
     *
     * @return numeric-string
     *
     * @throws RandflakeIdInvalidLeaseException
     * If the current time is outside the lease period.
     *
     * @throws RandflakeIdConsistencyViolationException
     * If the current time is less than the previous rollover time.
     *
     * @throws RandflakeIdResourceExhaustedException
     * If the sequence number exceeds the maximum within the same timestamp.
     */
    private function makeNewRawId(): string
    {
        $now = $this->timeSource ?? time();

        if ($now < $this->leaseStart || $now > $this->leaseEnd) {
            throw new RandflakeIdInvalidLeaseException();
        }

        ++$this->sequence;

        if ($this->sequence > RandflakeId::MAX_SEQUENCE) {
            if ($now > $this->rollover) {
                $this->rollover = $now;
                $this->sequence = 0;
            } else {
                throw new ($now < $this->rollover
                    ? RandflakeIdConsistencyViolationException::class
                    : RandflakeIdResourceExhaustedException::class
                )();
            }
        }

        $id = (($now - RandflakeId::EPOCH_OFFSET) << (RandflakeId::NODE_BITS + RandflakeId::SEQUENCE_BITS))
            | ($this->nodeId << RandflakeId::SEQUENCE_BITS)
            | $this->sequence;

        return $this->intToString($id);
    }

    /**
     * Decode a mathematical Base32Hex string into an unsigned numeric string.
     *
     * @param non-empty-string $idEncoded
     *
     * @return numeric-string
     *
     * @throws RandflakeIdDecodingErrorException
     * If decoding fails or the decoded ID is out of valid range.
     */
    private function decodeBase32HexToNumericString(string $idEncoded): string
    {
        RandflakeId::assertBase32HexStringId($idEncoded);

        $idEncoded = strtolower(trim($idEncoded));
        $idDecodedNum = "0";
        $length = strlen($idEncoded);
        for ($i = 0; $i < $length; $i++) {
            $charCode = ord($idEncoded[$i]);
            if ($charCode >= 48 && $charCode <= 57) {
                $value = $charCode - 48;
            } elseif ($charCode >= 97 && $charCode <= 118) {
                $value = $charCode - 87;
            } else {
                throw new RandflakeIdDecodingErrorException("Invalid base32hex character.");
            }

            $idDecodedNum = bcadd(bcmul($idDecodedNum, "32", 0), strval($value), 0);
        }

        if (bccomp($idDecodedNum, $this->numMaxMinus1, 0) > 0) {
            throw new RandflakeIdDecodingErrorException("Decoded ID is out of valid range.");
        }

        return $idDecodedNum;
    }



    /*
     * -------------------------------------------------------------------------
     * Library functions
     * -------------------------------------------------------------------------
     */

    /**
     * Get the calculated middle number.
     * This will be equal to `2 ** ID_BITS`.
     *
     * @return numeric-string
     */
    public function getNumMid(): string
    {
        return $this->numMid;
    }

    /**
     * Get the calculated middle number minus one.
     * This will be equal to `(2 ** ID_BITS) - 1`.
     *
     * @return numeric-string
     */
    public function getNumMidMinus1(): string
    {
        return $this->numMidMinus1;
    }

    /**
     * Get the calculated maximum number.
     * This will be equal to `(2 ** ID_BITS) / 2`.
     *
     * @return numeric-string
     */
    public function getNumMax(): string
    {
        return $this->numMax;
    }

    /**
     * Get the calculated maximum number minus 1.
     * This will be equal to `((2 ** ID_BITS) / 2) - 1`.
     *
     * @return numeric-string
     */
    public function getNumMaxMinus1(): string
    {
        return $this->numMaxMinus1;
    }

    /**
     * Change the lease period.
     *
     * @param non-negative-int $leaseEnd
     * New lease end timestamp relative to epoch offset.
     * This must be an absolute timestamp, not relative to the current lease end timestamp.
     *
     * @throws RandflakeIdDeadException
     * If the new lease end timestamp exceeds the maximum timestamp.
    *
     * @throws RandflakeIdInvalidLeaseException
     * If the new lease end timestamp is earlier than the current lease end timestamp.
     */
    public function changeLease(int $leaseEnd): void
    {
        if ($leaseEnd > RandflakeId::MAX_TIMESTAMP) {
            throw new RandflakeIdDeadException();
        }

        if ($leaseEnd < $this->leaseEnd) {
            throw new RandflakeIdInvalidLeaseException("Lease end timestamp cannot be shortened.");
        }

        $this->leaseEnd = $leaseEnd;
    }

    /**
     * Get the generator's node ID.
     *
     * @return non-negative-int
     */
    public function getNodeId(): int
    {
        return $this->nodeId;
    }

    /**
     * Get the generator's lease start timestamp.
     */
    public function getLeaseStart(): int
    {
        return $this->leaseStart;
    }

    /**
     * Get the generator's lease end timestamp.
     */
    public function getLeaseEnd(): int
    {
        return $this->leaseEnd;
    }

    /**
     * Get the generator's time source.
     *
     * This is only relevant if a custom time source was provided in the constructor, otherwise it will be null and
     * `time()` is used during ID generation.
     */
    public function getTimeSource(): ?int
    {
        return $this->timeSource;
    }

    /**
     * Check if a numeric string ID is valid.
     *
     * @param numeric-string $id
     *
     * @throws RandflakeIdInvalidIdUnderflowException
     * If the ID is less than 0.
     *
     * @throws RandflakeIdInvalidIdOverflowException
     * If the ID is greater than the maximum possible value.
     */
    public function isNumericStringIdValid(string $id): void
    {
        RandflakeId::assertNumericStringId($id);

        if (bccomp($id, "0", 0) < 0) {
            throw new RandflakeIdInvalidIdUnderflowException("ID is less than 0.");
        }

        if (bccomp($id, $this->numMaxMinus1, 0) > 0) {
            throw new RandflakeIdInvalidIdOverflowException("ID is greater than maximum possible value.");
        }
    }

    /**
     * Check if an encoded string ID is valid.
     *
     * @param non-empty-string $id
     */
    public function isEncodedStringIdValid(string $id): void
    {
        $this->decodeBase32HexToNumericString($id);
    }

    /**
     * Check if an ID is valid.
     *
     * @param numeric-string|non-empty-string $id
     */
    public function isIdValid(string $id): void
    {
        RandflakeId::assertValidId($id);

        ctype_digit($id)
            ? $this->isNumericStringIdValid($id)
            : $this->isEncodedStringIdValid($id)
        ;
    }

    /**
     * Convert an integer formatted ID to a numeric string.
     *
     * If the integer is negative it will be converted to an unsigned numeric string.
     *
     * @return numeric-string
     */
    public function intToString(int|float $id): string
    {
        if (is_float($id)) {
            $id = intval(floor($id));
        }

        return $id >= 0
            ? strval($id)
            : bcadd(strval($id), $this->numMax, 0)
        ;
    }

    /**
     * Convert a string formatted ID to an integer.
     *
     * If the numeric string exceeds the signed limit it will be converted to a signed integer, which may be negative.
     *
     * @param numeric-string $id
     */
    public function stringToInt(string $id): int
    {
        $this->isNumericStringIdValid($id);

        return bccomp($id, $this->numMidMinus1, 0) !== 1
            ? intval($id)
            : intval(bcsub($id, $this->numMax, 0))
        ;
    }

    /**
     * Encrypt a raw ID using the secret key.
     *
     * @param numeric-string $idRaw
     *
     * @return numeric-string
     *
     * @throws RandflakeIdEncryptionErrorException
     * If encryption fails.
     */
    public function encryptId(string $idRaw): string
    {
        $this->isNumericStringIdValid($idRaw);

        $idRawBytes = pack("P", $this->stringToInt($idRaw));

        $idEncrypted = $this->secretBox?->encrypt($idRawBytes);
        if (null === $idEncrypted) {
            throw new RandflakeIdEncryptionErrorException("Failed to encrypt ID.");
        }

        $idEncryptedBytes = unpack("P", $idEncrypted);
        if (!is_array($idEncryptedBytes) || !isset($idEncryptedBytes[1]) || !is_int($idEncryptedBytes[1])) {
            throw new RandflakeIdEncryptionErrorException("Failed to unpack encrypted ID.");
        }

        $idEncryptedNum = $this->intToString($idEncryptedBytes[1]);
        if (bccomp($idEncryptedNum, "0", 0) < 0 || bccomp($idEncryptedNum, $this->numMaxMinus1, 0) > 0) {
            throw new RandflakeIdEncryptionErrorException("Encrypted ID is out of valid range.");
        }

        return $idEncryptedNum;
    }

    /**
     * Decrypt an encrypted ID using the secret key.
     *
     * @param numeric-string $idEncrypted
     *
     * @return numeric-string
     *
     * @throws \InvalidArgumentException
     * If the ID is not specified or not an integer numeric.
     *
     * @throws RandflakeIdDecryptionErrorException
     * If decryption fails.
     */
    public function decryptId(string $idEncrypted): string
    {
        $this->isNumericStringIdValid($idEncrypted);

        $idEncryptedBytes = pack("P", $this->stringToInt($idEncrypted));

        $idDecrypted = $this->secretBox?->decrypt($idEncryptedBytes);
        if (null === $idDecrypted) {
            throw new RandflakeIdDecryptionErrorException("Failed to decrypt ID.");
        }

        $idDecryptedBytes = unpack("P", $idDecrypted);
        if (!is_array($idDecryptedBytes) || !isset($idDecryptedBytes[1]) || !is_int($idDecryptedBytes[1])) {
            throw new RandflakeIdDecryptionErrorException("Failed to unpack decrypted ID.");
        }

        $idDecryptedNum = $this->intToString($idDecryptedBytes[1]);
        if (bccomp($idDecryptedNum, "0", 0) < 0 || bccomp($idDecryptedNum, $this->numMaxMinus1, 0) > 0) {
            throw new RandflakeIdDecryptionErrorException("Decrypted ID is out of valid range.");
        }

        return $idDecryptedNum;
    }

    /**
     * Encode a numeric string ID into a Base32Hex string.
     *
     * @param numeric-string $idPlain
     *
     * @return non-empty-string
     *
     * @throws RandflakeIdEncodingErrorException If encoding fails.
     */
    public function encodeId(string $idPlain): string
    {
        $this->isNumericStringIdValid($idPlain);

        if (bccomp($idPlain, "0", 0) === 0) {
            return "0";
        }

        $idEncoded = "";
        $idRemaining = $idPlain;
        while (bccomp($idRemaining, "0", 0) > 0) {
            $remainder = bcmod($idRemaining, "32", 0);
            $idEncoded = self::B32HEX_CHARS[(int) $remainder] . $idEncoded;
            $idRemaining = bcdiv($idRemaining, "32", 0);
        }

        if ("" === $idEncoded) {
            throw new RandflakeIdEncodingErrorException("Failed to encode ID.");
        }

        $this->isEncodedStringIdValid($idEncoded);

        return $idEncoded;
    }

    /**
     * Decode a Base32Hex encoded string back into a numeric string ID.
     *
     * @param non-empty-string $idEncoded
     *
     * @return numeric-string
     *
     * @throws RandflakeIdDecodingErrorException If decoding fails or the decoded ID is out of valid range.
     */
    public function decodeId(string $idEncoded): string
    {
        return $this->decodeBase32HexToNumericString($idEncoded);
    }

    /**
     * Generate a new Randflake ID.
     *
     * @param bool $encrypted Whether to return an encrypted ID or not.
     * @param bool $encoded Whether to return the ID encoded in Base32Hex or not.
     *
     * @return ($encoded is true ? non-empty-string : numeric-string)
     */
    public function generate(bool $encrypted = false, bool $encoded = false): string
    {
        $idRaw = $this->makeNewRawId();

        $id = $encrypted ? $this->encryptId($idRaw) : $idRaw;

        return $encoded ? $this->encodeId($id) : $id;
    }

    /**
     * Inspect a Randflake ID.
     *
     * @param numeric-string|non-empty-string $id
     * Randflake ID, either as an integer numeric string or a Base32Hex encoded string.
     *
     * @param bool $isEncrypted
     * Whether the ID is encrypted or not.
     *
     * @return RandflakeIdDetailsArray Parsed ID components.
     */
    public function inspect(string $id, bool $isEncrypted): array
    {
        $this->isIdValid($id);

        $idPlain        = !ctype_digit($id) ? $this->decodeId($id) : $id;
        $idRaw          = $isEncrypted ? $this->decryptId($idPlain) : $idPlain;
        $idRawInt       = $this->stringToInt($idRaw);
        $timestamp      = ($idRawInt >> RandflakeId::NODE_BITS + RandflakeId::SEQUENCE_BITS);
        $timestampUtc   = $timestamp + RandflakeId::EPOCH_OFFSET;
        $nodeId         = ($idRawInt >> RandflakeId::SEQUENCE_BITS) & RandflakeId::MAX_NODE;
        $sequence       = $idRawInt & RandflakeId::MAX_SEQUENCE;

        return [
            "timestamp"     => $this->intToString($timestamp),
            "timestampUtc"  => $this->intToString($timestampUtc),
            "nodeId"        => $this->intToString($nodeId),
            "sequence"      => $this->intToString($sequence),
        ];
    }
}
