<?php

namespace App\Enums;

enum ShippingStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Shipped = 'SHIPPED';
    case Delivered = 'DELIVERED';
    case Cancelled = 'CANCELLED';
}
