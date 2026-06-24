<?php

declare(strict_types=1);

namespace App\Services\Logs;

use App\Models\PaymentLog;

class LogsService
{
  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, PaymentLog>
   */
    public function findPaymentLogs(?string $orderNumber = null)
    {
        return PaymentLog::query()
            ->when($orderNumber !== null && trim($orderNumber) !== '', fn ($q) => $q->where('orderNumber', trim($orderNumber)))
            ->orderByDesc('createdAt')
            ->limit(100)
            ->get(['id', 'provider', 'orderNumber', 'signatureValid', 'createdAt']);
    }
}
