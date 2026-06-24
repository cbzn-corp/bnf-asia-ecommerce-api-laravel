<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $table = 'EmailTemplate';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'key';

    const CREATED_AT = null;

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'key',
        'subject',
        'bodyText',
        'bodyHtml',
    ];
}
