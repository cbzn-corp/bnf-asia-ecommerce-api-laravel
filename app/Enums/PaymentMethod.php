<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Paymongo = 'PAYMONGO';
    case PaymongoGcash = 'PAYMONGO_GCASH';
    case PaymongoMaya = 'PAYMONGO_MAYA';
    case StripeCard = 'STRIPE_CARD';
    case Cod = 'COD';
    case BankTransfer = 'BANK_TRANSFER';
    case BnplInstallment = 'BNPL_INSTALLMENT';
    case SupportAssisted = 'SUPPORT_ASSISTED';

    public function isPaymongo(): bool
    {
        return match ($this) {
            self::Paymongo, self::PaymongoGcash, self::PaymongoMaya => true,
            default => false,
        };
    }
}
