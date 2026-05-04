<?php

namespace Fozimat\TelegramMonitor;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

class TelegramMonitorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/telegram_monitor.php', 'telegram_monitor'
        );

        $this->app->singleton(TelegramErrorNotifier::class, function ($app) {
            return new TelegramErrorNotifier();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/telegram_monitor.php' => config_path('telegram_monitor.php'),
        ], 'telegram-monitor-config');

        // Hook into Laravel's exception reporting pipeline when reportable callbacks are supported.
        $this->app->resolving(ExceptionHandler::class, function ($handler) {
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $e) {
                    $notifier = $this->app->make(TelegramErrorNotifier::class);
                    $notifier->notify($e);
                });
            }
        });

        // Listen to Laravel log events so direct Log::error()/critical() calls can also be reported.
        if ($this->app->bound('events')) {
            $this->app['events']->listen('Illuminate\Log\Events\MessageLogged', function ($event) {
                $notifier = $this->app->make(TelegramErrorNotifier::class);
                $notifier->notifyLog(
                    (string) ($event->level ?? 'error'),
                    $event->message ?? '',
                    is_array($event->context ?? null) ? $event->context : []
                );
            });
        }
    }
}
