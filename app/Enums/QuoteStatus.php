<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case None = 'NONE';
    case PendingReview = 'PENDING_REVIEW';
    case QuoteSent = 'QUOTE_SENT';
    case Accepted = 'ACCEPTED';
    case Cancelled = 'CANCELLED';
}
