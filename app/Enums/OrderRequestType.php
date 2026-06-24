<?php

namespace App\Enums;

enum OrderRequestType: string
{
    case Cancel = 'CANCEL';
    case Return = 'RETURN';
}
