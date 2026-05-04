<?php

namespace Fozimat\TelegramMonitor;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Stringable;
use Throwable;

class TelegramErrorNotifier
{
    /**
     * Send exception details to Telegram.
     */
    public function notify(Throwable $exception): void
    {
        if (! $this->canSendNotifications()) {
            return;
        }

        if (! $this->shouldReportLevel('error')) {
            return;
        }

        if ($this->isThrottled($this->exceptionFingerprint($exception))) {
            return;
        }

        $message = $this->formatExceptionMessage($exception);
        $this->sendMessage($this->botToken(), $this->chatId(), $message);
    }

    /**
     * Send log details to Telegram.
     */
    public function notifyLog(string $level, string|Stringable $message, array $context = []): void
    {
        if (! config('telegram_monitor.capture_logs', true)) {
            return;
        }

        if (! $this->canSendNotifications()) {
            return;
        }

        if (! $this->shouldReportLevel($level)) {
            return;
        }

        if (($context['__telegram_monitor_ignore'] ?? false) === true) {
            return;
        }

        $logMessage = $this->stringifyLogMessage($message);
        $exception = $this->extractExceptionFromContext($context);
        $contextSummary = $this->formatContext($context);

        if ($this->isThrottled($this->logFingerprint($level, $logMessage, $contextSummary, $exception))) {
            return;
        }

        $formattedMessage = $this->formatLogMessage($level, $logMessage, $contextSummary, $exception);
        $this->sendMessage($this->botToken(), $this->chatId(), $formattedMessage);
    }

    /**
     * Format the exception message for Telegram.
     */
    protected function formatExceptionMessage(Throwable $exception): string
    {
        $appName = $this->escapeForHtml((string) config('app.name', 'Laravel'));
        $env = $this->escapeForHtml((string) config('app.env', 'production'));
        $level = strtoupper('error');

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
     * Format a generic log message for Telegram.
     */
    protected function formatLogMessage(string $level, string $message, ?string $contextSummary, ?Throwable $exception): string
    {
        $appName = $this->escapeForHtml((string) config('app.name', 'Laravel'));
        $env = $this->escapeForHtml((string) config('app.env', 'production'));
        $normalizedLevel = strtoupper($level);
        $url = app()->runningInConsole() ? 'N/A' : request()->fullUrl();
        $userId = app()->runningInConsole() ? 'Console' : (auth()->check() ? (string) auth()->id() : 'Guest');
        $time = now()->toDateTimeString();

        $message = $this->escapeForHtml($this->limitText($message, 1000));
        $url = $this->escapeForHtml((string) ($url ?: 'N/A'));
        $userId = $this->escapeForHtml($userId);

        $formatted = "🚨 <b>{$appName} Log Alert!</b>\n"
            . "<b>Environment:</b> {$env}\n"
            . "<b>Level:</b> {$normalizedLevel}\n"
            . "<b>Time:</b> {$time}\n"
            . "<b>URL:</b> {$url}\n"
            . "<b>User ID:</b> {$userId}\n"
            . "<b>Message:</b> <code>{$message}</code>";

        if ($contextSummary !== null && $contextSummary !== '') {
            $formatted .= "\n<b>Context:</b> <code>{$this->escapeForHtml($contextSummary)}</code>";
        }

        if ($exception instanceof Throwable) {
            $formatted .= "\n<b>Exception:</b> <code>{$this->escapeForHtml($this->limitText($exception->getMessage(), 1000))}</code>"
                . "\n<b>File:</b> {$this->escapeForHtml($exception->getFile())}:{$exception->getLine()}";
        }

        return $formatted;
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
            error_log('[laravel-telegram-monitor] Failed to send Telegram notification: ' . $e->getMessage());
        }
    }

    /**
     * Check whether notifications can be sent.
     */
    protected function canSendNotifications(): bool
    {
        if (!config('telegram_monitor.enabled', false)) {
            return false;
        }

        return $this->botToken() !== '' && $this->chatId() !== '';
    }

    /**
     * Apply the configured threshold to a log or exception level.
     */
    protected function shouldReportLevel(string $reportedLevel): bool
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
        $reportedLevel = strtolower($reportedLevel);

        return ($levels[$reportedLevel] ?? 400) >= ($levels[$configuredLevel] ?? 400);
    }

    protected function isThrottled(string $fingerprint): bool
    {
        $cacheKey = 'telegram_error_throttle_' . md5($fingerprint);

        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, 300);

        return false;
    }

    protected function exceptionFingerprint(Throwable $exception): string
    {
        return implode('|', [
            'exception',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            (string) $exception->getLine(),
        ]);
    }

    protected function logFingerprint(string $level, string $message, ?string $contextSummary, ?Throwable $exception): string
    {
        return implode('|', [
            'log',
            strtolower($level),
            $message,
            $contextSummary ?? '',
            $exception?->getFile() ?? '',
            (string) ($exception?->getLine() ?? ''),
        ]);
    }

    protected function extractExceptionFromContext(array $context): ?Throwable
    {
        $exception = $context['exception'] ?? null;

        return $exception instanceof Throwable ? $exception : null;
    }

    protected function formatContext(array $context): ?string
    {
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            unset($context['exception']);
        }

        if ($context === []) {
            return null;
        }

        $normalized = $this->normalizeContextValue($context);
        $json = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        if ($json === false) {
            return 'Unable to encode log context.';
        }

        return $this->limitText($json, 1200);
    }

    protected function normalizeContextValue(mixed $value): mixed
    {
        if ($value instanceof Throwable) {
            return [
                'type' => $value::class,
                'message' => $value->getMessage(),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ];
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeContextValue($item);
            }

            return $normalized;
        }

        if (is_object($value)) {
            return 'Object(' . $value::class . ')';
        }

        if (is_resource($value)) {
            return 'Resource(' . get_resource_type($value) . ')';
        }

        return $value;
    }

    protected function stringifyLogMessage(string|Stringable $message): string
    {
        return $message instanceof Stringable ? (string) $message : $message;
    }

    protected function limitText(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) . '...' : $value;
    }

    protected function botToken(): string
    {
        return (string) config('telegram_monitor.bot_token', '');
    }

    protected function chatId(): string
    {
        return (string) config('telegram_monitor.chat_id', '');
    }

    protected function escapeForHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
