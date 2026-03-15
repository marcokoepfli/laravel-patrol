<?php

namespace MarcoKoepfli\LaravelPatrol\Enums;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Error => 'ERROR',
            self::Warning => 'WARNING',
            self::Info => 'INFO',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Error => 'red',
            self::Warning => 'yellow',
            self::Info => 'blue',
        };
    }
}
