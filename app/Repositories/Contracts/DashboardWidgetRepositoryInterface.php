<?php

namespace App\Repositories\Contracts;

use App\Models\DashboardWidget;

interface DashboardWidgetRepositoryInterface
{
    public function find(int $id): ?DashboardWidget;

    public function create(array $attributes): DashboardWidget;

    public function update(DashboardWidget $widget, array $attributes): DashboardWidget;

    public function delete(DashboardWidget $widget): void;
}
