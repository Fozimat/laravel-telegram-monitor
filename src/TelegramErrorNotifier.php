<?php

namespace Fozimat\TelegramMonitor;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TelegramErrorNotifier
{
    /**
     * Send exception details to Telegram.
     */
    public function notify(Throwable $exception): void
    {
        if (!config('telegram_monitor.enabled', false)) {
            return;
        }

        $token = config('telegram_monitor.bot_token');
        $chatId = config('telegram_monitor.chat_id');

        if (empty($token) || empty($chatId)) {
            return;
        }

        if (! $this->shouldReport()) {
            return;
        }

        // Optional Throttling/Deduplication
        // Prevent spam by hashing the error message and file line
        $errorHash = md5($exception->getMessage() . $exception->getFile() . $exception->getLine());
        $cacheKey = 'telegram_error_throttle_' . $errorHash;

        if (Cache::has($cacheKey)) {
            return; // Already sent recently
        }

        // Cache for 5 minutes (300 seconds) to prevent spam
        Cache::put($cacheKey, true, 300);

        $message = $this->formatMessage($exception);
        $this->sendMessage($token, $chatId, $message);
    }

    /**
     * Format the exception message for Telegram.
     */
    protected function formatMessage(Throwable $exception): string
    {
        $appName = $this->escapeForHtml((string) config('app.name', 'Laravel'));
        $env = $this->escapeForHtml((string) config('app.env', 'production'));
        $level = strtoupper(config('telegram_monitor.level', 'error'));

        $url = app()->runningInConsole() ? 'N/A' : request()->fullUrl();
        $userId = app()->runningInConsole() ? 'Console' : (auth()->check() ? (string) auth()->id() : 'Guest');

        $message = $exception->getMessage();
        $file = $this->escapeForHtml($exception->getFile());
        $line = $exception->getLine();
        $time = now()->toDateTimeString();

        // Limit message length to avoid Telegram message size limits (4096 chars)
        $message = mb_strlen($message) > 1000 ? mb_substr($message, 0, 1000) . '...' : $message;
        $message = $this->escapeForHtml($message);
        $url = $this->escapeForHtml((string) ($url ?: 'N/A'));
        $userId = $this->escapeForHtml($userId);

        return "🚨 <b>{$appName} Error!</b>\n"
            . "<b>Environment:</b> {$env}\n"
            . "<b>Level:</b> {$level}\n"
            . "<b>Time:</b> {$time}\n"
            . "<b>URL:</b> {$url}\n"
            . "<b>User ID:</b> {$userId}\n"
            . "<b>Message:</b> <code>{$message}</code>\n"
            . "<b>File:</b> {$file}:{$line}";
    }

    /**
     * Send HTTP request to Telegram API.
     */
    protected function sendMessage(string $token, string $chatId, string $message): void
    {
        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            
            Http::post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            // Fallback: log locally if Telegram sending fails
            Log::channel('single')->error('Failed to send Telegram error notification: ' . $e->getMessage());
        }
    }

    /**
     * Treat reported exceptions as error-level events and apply the configured threshold.
     */
    protected function shouldReport(): bool
    {
        $levels = [
            'debug' => 100,
            'info' => 200,
            'notice' => 250,
            'warning' => 300,
            'error' => 400,
            'critical' => 500,
            'alert' => 550,
            'emergency' => 600,
        ];

        $configuredLevel = strtolower((string) config('telegram_monitor.level', 'error'));
        $reportedLevel = 'error';

        return ($levels[$reportedLevel] ?? 400) >= ($levels[$configuredLevel] ?? 400);
    }

    protected function escapeForHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
