<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $fillable = ['type', 'number', 'title', 'created_by'];

    protected $casts = ['number' => 'integer'];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public static function defaultTitle(string $type, int $number): string
    {
        return 'Конкурс '.($type === 'pps' ? 'ППС' : 'АУП').' #'.$number;
    }
}
