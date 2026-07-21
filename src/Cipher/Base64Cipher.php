<?php

namespace App\Cipher;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 30)]
final class Base64Cipher implements CipherInterface
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
