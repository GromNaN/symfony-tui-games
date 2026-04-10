<?php

namespace App\Tests\Cipher;

use App\Cipher\Base64Cipher;
use App\Cipher\CaesarCipher;
use App\Cipher\LeetCipher;
use App\Cipher\Rot13Cipher;
use App\Cipher\VigenereCipher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CipherTest extends TestCase
{
    // ── Leet ─────────────────────────────────────────────────────────────────

    public static function leetEncodeProvider(): array
    {
        return [
            'letters' => ['leet speak', 'l337 5p34k'],
            'sentence' => ['see you at the party', '533 y0u 47 7h3 p4r7y'],
            'upper' => ['LEET SPEAK', 'L337 5P34K'],
            'no change' => ['xyz', 'xyz'],
        ];
    }

    #[DataProvider('leetEncodeProvider')]
    public function testLeetEncode(string $input, string $expected)
    {
        self::assertSame($expected, (new LeetCipher())->encode($input));
    }

    public static function leetDecodeProvider(): array
    {
        return [
            'digits' => ['1337 5p34k', 'ieet speak'],
            'sentence' => ['533 y0u 47 7h3 p4r7y', 'see you at the party'],
            'no change' => ['xyz', 'xyz'],
        ];
    }

    #[DataProvider('leetDecodeProvider')]
    public function testLeetDecode(string $input, string $expected)
    {
        self::assertSame($expected, (new LeetCipher())->decode($input));
    }

    public function testLeetRoundTrip()
    {
        $c = new LeetCipher();
        self::assertSame('hello', $c->decode($c->encode('hello')));
    }

    // ── Base64 ────────────────────────────────────────────────────────────────

    public static function base64EncodeProvider(): array
    {
        return [
            'simple' => ['hello', 'aGVsbG8='],
            'with spaces' => ['Hello World', 'SGVsbG8gV29ybGQ='],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('base64EncodeProvider')]
    public function testBase64Encode(string $input, string $expected)
    {
        self::assertSame($expected, (new Base64Cipher())->encode($input));
    }

    public static function base64DecodeProvider(): array
    {
        return [
            'simple' => ['aGVsbG8=', 'hello'],
            'with spaces' => ['SGVsbG8gV29ybGQ=', 'Hello World'],
            'invalid input' => ['not-valid-base64!!!', 'not-valid-base64!!!'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('base64DecodeProvider')]
    public function testBase64Decode(string $input, string $expected)
    {
        self::assertSame($expected, (new Base64Cipher())->decode($input));
    }

    public function testBase64RoundTrip()
    {
        $c = new Base64Cipher();
        self::assertSame('hello', $c->decode($c->encode('hello')));
    }

    // ── ROT13 ─────────────────────────────────────────────────────────────────

    public static function rot13Provider(): array
    {
        return [
            'lower' => ['hello', 'uryyb'],
            'mixed case' => ['Hello World', 'Uryyb Jbeyq'],
            'digits kept' => ['abc123', 'nop123'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('rot13Provider')]
    public function testRot13Encode(string $input, string $expected)
    {
        self::assertSame($expected, (new Rot13Cipher())->encode($input));
    }

    #[DataProvider('rot13Provider')]
    public function testRot13Decode(string $expected, string $input)
    {
        // ROT13 is its own inverse: decode(encode(x)) == x
        self::assertSame($expected, (new Rot13Cipher())->decode($input));
    }

    // ── Caesar ───────────────────────────────────────────────────────────────

    public static function caesarEncodeProvider(): array
    {
        return [
            'shift 1' => ['abc', '1', 'bcd'],
            'shift 13' => ['abc', '13', 'nop'],
            'ROT13 equiv' => ['Hello World', '13', 'Uryyb Jbeyq'],
            'wraps around' => ['xyz', '3', 'abc'],
            'upper' => ['ABC', '1', 'BCD'],
            'preserves non-alpha' => ['hello!', '1', 'ifmmp!'],
            'empty' => ['', '5', ''],
        ];
    }

    #[DataProvider('caesarEncodeProvider')]
    public function testCaesarEncode(string $input, string $key, string $expected)
    {
        self::assertSame($expected, (new CaesarCipher())->encodeWithKey($input, $key));
    }

    public static function caesarDecodeProvider(): array
    {
        return [
            'shift 1' => ['bcd', '1', 'abc'],
            'shift 13' => ['nop', '13', 'abc'],
            'upper' => ['BCD', '1', 'ABC'],
        ];
    }

    #[DataProvider('caesarDecodeProvider')]
    public function testCaesarDecode(string $input, string $key, string $expected)
    {
        self::assertSame($expected, (new CaesarCipher())->decodeWithKey($input, $key));
    }

    public function testCaesarRoundTrip()
    {
        $c = new CaesarCipher();
        foreach (['1', '3', '13', '25'] as $key) {
            self::assertSame('Hello World', $c->decodeWithKey($c->encodeWithKey('Hello World', $key), $key));
        }
    }

    // ── Vigenère ─────────────────────────────────────────────────────────────

    public static function vigenereEncodeProvider(): array
    {
        return [
            'classic' => ['ATTACKATDAWN', 'LEMON', 'LXFOPVEFRNHR'],
            'lower input' => ['hello', 'key', 'rijvs'],
            'mixed case' => ['Hello', 'KEY', 'Rijvs'],
            'preserves non-alpha' => ['hello world', 'key', 'rijvs uyvjn'],
            'empty' => ['', 'KEY', ''],
        ];
    }

    #[DataProvider('vigenereEncodeProvider')]
    public function testVigenereEncode(string $input, string $key, string $expected)
    {
        self::assertSame($expected, (new VigenereCipher())->encodeWithKey($input, $key));
    }

    public static function vigenereDecodeProvider(): array
    {
        return [
            'classic' => ['LXFOPVEFRNHR', 'LEMON', 'ATTACKATDAWN'],
            'lower input' => ['rijvs', 'key', 'hello'],
        ];
    }

    #[DataProvider('vigenereDecodeProvider')]
    public function testVigenereDecode(string $input, string $key, string $expected)
    {
        self::assertSame($expected, (new VigenereCipher())->decodeWithKey($input, $key));
    }

    public function testVigenereRoundTrip()
    {
        $c = new VigenereCipher();
        foreach (['KEY', 'SECRET', 'A'] as $key) {
            self::assertSame('Hello World', $c->decodeWithKey($c->encodeWithKey('Hello World', $key), $key));
        }
    }
}
