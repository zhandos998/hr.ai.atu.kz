<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;
use Normalizer;

class Username
{
    public static function normalize(string $value): string
    {
        return mb_strtolower(Normalizer::normalize(trim($value), Normalizer::FORM_C) ?: trim($value));
    }

    public static function rules(?User $user = null): array
    {
        return [
            'nullable', 'string', 'min:3', 'max:64', 'regex:/^[\pL\pN][\pL\pM\pN._-]*$/uD',
            Rule::unique('users', 'username')->ignore($user?->id),
        ];
    }
}
