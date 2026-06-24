<?php

namespace App\Enums;

enum RefundStatus: string
{
    case None = 'NONE';
    case Requested = 'REQUESTED';
    case Processed = 'PROCESSED';
    case Rejected = 'REJECTED';
}
