<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;

abstract class ApiController extends Controller
{
    use ApiResponse;

    protected function tenantId(): int
    {
        return (int) config('tenant.tenant_id');
    }

    protected function locationId(): int
    {
        return (int) config('tenant.location_id');
    }
}
