<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant / Location scoping
    |--------------------------------------------------------------------------
    |
    | This API serves a single tenant + location deployment. Every query is
    | scoped server-side using these values — they are never accepted from
    | the request, so a client cannot read or write another tenant's data.
    |
    */

    'tenant_id' => env('APP_TENANT_ID', 1),

    'location_id' => env('APP_LOCATION_ID', 1),

];
