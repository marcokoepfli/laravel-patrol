<?php

namespace MarcoKoepfli\LaravelPatrol\Enums;

enum LaravelVersion: int
{
    case V11 = 11;
    case V12 = 12;

    public function docsBaseUrl(): string
    {
        return "https://laravel.com/docs/{$this->value}";
    }

    public function docsUrl(string $path): string
    {
        return "{$this->docsBaseUrl()}/{$path}";
    }
}
