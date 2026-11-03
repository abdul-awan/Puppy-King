<?php

namespace App\Enums;

use App\Concerns\EnumOptions;

enum BusinessType: string
{
    use EnumOptions;

    case SoleProprietor = 'sole_proprietor';
    case Partnership = 'partnership';
    case Aop = 'aop';
    case SingleMemberCompany = 'smc';
    case PrivateLimited = 'private_limited';
    case PublicLimited = 'public_limited';
    case Government = 'government';
    case NonProfit = 'non_profit';
    case Foreign = 'foreign';
    case Other = 'other';

    /**
     * Get the display label for the business type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SoleProprietor => 'Sole proprietor',
            self::Partnership => 'Partnership firm',
            self::Aop => 'Association of persons (AOP)',
            self::SingleMemberCompany => 'Single member company (SMC)',
            self::PrivateLimited => 'Private limited company',
            self::PublicLimited => 'Public limited company',
            self::Government => 'Government / public sector',
            self::NonProfit => 'NGO / non-profit',
            self::Foreign => 'Foreign company',
            self::Other => 'Other',
        };
    }
}
