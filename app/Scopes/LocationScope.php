<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class LocationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * SECURITY DIRECTIVES:
     * 1. Only apply if active_city_id exists in session
     * 2. Do NOT apply in console (artisan commands)
     * 3. Do NOT apply in Filament Admin Panel
     */
    public function apply(Builder $builder, Model $model)
    {
        // Guard 1: Console protection
        if (app()->runningInConsole()) {
            return;
        }

        // Guard 2: Admin Panel protection (Filament)
        if ($this->isAdminPanel()) {
            return;
        }

        // Guard 3: Session check
        if (!session()->has('active_city_id')) {
            return;
        }

        // Apply the location filter
        $builder->where($model->getTable() . '.city_id', session('active_city_id'));
    }

    /**
     * Check if current request is from Filament Admin Panel
     */
    protected function isAdminPanel(): bool
    {
        // Method 1: Check route prefix
        if (request()->is('admin/*') || request()->is('admin')) {
            return true;
        }

        // Method 2: Check Filament context (if Filament is loaded)
        if (class_exists(\Filament\Facades\Filament::class)) {
            try {
                return \Filament\Facades\Filament::getCurrentPanel() !== null;
            } catch (\Throwable $e) {
                // Filament not in context
            }
        }

        return false;
    }
}
