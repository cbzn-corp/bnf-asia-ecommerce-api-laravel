<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'roleId' => ['sometimes', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:6'],
        ];
    }
}
