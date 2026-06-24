<?php

namespace App\Models;

use App\Enums\MessageSenderType;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasCuid;

    protected $table = 'SupportMessage';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = [
        'conversationId',
        'senderType',
        'senderUserId',
        'body',
        'readAt',
    ];

    protected function casts(): array
    {
        return [
            'senderType' => MessageSenderType::class,
            'readAt' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversationId');
    }
}
