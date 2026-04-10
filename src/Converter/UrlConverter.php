<?php

namespace App\Converter;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 10)]
final class UrlConverter implements ConverterInterface
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
