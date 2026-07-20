<?php

declare(strict_types=1);

namespace FahriGunadi\Whatsapp\Contracts;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;

/**
 * Interface WhatsappInterface
 *
 * Defines the contract for interacting with a WhatsApp service provider.
 */
interface WhatsappInterface
{
    /**
     * Set the target phone number for the WhatsApp message.
     *
     * @param  string  $phone  The target phone number (e.g., '628123456789').
     */
    public function to(string $phone): static;

    /**
     * Set the target message Id for the WhatsApp message.
     *
     * @param  string  $messageId  The target message Id.
     * @param  string|null  $participant  The participant to reply to, implemented by driver wuzapi.
     */
    public function replyMessage(string $messageId, ?string $participant = null): static;

    /**
     * Set the text message to be sent.
     *
     * @param  string  $message  The message content.
     */
    public function message(string $message): static;

    /**
     * Set the image to be sent.
     *
     * @param  string  $image  The image URL or path.
     */
    public function image(string $image): static;

    /**
     * Set the file/document to be sent.
     *
     * @param  string  $file  The file URL or storage path.
     */
    public function file(string $file): static;

    /**
     * Set the video to be sent.
     *
     * @param  string  $video  The video URL or storage path.
     */
    public function video(string $video): static;

    /**
     * Mark the outgoing message as forwarded.
     *
     * @param  bool  $forwarded  Whether the message should be flagged as forwarded. Default true.
     */
    public function forwarded(bool $forwarded = true): static;

    /**
     * Set the disappearing message duration.
     *
     * @param  int  $seconds  Duration in seconds. Allowed values: 0 (no expiry), 86400 (24h), 604800 (7d), 7776000 (90d).
     */
    public function duration(int $seconds): static;

    /**
     * Mark the image/video to be sent as view-once.
     *
     * @param  bool  $viewOnce  Whether the media should be view-once. Default true.
     */
    public function viewOnce(bool $viewOnce = true): static;

    /**
     * Enable compression for the image/video to be sent.
     *
     * @param  bool  $compress  Whether the media should be compressed. Default true.
     */
    public function compress(bool $compress = true): static;

    /**
     * Display the video to be sent as a looping, silent, autoplay GIF.
     *
     * @param  bool  $gifPlayback  Whether the video should play back as a GIF. Default true.
     */
    public function gifPlayback(bool $gifPlayback = true): static;

    /**
     * Revoke (delete for everyone) a previously sent message.
     *
     * @param  string  $messageId  The ID of the message to revoke.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     */
    public function revokeMessage(string $messageId, string $phone): Response;

    /**
     * React to a message with an emoji.
     *
     * @param  string  $messageId  The ID of the message to react to.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     * @param  string  $emoji  The emoji to react with.
     */
    public function reactMessage(string $messageId, string $phone, string $emoji): Response;

    /**
     * Edit the text of a previously sent message.
     *
     * @param  string  $messageId  The ID of the message to edit.
     * @param  string  $phone  The chat (phone/group JID) the message was sent in.
     * @param  string  $message  The new message text.
     */
    public function updateMessage(string $messageId, string $phone, string $message): Response;

    /**
     * Prepare the HTTP client request instance.
     */
    public function request(): PendingRequest;

    /**
     * Send the WhatsApp message.
     */
    public function send(): Response;

    /**
     * Format a collection of items into a WhatsApp-friendly text table.
     *
     * @param  Collection  $items  The data collection.
     * @param  array  $columns  Array of column keys to include.
     */
    public function formatTable(Collection $items, array $columns): string;

    /**
     * Retrieve the sender's WhatsApp number from the webhook payload.
     */
    public function webhookSender(): string;

    /**
     * Retrieve the chat ID or group ID from the webhook payload.
     */
    public function webhookChat(): string;

    /**
     * Retrieve the message text from the webhook payload.
     */
    public function webhookMessageText(): ?string;

    /**
     * Retrieve the message id from the webhook payload.
     */
    public function webhookMessageId(): ?string;

    /**
     * Get the message timestamp from the webhook payload.
     */
    public function webhookMessageTimestamp(): ?string;

    /**
     * Get the message timestamp from the webhook payload.
     */
    public function webhookPushname(): ?string;

    /**
     * Determine if the webhook message was sent in a group chat.
     */
    public function webhookIsGroup(): bool;

    /**
     * Determine if the webhook message contains an image.
     */
    public function webhookIsImage(): bool;

    /**
     * Get the MIME type of the image in the webhook payload.
     */
    public function webhookImageMimeType(): ?string;

    /**
     * Get the image URL from the webhook payload.
     */
    public function webhookImage(): ?string;

    /**
     * Determine if the webhook message contains a document.
     */
    public function webhookIsDocument(): bool;

    /**
     * Get the MIME type of the document in the webhook payload.
     */
    public function webhookDocumentMimeType(): ?string;

    /**
     * Get the document URL from the webhook payload.
     */
    public function webhookDocument(): ?string;

    /**
     * Format a phone number to comply with WhatsApp format.
     *
     * @param  string  $phone  Raw phone number input.
     * @return string Formatted phone number (e.g., '628123456789').
     */
    public function formatPhone(string $phone): string;

    /**
     * Validate if the given phone number is a valid WhatsApp number.
     *
     * @param  string  $phone  Phone number to validate.
     * @return bool True if valid, false otherwise.
     */
    public function hasValidPhone(string $phone): bool;

    /**
     * Write a general log message to the configured WhatsApp log channel.
     *
     * @param  string|Stringable  $message  The log message content.
     * @param  string  $level  The log level (e.g. 'info', 'error', 'debug'). Default is 'info'.
     *                         Must be one of: 'emergency', 'alert', 'critical', 'error',
     *                         'warning', 'notice', 'info', or 'debug'.
     */
    public function log(string|Stringable $message, string $level = 'info'): void;

    /**
     * Write a webhook-specific log message to the configured webhook log channel.
     *
     * @param  string|Stringable  $message  The webhook log message.
     * @param  string  $level  The log level (e.g. 'info', 'error', 'debug'). Default is 'info'.
     */
    public function webhookLog(string|Stringable $message, string $level = 'info'): void;

    /**
     * Retrieve a list of the user's WhatsApp groups.
     */
    public function getMyGroups(): Collection;
}
