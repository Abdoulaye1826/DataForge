<?php

namespace App\Models;

use App\Enums\WidgetType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'visualization_id',
        'widget_type',
        'title',
        'position_x',
        'position_y',
        'width',
        'height',
        'config',
    ];

    protected $casts = [
        'widget_type' => WidgetType::class,
        'config' => 'array',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function visualization(): BelongsTo
    {
        return $this->belongsTo(Visualization::class);
    }
}
