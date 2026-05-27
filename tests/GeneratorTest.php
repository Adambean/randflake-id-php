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

    /**
     * @return list<array{
     *  secret: non-empty-string,
     *  node_id: non-negative-int,
     *  lease_start: int,
     *  lease_end: int,
     *  timestamp: int,
     *  sequence: non-negative-int,
     *  raw_id: numeric-string,
     *  encrypted_id: numeric-string,
     *  encoded_id: non-empty-string,
     * }>
     */
    private static function officialTestVectors(): array
    {
        return [
            [
                "secret"       => "dffd6021bb2bd5b0af676290809ec3a5",
                "node_id"      => 42,
                "lease_start"  => 1730000000,
                "lease_end"    => 1735000000,
                "timestamp"    => 1733706297,
                "sequence"     => 1,
                "raw_id"       => "63673697622556673",
                "encrypted_id" => "4594531474933654033",
                "encoded_id"   => "3vgoe12ccb8gh",
            ],
            [
                "secret"       => "00000000000000000000000000000000",
                "node_id"      => 0,
                "lease_start"  => 1730000000,
                "lease_end"    => 1730000010,
                "timestamp"    => 1730000001,
                "sequence"     => 0,
                "raw_id"       => "17179869184",
                "encrypted_id" => "2111581968557607991",
                "encoded_id"   => "1qjeojjevu31n",
            ],
            [
                "secret"       => "ffffffffffffffffffffffffffffffff",
                "node_id"      => 131071,
                "lease_start"  => 1730000000,
                "lease_end"    => 1730000010,
                "timestamp"    => 1730000002,
                "sequence"     => 131071,
                "raw_id"       => "51539607551",
                "encrypted_id" => "1072887061578045911",
                "encoded_id"   => "tot934enhmen",
            ],
            [
                "secret"       => "000102030405060708090a0b0c0d0e0f",
                "node_id"      => 1,
                "lease_start"  => 1730000000,
                "lease_end"    => 1730001000,
                "timestamp"    => 1730000123,
                "sequence"     => 1,
                "raw_id"       => "2113124040705",
                "encrypted_id" => "-232447010193727000",
                "encoded_id"   => "fphhelk04q8f8",
            ],
            [
                "secret"       => "0f0e0d0c0b0a09080706050403020100",
                "node_id"      => 131070,
                "lease_start"  => 1730000001,
                "lease_end"    => 1730005000,
                "timestamp"    => 1730004567,
                "sequence"     => 131070,
                "raw_id"       => "78477642301438",
                "encrypted_id" => "-2085243871051999270",
                "encoded_id"   => "e63tpnt9u55uq",
            ],
            [
                "secret"       => "73757065722d7365637265742d6b6579",
                "node_id"      => 7,
                "lease_start"  => 1730500000,
                "lease_end"    => 1732500000,
                "timestamp"    => 1731234567,
                "sequence"     => 42,
                "raw_id"       => "21209699559800874",
                "encrypted_id" => "-2990835006926556165",
                "encoded_id"   => "dcvjbd135dsvr",
            ],
            [
                "secret"       => "00112233445566778899aabbccddeeff",
                "node_id"      => 65535,
                "lease_start"  => 1731000000,
                "lease_end"    => 1739000000,
                "timestamp"    => 1737654321,
                "sequence"     => 65535,
                "raw_id"       => "131500242062213119",
                "encrypted_id" => "8136495406619906497",
                "encoded_id"   => "71ql3eqe2qke1",
            ],
            [
                "secret"       => "ffeeddccbbaa99887766554433221100",
                "node_id"      => 65536,
                "lease_start"  => 1732000000,
                "lease_end"    => 1740000000,
                "timestamp"    => 1738888888,
                "sequence"     => 65536,
                "raw_id"       => "152709941621227520",
                "encrypted_id" => "2668698934995960496",
                "encoded_id"   => "2a28vhb0ebglg",
            ],
            [
                "secret"       => "0123456789abcdeffedcba9876543210",
                "node_id"      => 12345,
                "lease_start"  => 1740000000,
                "lease_end"    => 1748000000,
                "timestamp"    => 1745678901,
                "sequence"     => 12345,
                "raw_id"       => "269361469746982969",
                "encrypted_id" => "-4589356659636265124",
                "encoded_id"   => "c0jqkdrtct7qs",
            ],
            [
                "secret"       => "89abcdef0123456776543210fedcba98",
                "node_id"      => 98765,
                "lease_start"  => 1750000000,
                "lease_end"    => 1758000000,
                "timestamp"    => 1753456789,
                "sequence"     => 98765,
                "raw_id"       => "402984579442115021",
                "encrypted_id" => "7890279631303626821",
                "encoded_id"   => "6qvv7gkl5kk25",
            ],
            [
                "secret"       => "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
                "node_id"      => 2,
                "lease_start"  => 1760000000,
                "lease_end"    => 1765000000,
                "timestamp"    => 1762345678,
                "sequence"     => 2,
                "raw_id"       => "555694516708048898",
                "encrypted_id" => "1271837609463808827",
                "encoded_id"   => "139jpi4u150pr",
            ],
            [
                "secret"       => "55555555555555555555555555555555",
                "node_id"      => 131071,
                "lease_start"  => 1770000000,
                "lease_end"    => 1779000000,
                "timestamp"    => 1777777777,
                "sequence"     => 0,
                "raw_id"       => "820815975942062080",
                "encrypted_id" => "-2472022342516504789",
                "encoded_id"   => "drccsorenj1pb",
            ],
            [
                "secret"       => "31415926535897932384626433832795",
                "node_id"      => 31415,
                "lease_start"  => 1800000000,
                "lease_end"    => 1810000000,
                "timestamp"    => 1803141592,
                "sequence"     => 65358,
                "raw_id"       => "1256562986587193166",
                "encrypted_id" => "-4403670233087647781",
                "encoded_id"   => "c5oo584odinur",
            ],
            [
                "secret"       => "27182818284590452353602874713526",
                "node_id"      => 27182,
                "lease_start"  => 1850000000,
                "lease_end"    => 1860000000,
                "timestamp"    => 1852718281,
                "sequence"     => 84590,
                "raw_id"       => "2108284017628236398",
                "encrypted_id" => "-2703274410196994664",
                "encoded_id"   => "dkv0a7scsrtco",
            ],
            [
                "secret"       => "11235813213455891442333776109871",
                "node_id"      => 1098,
                "lease_start"  => 1900000000,
                "lease_end"    => 1910000000,
                "timestamp"    => 1901123581,
                "sequence"     => 33776,
                "raw_id"       => "2939880736021578736",
                "encrypted_id" => "8857717212368356482",
                "encoded_id"   => "7lr7erve18h42",
            ],
            [
                "secret"       => "fedcba98765432100123456789abcdef",
                "node_id"      => 131071,
                "lease_start"  => 2266870901,
                "lease_end"    => 2266870911,
                "timestamp"    => 2266870911,
                "sequence"     => 131071,
                "raw_id"       => "9223372036854775807",
                "encrypted_id" => "-6720148258825199276",
                "encoded_id"   => "a5f9sodpc78ak",
            ],
        ];
    }

    /**
     * @param numeric-string $id
     *
     * @return numeric-string
     */
    private static function signedVectorIdToUnsigned(string $id): string
    {
        if (!str_starts_with($id, "-")) {
            if (!ctype_digit($id)) {
                self::fail("Positive vector ID must be an unsigned integer string.");
            }

            return $id;
        }

        $unsigned = bcadd($id, bcpow("2", "64", 0), 0);
        if (!ctype_digit($unsigned)) {
            self::fail("Signed vector ID could not be converted to an unsigned integer string.");
        }

        return $unsigned;
    }

    private static function setGeneratorState(Generator $generator, int $sequence, int $rollover): void
    {
        $sequenceProperty = new \ReflectionProperty($generator, "sequence");
        $sequenceProperty->setAccessible(true);
        $sequenceProperty->setValue($generator, $sequence);

        $rolloverProperty = new \ReflectionProperty($generator, "rollover");
        $rolloverProperty->setAccessible(true);
        $rolloverProperty->setValue($generator, $rollover);
    }

    public function testOfficialGoTestVectors(): void
    {
        foreach (self::officialTestVectors() as $vector) {
            $secret = hex2bin($vector["secret"]);
            if (false === $secret || "" === $secret) {
                self::fail("Official test vector secret must decode to a non-empty binary string.");
            }

            $generator = new Generator(
                $vector["node_id"],
                $secret,
                $vector["lease_start"],
                $vector["lease_end"],
                $vector["timestamp"]
            );

            self::setGeneratorState(
                $generator,
                $vector["sequence"] === 0 ? RandflakeId::MAX_SEQUENCE : $vector["sequence"] - 1,
                $vector["sequence"] === 0 ? $vector["timestamp"] - 1 : $vector["lease_start"]
            );

            $encryptedUnsigned = self::signedVectorIdToUnsigned($vector["encrypted_id"]);
            $generatedEncrypted = $generator->generate(true, false);

            self::assertSame($encryptedUnsigned, $generator->encryptId($vector["raw_id"]));
            self::assertSame($encryptedUnsigned, $generatedEncrypted);
            self::assertSame((int) $vector["encrypted_id"], $generator->stringToInt($generatedEncrypted));
            self::assertSame($vector["encoded_id"], $generator->encodeId($generatedEncrypted));
            self::assertSame($encryptedUnsigned, $generator->decodeId($vector["encoded_id"]));
            self::assertSame($vector["raw_id"], $generator->decryptId($generatedEncrypted));

            $details = $generator->inspect($vector["encoded_id"], true);
            self::assertSame(strval($vector["timestamp"] - RandflakeId::EPOCH_OFFSET), $details["timestamp"]);
            self::assertSame(strval($vector["timestamp"]), $details["timestampUtc"]);
            self::assertSame(strval($vector["node_id"]), $details["nodeId"]);
            self::assertSame(strval($vector["sequence"]), $details["sequence"]);
        }
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
        self::addToAssertionCount(8);
    }

    public function testStringIntegers(): void
    {
        $generator = self::makeGenerator();

        // Test integer/string overflow juggling
        foreach ([
            "0"                     => 0,
            "1234567890"            => 1234567890,
            "9223372036854775807"   => 9223372036854775807,
            "9223372036854775808"   => PHP_INT_MIN,
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
