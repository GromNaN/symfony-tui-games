<?php

namespace App\Converter;

final class Base64Converter implements ConverterInterface
{
    public function getId(): string
    {
        return 'base64';
    }

    public function getName(): string
    {
        return 'Base64';
    }

    public function encode(string $text): string
    {
        return base64_encode($text);
    }

    public function decode(string $text): string
    {
        $decoded = base64_decode($text, strict: true);

        return false !== $decoded ? $decoded : $text;
    }
}
