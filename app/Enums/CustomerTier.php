<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum CustomerTier: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';

    case Silver = 'silver';

    case Gold = 'gold';

    public static function fromOrderCount(int $count): self
    {
        return match (true) {
            $count >= 6 => self::Gold,
            $count >= 2 => self::Silver,
            default => self::New,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Silver => 'Silver',
            self::Gold => 'Gold',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Silver => 'gray',
            self::Gold => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::New => Heroicon::Sparkles,
            self::Silver => Heroicon::Star,
            self::Gold => Heroicon::Trophy,
        };
    }
}
