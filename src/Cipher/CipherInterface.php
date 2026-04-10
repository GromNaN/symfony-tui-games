<?php

namespace App\Cipher;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface CipherInterface
{
    public function getId(): string;

    public function getName(): string;

    public function encode(string $text): string;

    public function decode(string $text): string;
}
