<?php

namespace App\Converter;

interface ConverterInterface
{
    public function getId(): string;

    public function getName(): string;

    public function encode(string $text): string;

    public function decode(string $text): string;
}
