<?php

declare(strict_types=1);

namespace App\Domain\Logging;

use App\Domain\Logging\Listeners\RecordAuthenticationEvents;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class LoggingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, [RecordAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Failed::class, [RecordAuthenticationEvents::class, 'handleFailed']);
        Event::listen(Logout::class, [RecordAuthenticationEvents::class, 'handleLogout']);
        Event::listen(PasswordReset::class, [RecordAuthenticationEvents::class, 'handlePasswordReset']);
    }
}
