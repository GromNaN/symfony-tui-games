<?php

namespace App\Cipher;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 40)]
final class LeetCipher implements CipherInterface
{
    private const ENCODE = [
        'a' => '4', 'A' => '4',
        'e' => '3', 'E' => '3',
        'i' => '1', 'I' => '1',
        'o' => '0', 'O' => '0',
        's' => '5', 'S' => '5',
        't' => '7', 'T' => '7',
        'g' => '9', 'G' => '9',
        'b' => '8', 'B' => '8',
    ];

    private const DECODE = [
        '4' => 'a',
        '3' => 'e',
        '1' => 'i',
        '0' => 'o',
        '5' => 's',
        '7' => 't',
        '9' => 'g',
        '8' => 'b',
    ];

    public function getId(): string
    {
        return 'leet';
    }

    public function getName(): string
    {
        return '1337 sp34k';
    }

    public function encode(string $text): string
    {
        return strtr($text, self::ENCODE);
    }

    public function decode(string $text): string
    {
        return strtr($text, self::DECODE);
    }
}
