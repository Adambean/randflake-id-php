<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Tests;

use Adambean\RandflakeId\Generator;
use Adambean\RandflakeId\RandflakeId;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase
{
    private static function makeGenerator(): Generator
    {
        return new Generator(
            random_int(0, RandflakeId::MAX_NODE),
            RandflakeId::SILLY_SECRET,
            time(),
            time() + 3600
        );
    }

    public function testGenerate(): void
    {
        $generator = self::makeGenerator();

        // Test raw ID generation
        $idRaw = $generator->generate();
        self::assertNotEmpty($idRaw, "Generated ID string should not be empty.");
        self::assertGreaterThanOrEqual("0", $idRaw, "Generated ID number should be at least 0.");
        self::assertLessThan($generator->getNumMax(), $idRaw, "Generated ID number should be at most `(2 ** ID_BITS) - 1`.");

        // Test ID encryption
        $idEncrypted = $generator->encryptId($idRaw);
        self::assertNotEmpty($idEncrypted, "Encrypted ID string should not be empty.");
        self::assertGreaterThanOrEqual("0", $idEncrypted, "Encrypted ID number should be at least 0.");
        self::assertLessThan($generator->getNumMax(), $idEncrypted, "Encrypted ID number should be at most `(2 ** ID_BITS) - 1`.");

        // Test ID decryption
        $idDecrypted = $generator->decryptId($idEncrypted);
        self::assertNotEmpty($idDecrypted, "Decrypted ID string should not be empty.");
        self::assertGreaterThanOrEqual("0", $idDecrypted, "Decrypted ID number should be at least 0.");
        self::assertLessThan($generator->getNumMax(), $idDecrypted, "Decrypted ID number should be at most `(2 ** ID_BITS) - 1`.");

        // Test that decrypted ID matches the original raw ID
        self::assertSame($idRaw, $idDecrypted, "Decrypted ID string should match the original raw ID string.");

        // Test ID encoding (raw)
        $idRawEncoded = $generator->encodeId($idRaw);
        self::assertNotEmpty($idRawEncoded, "Encoded ID string should not be empty.");

        // Test ID decoding (raw)
        $idRawDecoded = $generator->decodeId($idRawEncoded);
        self::assertNotEmpty($idRawDecoded, "Decoded ID string should not be empty.");
        self::assertSame($idRaw, $idRawDecoded, "Decoded ID string should match the original raw ID string.");

        // Test ID encoding (encrypted)
        $idEncryptedEncoded = $generator->encodeId($idEncrypted);
        self::assertNotEmpty($idEncryptedEncoded, "Encoded ID string should not be empty.");

        // Test ID decoding (encrypted)
        $idEncryptedDecoded = $generator->decodeId($idEncryptedEncoded);
        self::assertNotEmpty($idEncryptedDecoded, "Decoded ID string should not be empty.");
        self::assertSame($idEncrypted, $idEncryptedDecoded, "Decoded ID string should match the original encrypted ID string.");
    }

    public function testAssertions(): void
    {
        $generator = self::makeGenerator();

        // Make an ID in multiple formats
        $idRaw              = $generator->generate(false, false);
        $idRawEncoded       = $generator->encodeId($idRaw);
        $idEncrypted        = $generator->encryptId($idRaw);
        $idEncryptedEncoded = $generator->encodeId($idEncrypted);

        // Test valid ID assertions (automatic encoding detection)
        RandflakeId::assertValidId($idRaw);
        RandflakeId::assertValidId($idRawEncoded);
        RandflakeId::assertValidId($idEncrypted);
        RandflakeId::assertValidId($idEncryptedEncoded);

        // Test valid ID assertions (explicit encoding specification)
        RandflakeId::assertValidId($idRaw, false);
        RandflakeId::assertValidId($idRawEncoded, true);
        RandflakeId::assertValidId($idEncrypted, false);
        RandflakeId::assertValidId($idEncryptedEncoded, true);
    }

    public function testStringIntegers(): void
    {
        $generator = self::makeGenerator();

        // Test integer/string overflow juggling
        foreach ([
            "0"                     => 0,
            "1234567890"            => 1234567890,
            "9223372036854775807"   => 9223372036854775807,
            "9223372036854775808"   => -9223372036854775808,
            "17293822564152854774"  => -1152921509556696842,
            "18446597525811658164"  => -146547897893452,
            "18446744068759701750"  => -4949849866,
            "18446744073709551615"  => -1,
        ] as $numStr => $numInt) {
            // Because PHP implicitly casts array keys that are numeric strings (strings containing valid integer values) to integers
            if (is_int($numStr)) {
                $numStr = strval($numStr);
            }

            $numToString = $generator->intToString($numInt);
            self::assertSame($numStr, $numToString, "intToString should convert int to correct string.");

            $numToInt = $generator->stringToInt($numStr);
            self::assertSame($numInt, $numToInt, "stringToInt should convert string to correct int.");
        }
    }
}
