# Randflake ID

A distributed, uniform, unpredictable, unique random ID generator: <https://gosuda.org/randflake>

This is a PHP port based on lemon-mint at GoSuda's specification, primarily based on the TypeScript implementation: <https://github.com/gosuda/randflake>

This repository provides a library to:

- Generate a secret key.
- Generate IDs.
- Encrypt and decrypt existing IDs.
- Encode and decode existing IDs to and from Base32Hex.
- Inspect existing IDs to retrieve their timestamp, node ID, and sequence.

Includes a port of a library for the [SPARX](https://www.research.ed.ac.uk/en/publications/sparx-a-family-of-arx-based-lightweight-block-ciphers-provably-se/) 64-bit block cipher, used to encrypt raw ID numbers into unpredictable ID numbers.

**⚠️ This library is currently a work in progress, and should not be used in a production environment until it has undergone peer review.**

You may not need this library directly in your project:

- For a [Symfony](https://symfony.com/) project with Doctrine ORM/DBAL integration, see my [Symfony bundle](https://github.com/adambean/randflake-id-bundle).

## Is Randflake ID for my project?

That's for you to decide. A short description of it:

> Inspired by Snowflake ID, works well in a distributed system, unpredictable, unique random ID generator, timestamp resolution of 1000ms, works on more distributed machines 131072 (2^17) than snowflake's 1024 (2^10).

There is a good comparison of ideas for unique IDs here: <https://adileo.github.io/awesome-identifiers/>

Randflake ID seems to strike a good balance of a large ID pool, wide distribution, encryption, unpredictability, naturally lexicographic, cursor-based pagination, with 34 years of life.

## Requirements

- PHP† 8.1 or later
- Composer

† **This library requires a 64-bit build of PHP to function.** Check your `PHP_INT_SIZE`, which **must** be at least `8`:

```sh
php -r 'echo PHP_INT_SIZE;'
```

## Installation

Add a Composer package as is usual for PHP projects:

```sh
composer require adambean/randflake-id
```

## Usage

First, generate yourself a 16-byte secret string if you don't have one yet:

```php
<?php

declare(strict_types=1);

use Adambean\RandflakeId\Generator;

// Create a secret string for encryption
$secret = Generator::generateSecret();

// Create a secret without symbols if you prefer (digits and mixed case letters only)
$secret = Generator::generateSecret(true);
```

The secret string must be ***exactly*** 16 bytes. Be careful when using Unicode characters consuming more than 1 byte each. The secret generator will stick to single-byte ASCII characters returning a 16 character string.

Keep your secret string safe for reuse later. All nodes generating IDs will need to use the same secret string.

Now you can start generating IDs. The `$nodeId` is an integer between 0 and 131071 (inclusive):

```php
<?php

declare(strict_types=1);

use Adambean\RandflakeId\Generator;

// Create an instance of the generator library
$generator = new Generator($nodeId, $secret);

// Optionally create an instance of the generator library with a limited node lease time (e.g. for 1 day)
$generator = new Generator($nodeId, $secret, time(), time() + 86400);

// Generate an ID
$id = $generator->generate();

// Generate an encrypted ID
$idEncrypted = $generator->generate(true);

// Generate a non-encrypted but encoded ID
$idEncoded = $generator->generate(false, true);

// Generate an encrypted and encoded ID
$idEncryptedAndEncoded = $generator->generate(true, true);

// Encrypt an existing raw ID
$idEncrypted = $generator->encryptId($id);

// Decrypt an existing encrypted ID
$id = $generator->decryptId($idEncrypted);

// Encode an integer string ID to Base32Hex
$idEncoded = $generator->encodeId($id);

// Decode a Base32Hex encoded ID back to an integer string
$id = $generator->decodeId($idEncoded);

// Get the timestamp, node ID, and sequence of an existing ID
$idDetails = $generator->inspect($id); // If it was raw
$idDetails = $generator->inspect($idEncrypted, true); // If it was encrypted

// Assert if an ID is valid (encoded or not)
$generator->isIdValid($id);
```

For PHPStan or other static analysis tools you can import the `RandflakeIdDetailsArray` pseudo-type for the return type of `inspect()`:

```php
/**
 * @phpstan-import-type RandflakeIdDetailsArray from Generator
 */
```

## Nodes

A node is a unique instance of your application that will be generating IDs. The node ID is simply an integer between 0 and 131071 (inclusive).

It is ***critical*** that you keep track of your application node IDs in a registry of your choosing to ensure they cannot possibly generate conflicting IDs. A flat spreadsheet/table would be sufficient basic way to achieve this, providing it keeps track of your nodes, their assigned ID, and its lease times.

### Temporary nodes

If your application will have temporary nodes that will only be generating IDs for a limited time it is possible to create a generator instance with a limited lease time. This will cause the generator to throw an exception preventing IDs being generated if this node attempts to generate an ID prior to or after its lease window. After the node's lease expiration you can allocate the node ID to a new node with a new lease time, though you should still keep track of the previous node's lease times in your registry.

It is possible to extend the lease time of a node process at runtime, but not shorten it. The new lease time must be relative to the absolute Randflake ID epoch, not relative to the current lease end time.

Extending the lease time of a node process during runtime:

```php
$generator->changeLease($generator->getLeaseEnd() + 86400); // Extend the existing lease by 1 day
```

## ID generation and handling

Randflake IDs are unsigned 64-bit integers, however due to PHP not supporting unsigned number types, this library always handles them as numeric strings and expects your application to do the same. This is essential to avoid numbers exceeding `(2 ** 63) - 1` implicitly becoming floats. Even if they are within the supported signed integer range they, will still be handled as numeric strings.

Internally the [BCMath](https://www.php.net/manual/en/book.bc.php) functions handle mathematical operations on these numeric strings (with 0 scale).

## ID persistence in database engines

### Column type

When IDs are stored in a database you should store them as an unsigned 64-bit integer whenever possible to achieve maximum performance and compatibility.

It is possible to also store IDs as a signed 64-bit integer as not all database engines support unsigned values, though may be less efficient for sorting and cursor-based navigation, and will require your application to handle the conversion between a numeric string and integer, as well as the conversion between signed and unsigned integers. Signed values will also become confusing for human reading in browsing, as negative values with a later creation time will appear before positive values with an earlier creation times when sorted.

- MySQL/MariaDB: `BIGINT UNSIGNED`
- PostgreSQL†: `BIGINT`
- Oracle: `NUMBER(20)`
- Microsoft SQL Server†: `BIGINT`
- SQLite†: `INTEGER`

† These databases do not have unsigned integer types, therefore IDs will be stored as signed integers. You could alternatively:

- Store as a 20-character string: This will make them human readable and guaranteed to be sorted correctly by creation time, however text is less efficient for sorting and cursor-based navigation.
- Store as 8-byte binary: This will be very efficient for sorting and cursor-based navigation, however they will not be human readable and require custom conversion to and from a numeric string in your application.

As this library handles IDs as numeric strings internally, your application will need to handle the conversion between a numeric string and integer if your database engine or chosen ORM/DBAL library cannot do this implicitly.

Helper functions `stringToInt()` and `intToString()` are provided for this:

```php
// Convert a numeric string ID to an integer for database storage
$idForDatabase = $generator->stringToInt($id);

// Convert an integer ID from the database back to a numeric string
$id = $generator->intToString($idFromDatabase);
```

Note that `stringToInt()` will return a negative integer for IDs greater than `(2 ** 63) - 1`, however as these are binary identical to an unsigned equivalent, your chosen ORM/DBAL should be able to accept it and convert it to unsigned (on supporting database engines).

If you're using [Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html) this can handle the conversion transparently with the "BIGINT" column type, allowing you to keep IDs as numeric strings in your application.

### Encryption and encoding

IDs must be stored **without** both encryption and encoding.

This is important to provide optimal performance with both sorting (by creation time) and cursor-based navigation (for pagination). After all, the underlying database should already be built and configured with security in mind, therefore encryption and encoding isn't necessary at this layer.

Helper functions `encryptId()`, `decryptId()`, `encodeId()`, and `decodeId()` are provided for your application to handle encryption and encoding at the application layer if you choose to do so.

- When storing a value into database, decode first, then decrypt
- When retrieving a value from database, encrypt first, then encode

User queries and internal application references should be expected to be both encrypted and encoded if the application opts in for these.

Whether you use encryption and encoding at your application layer is up to you, though once making a decision you should stick with it once your application begins generating IDs. Changing after this point could mean that permalinks and hard references outside your application will no longer work.

Typically you should opt in to both encryption and encoding:

- Encrypting IDs means that they will be unpredictable, thus will not leak the creation time, node ID, and ID sequence to end users
- Encoding IDs will shorten them from an integer up to 20 digits down to a string up to 13 characters

### Symfony and Doctrine ORM/DBAL integration

If you're using this as part of a [Symfony](https://symfony.com/) project with a Doctrine ORM/DBAL integration consider getting my [Symfony bundle](https://github.com/adambean/randflake-id-bundle), which provides a custom column type and ID generator for seamless integration, including transparent handling of encrypted and encoded IDs.

## Development & contributing

Ensure any contribution is compatible with the earliest supported PHP version.

Clean up code style to the accepted standards:

```sh
composer cs:fix
```

Ensure static analysis checks pass:

```sh
composer stan
```

Ensure all tests pass:

```sh
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.

## Credits

- PHP implementation: [Adam "Adambean" Reece](https://github.com/adambean)
- Original implementation: [lemon-mint](https://github.com/lemon-mint) at [GoSuda](https://github.com/gosuda)
