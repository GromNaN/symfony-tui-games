<?php

namespace App\Cipher;

interface KeyedCipherInterface extends CipherInterface
{
    public function getDefaultKey(): string;

    public function encodeWithKey(string $text, string $key): string;

    public function decodeWithKey(string $text, string $key): string;
}
