<?php

namespace App\Enums;

enum ReferralCommissionStatus: string
{
    case Recorded = 'RECORDED';
    case Cancelled = 'CANCELLED';
}
