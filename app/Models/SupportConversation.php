<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    use HasCuid;

    protected $table = 'SupportConversation';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'orderId',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversationId');
    }
}
