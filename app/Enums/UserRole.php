<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Teknisi = 'technician';
    case Guest = 'guest';

    public function canEdit(): bool
    {
        return match ($this) {
            self::Manager, self::Teknisi => true,
            self::Guest => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Manager => __('Manager'),
            self::Teknisi => __('Technician'),
            self::Guest => __('Guest'),
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn ($case) => $case->value, self::cases()),
            array_map(fn ($case) => $case->label(), self::cases())
        );
    }
}