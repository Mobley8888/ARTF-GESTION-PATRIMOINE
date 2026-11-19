<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum MaintenanceFrequency: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensuelle',
            self::Quarterly => 'Trimestrielle',
            self::Semiannual => 'Semestrielle',
            self::Annual => 'Annuelle',
        };
    }

    public function next(Carbon $date): Carbon
    {
        return match ($this) {
            self::Monthly => $date->copy()->addMonthNoOverflow(),
            self::Quarterly => $date->copy()->addMonthsNoOverflow(3),
            self::Semiannual => $date->copy()->addMonthsNoOverflow(6),
            self::Annual => $date->copy()->addYearNoOverflow(),
        };
    }
}
