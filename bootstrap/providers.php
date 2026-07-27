<?php

use App\Domain\Logging\LoggingServiceProvider;
use App\Domain\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    LoggingServiceProvider::class,
    TenancyServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
];
