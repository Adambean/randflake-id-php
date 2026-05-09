<?php

declare(strict_types=1);

namespace Adambean\RandflakeId\Tests;

use Adambean\RandflakeId\Sparx64;
use PHPUnit\Framework\TestCase;

final class Sparx64Test extends TestCase
{
    /**
     * Test that a known plaintext and key produce the expected ciphertext, and
     * that decryption returns the original plaintext.
     */
    public function testEncryptDecrypt(): void
    {
        $key = implode(array_map("chr", [
            0x00, 0x11, 0x22, 0x33, 0x44, 0x55, 0x66, 0x77,
            0x88, 0x99, 0xaa, 0xbb, 0xcc, 0xdd, 0xee, 0xff,
        ]));

        $plainText = implode(array_map("chr", [
            0x01, 0x23, 0x45, 0x67, 0x89, 0xab, 0xcd, 0xef,
        ]));

        $expectedCipherText = implode(array_map("chr", [
            0x2b, 0xbe, 0xf1, 0x52, 0x01, 0xf5, 0x5f, 0x98,
        ]));

        $sbox = new Sparx64($key);

        $encrypted = $sbox->encrypt($plainText);
        self::assertSame($expectedCipherText, $encrypted, "Encryption did not produce expected ciphertext.");
        $decrypted = $sbox->decrypt($encrypted);
        self::assertSame($plainText, $decrypted, "Decryption did not return original plaintext.");
    }

    /**
     * Test that an invalid key length throws an exception.
     */
    public function testInvalidKeyLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $invalidKey = implode(array_map("chr", [0x00, 0x11]));
        new Sparx64($invalidKey);
    }

    /**
     * Test that an invalid source length throws an exception.
     */
    public function testInvalidSourceLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $key            = random_bytes(Sparx64::SECRET_SIZE);
        $sbox           = new Sparx64($key);
        $invalidSource  = implode(array_map("chr", [0x00, 0x11]));

        $encrypted = $sbox->encrypt($invalidSource);
    }
}
