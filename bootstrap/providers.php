<?php

use App\Providers\AIServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HealthCheckServiceProvider;
use App\Providers\JetstreamServiceProvider;
use App\Providers\MinesSsoServiceProvider;
use Dot\SsoClient\DotSsoServiceProvider;

return [
    AppServiceProvider::class,
    HealthCheckServiceProvider::class,
    BroadcastServiceProvider::class,
    FortifyServiceProvider::class,
    JetstreamServiceProvider::class,
    AIServiceProvider::class,
    DotSsoServiceProvider::class,
    MinesSsoServiceProvider::class,
];
