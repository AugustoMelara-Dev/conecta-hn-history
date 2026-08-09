<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Crm\ActivityController;
use App\Http\Controllers\Api\V1\Crm\CompanyController;
use App\Http\Controllers\Api\V1\Crm\ContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM API Routes (v1)
|--------------------------------------------------------------------------
|
| REST API for CRM enterprise: contacts, companies, deals, activities.
| All routes are tenant-scoped via `tenant.context` middleware.
|
*/

Route::prefix('v1/crm')
    ->middleware(['auth:sanctum', 'tenant.context'])
    ->name('api.v1.crm.')
    ->group(function () {
        Route::apiResource('contacts', ContactController::class);
        Route::apiResource('companies', CompanyController::class);
        Route::apiResource('activities', ActivityController::class);
    });
