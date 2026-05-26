<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationStageLog extends Model
{
    protected $guarded = [];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
