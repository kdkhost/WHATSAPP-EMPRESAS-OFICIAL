<?php

namespace App\Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingVisit extends Model
{
    protected $table = 'marketing_visits';

    protected $guarded = ['id'];

    protected $casts = [
        'is_unique' => 'boolean',
    ];
}
