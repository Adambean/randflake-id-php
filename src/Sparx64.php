<?php

declare(strict_types=1);

namespace Adambean\RandflakeId;

/**
 * Implementation of the SPARX 64-bit block cipher.
 *
 * Used by Randflake ID generation to make IDs unpredictable, and prevent leaking the creation time, node ID, and ID
 * sequence.
 *
 * @see https://github.com/gosuda/randflake/blob/main/src/randflake-ts/sparx64/src/index.ts
 * Primarily based on the TypeScript implementation.
 *
 * @author Adam Reece <1108717+Adambean@users.noreply.github.com>
 * @license MIT
 */
final class Sparx64
{
    /*
     * -------------------------------------------------------------------------
     * Constants
     * -------------------------------------------------------------------------
     */

    /** @var int Number of steps. */
    public const N_STEPS = 8;

    /** @var int Rounds per step. */
    public const ROUNDS_PER_STEP = 3;

    /** @var int Number of branches. */
    public const N_BRANCHES = 2;

    /** @var int Key size. */
    public const K_SIZE = 4;

    /** @var int Secret size. */
    public const SECRET_SIZE = 16;

    /** @var int Block size. */
    public const BLOCK_SIZE = 8;



    /*
     * -------------------------------------------------------------------------
     * Variables
     * -------------------------------------------------------------------------
     */

    /** @var int[][] $subkeys Subkeys for encryption/decryption. */
    private array $subkeys = [];



    /*
     * -------------------------------------------------------------------------
     * Life-cycle functions
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor.
     *
     * @param string $secret Secret key, must be 16 bytes long.
     */
    public function __construct(string $secret)
    {
        if ("" === $secret || strlen($secret) !== self::SECRET_SIZE) {
            throw new \InvalidArgumentException(sprintf("Secret key must be %d bytes long.", self::SECRET_SIZE));
        }

        $masterKey = [];
        for ($i = 0; $i < 2 * self::K_SIZE; $i++) {
            $masterKey[$i] = (ord($secret[2 * $i]) << 8) | ord($secret[2 * $i + 1]);
        }
        $this->subkeys = $this->keySchedule($masterKey);
    }



    /*
     * -------------------------------------------------------------------------
     * Helper functions
     * -------------------------------------------------------------------------
     */

    /**
     * Rotate left a 16-bit integer.
     *
     * @param int $x Value to rotate.
     * @param int $n Number of bits to rotate.
     */
    private function rotl(int $x, int $n): int
    {
        return ((($x << $n) | ($x >> (16 - $n))) & 0xFFFF);
    }

    /**
     * ARX round function.
     *
     * @param int $l Left half of the branch.
     * @param int $r Right half of the branch.
     *
     * @return array{0: int, 1: int} Each half of the branch.
     */
    private function A(int $l, int $r): array
    {
        $l = $this->rotl($l, 9);
        $l = ($l + $r) & 0xFFFF;
        $r = $this->rotl($r, 2);
        $r ^= $l;

        return [$l, $r];
    }

    /**
     * Inverse ARX round function.
     *
     * @param int $l Left half of the branch.
     * @param int $r Right half of the branch.
     *
     * @return array{0: int, 1: int} Each half of the branch.
     */
    private function AInv(int $l, int $r): array
    {
        $r ^= $l;
        $r = $this->rotl($r, 14);
        $l = ($l - $r) & 0xFFFF;
        $l = $this->rotl($l, 7);

        return [$l, $r];
    }

    /**
     * Linear mixing layer for two branches.
     *
     * @param int[] $x Block data, will be modified in place.
     */
    private function L2(array &$x): void
    {
        $tmp = $this->rotl($x[0] ^ $x[1], 8);
        $x[2] ^= $x[0] ^ $tmp;
        $x[3] ^= $x[1] ^ $tmp;
        [$x[0], $x[2]] = [$x[2], $x[0]];
        [$x[1], $x[3]] = [$x[3], $x[1]];
    }

    /**
     * Inverse linear mixing layer for two branches.
     *
     * @param int[] $x Block data, will be modified in place.
     */
    private function L2Inv(array &$x): void
    {
        [$x[0], $x[2]] = [$x[2], $x[0]];
        [$x[1], $x[3]] = [$x[3], $x[1]];
        $tmp = $this->rotl($x[0] ^ $x[1], 8);
        $x[2] ^= $x[0] ^ $tmp;
        $x[3] ^= $x[1] ^ $tmp;
    }

    /**
     * Key permutation for 64/128 variant.
     *
     * @param int[] $k Key, will be modified in place.
     * @param int   $c Round constant.
     */
    private function KPerm64128(array &$k, int $c): void
    {
        [$k[0], $k[1]] = $this->A($k[0], $k[1]);
        $k[2] = ($k[2] + $k[0]) & 0xFFFF;
        $k[3] = ($k[3] + $k[1]) & 0xFFFF;
        $k[7] = ($k[7] + $c) & 0xFFFF;
        [$tmp0, $tmp1] = [$k[6], $k[7]];
        for ($i = 7; $i >= 2; $i--) {
            $k[$i] = $k[$i - 2];
        }
        $k[0] = $tmp0;
        $k[1] = $tmp1;
    }

    /**
     * Key schedule for 64/128 variant.
     *
     * @param int[] $masterKey Master key, will be modified in place.
     *
     * @return int[][] Subkeys for encryption/decryption.
     */
    private function keySchedule(array &$masterKey): array
    {
        $subkeys = [];
        $total = (self::N_BRANCHES * self::N_STEPS) + 1;
        for ($c = 0; $c < $total; $c++) {
            $subkeys[$c] = array_slice($masterKey, 0, 2 * self::ROUNDS_PER_STEP);
            $this->KPerm64128($masterKey, $c + 1);
        }

        return $subkeys;
    }

    /**
     * Encrypt a block of data using the Sparx64 block cipher.
     *
     * @param int[]   $x Block data, will be modified in place.
     * @param int[][] $k Keys.
     */
    private function sparxEncrypt(array &$x, array $k): void
    {
        for ($s = 0; $s < self::N_STEPS; $s++) {
            for ($b = 0; $b < self::N_BRANCHES; $b++) {
                for ($r = 0; $r < self::ROUNDS_PER_STEP; $r++) {
                    $x[2 * $b] ^= $k[self::N_BRANCHES * $s + $b][2 * $r];
                    $x[2 * $b + 1] ^= $k[self::N_BRANCHES * $s + $b][2 * $r + 1];
                    [$x[2 * $b], $x[2 * $b + 1]] = $this->A($x[2 * $b], $x[2 * $b + 1]);
                }
            }
            $this->L2($x);
        }

        for ($b = 0; $b < self::N_BRANCHES; $b++) {
            $x[2 * $b] ^= $k[self::N_BRANCHES * self::N_STEPS][2 * $b];
            $x[2 * $b + 1] ^= $k[self::N_BRANCHES * self::N_STEPS][2 * $b + 1];
        }
    }

    /**
     * Decrypt a block of data using the Sparx64 block cipher.
     *
     * @param int[]   $x Block data, will be modified in place.
     * @param int[][] $k Keys.
     */
    private function sparxDecrypt(array &$x, array $k): void
    {
        for ($b = 0; $b < self::N_BRANCHES; $b++) {
            $x[2 * $b] ^= $k[self::N_BRANCHES * self::N_STEPS][2 * $b];
            $x[2 * $b + 1] ^= $k[self::N_BRANCHES * self::N_STEPS][2 * $b + 1];
        }

        for ($s = self::N_STEPS - 1; $s >= 0; $s--) {
            $this->L2Inv($x);
            for ($b = 0; $b < self::N_BRANCHES; $b++) {
                for ($r = self::ROUNDS_PER_STEP - 1; $r >= 0; $r--) {
                    [$x[2 * $b], $x[2 * $b + 1]] = $this->AInv($x[2 * $b], $x[2 * $b + 1]);
                    $x[2 * $b] ^= $k[self::N_BRANCHES * $s + $b][2 * $r];
                    $x[2 * $b + 1] ^= $k[self::N_BRANCHES * $s + $b][2 * $r + 1];
                }
            }
        }
    }

    /**
     * Encrypt or decrypt an 8-byte string using the Sparx64 block cipher
     *
     * @param string $source       Source string, must be 8 bytes long.
     * @param bool   $isDecrypting Whether to decrypt (true) or encrypt (false).
     *
     * @return string Modified string, 8 bytes long.
     *
     * @throws \InvalidArgumentException
     * If the source string is not 8 bytes long.
     */
    private function run(string $source, bool $isDecrypting): string
    {
        if ("" === $source || strlen($source) !== self::BLOCK_SIZE) {
            throw new \InvalidArgumentException(sprintf("Source string must be %d bytes long.", self::BLOCK_SIZE));
        }

        $x = array_fill(0, 2 * self::N_BRANCHES, 0);
        for ($i = 0; $i < 2 * self::N_BRANCHES; $i++) {
            $x[$i] = (ord($source[2 * $i]) << 8) | ord($source[2 * $i + 1]);
        }

        !$isDecrypting
            ? $this->sparxEncrypt($x, $this->subkeys)
            : $this->sparxDecrypt($x, $this->subkeys)
        ;

        $result = "";
        for ($i = 0; $i < 2 * self::N_BRANCHES; $i++) {
            $result .= chr($x[$i] >> 8) . chr($x[$i] & 0xFF);
        }

        return $result;
    }



    /*
     * -------------------------------------------------------------------------
     * Library functions
     * -------------------------------------------------------------------------
     */

    /**
     * Encrypt an 8-byte string using the Sparx64 block cipher.
     *
     * @param string $source Source string, must be 8 bytes long.
     *
     * @return string Encrypted string, 8 bytes long.
     *
     * @see `self::run`
     */
    public function encrypt(string $source): string
    {
        return $this->run($source, false);
    }

    /**
     * Decrypt an 8-byte string using the Sparx64 block cipher.
     *
     * @param string $source Source string, must be 8 bytes long.
     *
     * @return string Decrypted string, 8 bytes long.
     *
     * @see `self::run`
     */
    public function decrypt(string $source): string
    {
        return $this->run($source, true);
    }
}
