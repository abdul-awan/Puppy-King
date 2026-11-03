<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum Province: string
{
    use EnumOptions;

    case Punjab = 'punjab';
    case Sindh = 'sindh';
    case KhyberPakhtunkhwa = 'khyber_pakhtunkhwa';
    case Balochistan = 'balochistan';
    case Islamabad = 'islamabad';
    case GilgitBaltistan = 'gilgit_baltistan';
    case AzadKashmir = 'azad_kashmir';

    /**
     * Get the display label for the province or territory.
     */
    public function label(): string
    {
        return match ($this) {
            self::Punjab => 'Punjab',
            self::Sindh => 'Sindh',
            self::KhyberPakhtunkhwa => 'Khyber Pakhtunkhwa',
            self::Balochistan => 'Balochistan',
            self::Islamabad => 'Islamabad Capital Territory',
            self::GilgitBaltistan => 'Gilgit-Baltistan',
            self::AzadKashmir => 'Azad Jammu & Kashmir',
        };
    }
}
