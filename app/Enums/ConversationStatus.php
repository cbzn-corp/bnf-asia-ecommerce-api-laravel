<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case Open = 'OPEN';
    case Resolved = 'RESOLVED';
}
