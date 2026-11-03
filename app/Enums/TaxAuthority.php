<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum TaxAuthority: string
{
    use EnumOptions;

    case Fbr = 'fbr';
    case Pra = 'pra';
    case Srb = 'srb';
    case Kpra = 'kpra';
    case Bra = 'bra';
    case Ict = 'ict';
    case Other = 'other';

    /**
     * Get the display label for the tax authority.
     */
    public function label(): string
    {
        return match ($this) {
            self::Fbr => 'FBR (federal sales tax)',
            self::Pra => 'PRA (Punjab services)',
            self::Srb => 'SRB (Sindh services)',
            self::Kpra => 'KPRA (Khyber Pakhtunkhwa services)',
            self::Bra => 'BRA (Balochistan services)',
            self::Ict => 'ICT (Islamabad services)',
            self::Other => 'Other',
        };
    }
}
