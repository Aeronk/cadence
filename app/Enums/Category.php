<?php

namespace App\Enums;

enum Category: string
{
    case Work = 'work';
    case Personal = 'personal';
    case Family = 'family';
    case Church = 'church';
    case Social = 'social';
    case Travel = 'travel';
    case Health = 'health';
    case Finance = 'finance';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Work => 'blue',
            self::Personal => 'purple',
            self::Family => 'pink',
            self::Church => 'amber',
            self::Social => 'rose',
            self::Travel => 'sky',
            self::Health => 'emerald',
            self::Finance => 'green',
        };
    }

    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }

    public static function options(): array
    {
        return array_map(
            fn ($c) => ['value' => $c->value, 'label' => $c->label(), 'color' => $c->color()],
            self::cases(),
        );
    }
}
