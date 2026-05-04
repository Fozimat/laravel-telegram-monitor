<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Error Monitoring
    |--------------------------------------------------------------------------
    |
    | Determine if error monitoring should be sent to Telegram.
    |
    */
    'enabled' => env('ERROR_MONITORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    |
    | The token of your Telegram bot. You can get this from @BotFather.
    |
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Telegram Chat ID
    |--------------------------------------------------------------------------
    |
    | The Chat ID or Group ID where the bot should send the messages.
    |
    */
    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Capture Laravel Log Events
    |--------------------------------------------------------------------------
    |
    | When enabled, direct Log::error(), Log::critical(), and similar log
    | calls will also be reported to Telegram when they pass the threshold.
    |
    */
    'capture_logs' => env('ERROR_MONITORING_CAPTURE_LOGS', true),

    /*
    |--------------------------------------------------------------------------
    | Minimum Error Level
    |--------------------------------------------------------------------------
    |
    | Only errors with this level or higher will be reported.
    | Valid levels: debug, info, notice, warning, error, critical, alert, emergency.
    |
    */
    'level' => env('ERROR_MONITORING_LEVEL', 'error'),
];
