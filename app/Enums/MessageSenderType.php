<?php

namespace App\Enums;

enum MessageSenderType: string
{
    case Customer = 'CUSTOMER';
    case Staff = 'STAFF';
    case System = 'SYSTEM';
}
