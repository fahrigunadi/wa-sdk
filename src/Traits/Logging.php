<?php

declare(strict_types=1);

namespace FahriGunadi\Whatsapp\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;

/**
 * Trait Logging
 *
 * Provides centralized logging functionality for WhatsApp-related operations.
 *
 * Uses the `whatsapp.log_channel` and `whatsapp.webhook_log_channel` config values
 * to determine where logs should be written.
 */
trait Logging
{
    /**
     * Write a general log message to the configured WhatsApp log channel.
     *
     * @param  string|Stringable  $message  The log message content.
     * @param  string  $level  The log level (e.g. 'info', 'error', 'debug'). Default is 'info'.
     *                         Must be one of: 'emergency', 'alert', 'critical', 'error',
     *                         'warning', 'notice', 'info', or 'debug'.
     */
    public function log(string|Stringable $message, string $level = 'info'): void
    {
        Log::channel(config('whatsapp.log_channel'))->log($level, $message);
    }

    /**
     * Write a webhook-specific log message to the configured webhook log channel.
     *
     * @param  string|Stringable  $message  The webhook log message.
     * @param  string  $level  The log level (e.g. 'info', 'error', 'debug'). Default is 'info'.
     */
    public function webhookLog(string|Stringable $message, string $level = 'info'): void
    {
        Log::channel(config('whatsapp.webhook_log_channel'))->log($level, $message);
    }
}
