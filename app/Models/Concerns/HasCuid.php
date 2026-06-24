<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasCuid
{
    public static function bootHasCuid(): void
    {
        static::creating(function ($model) {
            $keyName = $model->getKeyName();

            if (is_string($keyName) && empty($model->getAttribute($keyName))) {
                $model->setAttribute($keyName, (string) Str::ulid());
            }
        });
    }
}
