<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    |
    | Hostnames that belong to the platform (no tenant subdomain).
    |
    */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CENTRAL_DOMAINS', 'localhost,127.0.0.1,restora.com,www.restora.com'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Tenant Base Domain
    |--------------------------------------------------------------------------
    |
    | Used when building tenant URLs (e.g. {slug}.restora.com).
    |
    */
    'tenant_base_domain' => env('TENANT_BASE_DOMAIN', 'restora.com'),

    /*
    |--------------------------------------------------------------------------
    | Local Tenant Base Domain
    |--------------------------------------------------------------------------
    */
    'local_tenant_base_domain' => env('LOCAL_TENANT_BASE_DOMAIN', 'localhost'),

];
