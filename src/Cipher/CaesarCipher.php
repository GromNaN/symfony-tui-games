<?php

namespace App\Cipher;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 50)]
final class CaesarCipher implements KeyedCipherInterface
{
    public function getId(): string
    {
        return 'caesar';
    }

    public function getName(): string
    {
        return 'Caesar';
    }

    public function getDefaultKey(): string
    {
        return '13';
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
        $shift = ((int) $key) % 26;

        return $this->shift($text, $shift);
    }

    public function decodeWithKey(string $text, string $key): string
    {
        $shift = ((int) $key) % 26;

        return $this->shift($text, 26 - $shift);
    }

    private function shift(string $text, int $shift): string
    {
        $result = '';
        for ($i = 0, $len = \strlen($text); $i < $len; ++$i) {
            $c = $text[$i];
            if ($c >= 'a' && $c <= 'z') {
                $result .= \chr(((\ord($c) - \ord('a') + $shift) % 26) + \ord('a'));
            } elseif ($c >= 'A' && $c <= 'Z') {
                $result .= \chr(((\ord($c) - \ord('A') + $shift) % 26) + \ord('A'));
            } else {
                $result .= $c;
            }
        }

        return $result;
    }
}
