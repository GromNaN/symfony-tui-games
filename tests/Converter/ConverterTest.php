<?php

namespace App\Tests\Converter;

use App\Converter\Base64Converter;
use App\Converter\LeetConverter;
use App\Converter\Rot13Converter;
use App\Converter\UrlConverter;
use PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    public function testLeetEncode()
    {
        $c = new LeetConverter();
        self::assertSame('l337 5p34k', $c->encode('leet speak'));
        self::assertSame('533 y0u 47 7h3 p4r7y', $c->encode('see you at the party'));
    }

    public function testLeetDecode()
    {
        $c = new LeetConverter();
        self::assertSame('ieet speak', $c->decode('1337 5p34k'));
        self::assertSame('see you at the party', $c->decode('533 y0u 4t 7h3 p4r7y'));
    }

    public function testLeetRoundTrip()
    {
        $c = new LeetConverter();
        self::assertSame('hello', $c->decode($c->encode('hello')));
    }

    public function testBase64Encode()
    {
        $c = new Base64Converter();
        self::assertSame('aGVsbG8=', $c->encode('hello'));
        self::assertSame('SGVsbG8gV29ybGQ=', $c->encode('Hello World'));
    }

    public function testBase64Decode()
    {
        $c = new Base64Converter();
        self::assertSame('hello', $c->decode('aGVsbG8='));
        self::assertSame('Hello World', $c->decode('SGVsbG8gV29ybGQ='));
    }

    public function testBase64DecodeInvalidInput()
    {
        $c = new Base64Converter();
        // Invalid base64 returns the original string unchanged
        self::assertSame('not-valid-base64!!!', $c->decode('not-valid-base64!!!'));
    }

    public function testBase64RoundTrip()
    {
        $c = new Base64Converter();
        self::assertSame('hello', $c->decode($c->encode('hello')));
    }

    public function testRot13Encode()
    {
        $c = new Rot13Converter();
        self::assertSame('uryyb', $c->encode('hello'));
        self::assertSame('Uryyb Jbeyq', $c->encode('Hello World'));
    }

    public function testRot13Decode()
    {
        $c = new Rot13Converter();
        self::assertSame('hello', $c->decode('uryyb'));
        self::assertSame('Hello World', $c->decode('Uryyb Jbeyq'));
    }

    public function testRot13IsItsOwnInverse()
    {
        $c = new Rot13Converter();
        $text = 'Hello World';
        self::assertSame($text, $c->decode($c->encode($text)));
        self::assertSame($c->encode($text), $c->decode($text));
    }

    public function testUrlEncode()
    {
        $c = new UrlConverter();
        self::assertSame('hello%20world', $c->encode('hello world'));
        self::assertSame('foo%3Dbar%26baz%3Dqux', $c->encode('foo=bar&baz=qux'));
    }

    public function testUrlDecode()
    {
        $c = new UrlConverter();
        self::assertSame('hello world', $c->decode('hello%20world'));
        self::assertSame('foo=bar&baz=qux', $c->decode('foo%3Dbar%26baz%3Dqux'));
    }

    public function testUrlRoundTrip()
    {
        $c = new UrlConverter();
        $text = 'hello world & foo=bar';
        self::assertSame($text, $c->decode($c->encode($text)));
    }
}
