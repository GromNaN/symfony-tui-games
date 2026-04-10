<?php

namespace App\Cipher;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 10)]
final class UrlCipher implements CipherInterface
{
    public function getId(): string
    {
        return 'url';
    }

    public function getName(): string
    {
        return 'URL Encode';
    }

    public function encode(string $text): string
    {
        return rawurlencode($text);
    }

    public function decode(string $text): string
    {
        return rawurldecode($text);
    }
}
