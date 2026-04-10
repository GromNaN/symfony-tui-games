<?php

namespace App\Cipher;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 20)]
final class Rot13Cipher implements CipherInterface
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
