<?php

use App\Search\BookingSearchProvider;
use App\Search\ClientSearchProvider;
use App\Search\EventSearchProvider;
use App\Search\InvoiceSearchProvider;
use App\Search\UserSearchProvider;
use App\Search\VendorSearchProvider;

return [
    'minimum_length' => 2,
    'per_provider_limit' => 8,
    'providers' => [
        EventSearchProvider::class,
        BookingSearchProvider::class,
        InvoiceSearchProvider::class,
        ClientSearchProvider::class,
        VendorSearchProvider::class,
        UserSearchProvider::class,
    ],
];
