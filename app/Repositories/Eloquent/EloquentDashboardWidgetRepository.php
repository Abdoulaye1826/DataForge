<?php

namespace App\Repositories\Eloquent;

use App\Models\DashboardWidget;
use App\Repositories\Contracts\DashboardWidgetRepositoryInterface;

class EloquentDashboardWidgetRepository implements DashboardWidgetRepositoryInterface
{
    public function find(int $id): ?DashboardWidget
    {
        return DashboardWidget::find($id);
    }

    public function create(array $attributes): DashboardWidget
    {
        return DashboardWidget::create($attributes);
    }

    public function update(DashboardWidget $widget, array $attributes): DashboardWidget
    {
        $widget->update($attributes);

        return $widget;
    }

    public function delete(DashboardWidget $widget): void
    {
        $widget->delete();
    }
}
