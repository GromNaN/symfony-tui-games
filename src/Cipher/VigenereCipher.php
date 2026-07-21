<?php

namespace App\Cipher;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 45)]
final class VigenereCipher implements KeyedCipherInterface
{
    public function getId(): string
    {
        return 'vigenere';
    }

    public function getName(): string
    {
        return 'Vigenère';
    }

    public function getDefaultKey(): string
    {
        return 'KEY';
    }

    public function encode(string $text): string
    {
        return $this->encodeWithKey($text, $this->getDefaultKey());
    }

    public function decode(string $text): string
    {
        return $this->decodeWithKey($text, $this->getDefaultKey());
    }

    public function encodeWithKey(string $text, string $key): string
    {
        return $this->apply($text, $key, encode: true);
    }

    public function decodeWithKey(string $text, string $key): string
    {
        return $this->apply($text, $key, encode: false);
    }

    private function apply(string $text, string $key, bool $encode): string
    {
        $key = strtoupper($key);
        $keyLen = \strlen($key);
        if (0 === $keyLen) {
            return $text;
        }

        $result = '';
        $keyIndex = 0;
        for ($i = 0, $len = \strlen($text); $i < $len; ++$i) {
            $c = $text[$i];
            if ($c >= 'a' && $c <= 'z') {
                $shift = \ord($key[$keyIndex % $keyLen]) - \ord('A');
                $shifted = $encode
                    ? ((\ord($c) - \ord('a') + $shift) % 26) + \ord('a')
                    : ((\ord($c) - \ord('a') - $shift + 26) % 26) + \ord('a');
                $result .= \chr($shifted);
                ++$keyIndex;
            } elseif ($c >= 'A' && $c <= 'Z') {
                $shift = \ord($key[$keyIndex % $keyLen]) - \ord('A');
                $shifted = $encode
                    ? ((\ord($c) - \ord('A') + $shift) % 26) + \ord('A')
                    : ((\ord($c) - \ord('A') - $shift + 26) % 26) + \ord('A');
                $result .= \chr($shifted);
                ++$keyIndex;
            } else {
                $result .= $c;
            }
        }

        return $result;
    }
}
