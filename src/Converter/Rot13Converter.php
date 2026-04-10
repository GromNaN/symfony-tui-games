<?php

namespace App\Converter;

final class Rot13Converter implements ConverterInterface
{
    public function getId(): string
    {
        return 'rot13';
    }

    public function getName(): string
    {
        return 'ROT13';
    }

    public function encode(string $text): string
    {
        return str_rot13($text);
    }

    public function decode(string $text): string
    {
        return str_rot13($text);
    }
}
