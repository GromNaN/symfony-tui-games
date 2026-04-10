<?php

namespace App\Converter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.converter')]
interface ConverterInterface
{
    public function getId(): string;

    public function getName(): string;

    public function encode(string $text): string;

    public function decode(string $text): string;
}
