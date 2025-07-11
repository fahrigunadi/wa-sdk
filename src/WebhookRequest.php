<?php

declare(strict_types=1);

namespace FahriGunadi\Whatsapp;

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class WebhookRequest
 *
 * Custom request class that provides helper methods to access WhatsApp webhook data.
 */
class WebhookRequest extends FormRequest
{
    /**
     * Get the message text from the WhatsApp webhook.
     *
     * @return string|null The message text, or null if not available.
     */
    public function messageText(): ?string
    {
        return whatsapp()->webhookMessageText();
    }

    /**
     * Get the message id from the WhatsApp webhook.
     *
     * @return string|null The message id, or null if not available.
     */
    public function messageId(): ?string
    {
        return whatsapp()->webhookMessageId();
    }

    /**
     * Get the pushname from the WhatsApp webhook.
     *
     * @return string|null The pushname, or null if not available.
     */
    public function pushname(): ?string
    {
        return whatsapp()->webhookPushname();
    }

    /**
     * Get the sender's WhatsApp number.
     *
     * @return string The sender's phone number.
     */
    public function sender(): string
    {
        return whatsapp()->webhookSender();
    }

    /**
     * Determine if the message is from a group chat.
     *
     * @return bool True if the message is from a group, false otherwise.
     */
    public function isGroup(): bool
    {
        return whatsapp()->webhookIsGroup();
    }

    /**
     * Get the chat ID.
     *
     * @return string The chat identifier.
     */
    public function chat(): string
    {
        return whatsapp()->webhookChat();
    }

    /**
     * Determine if the webhook message contains an image.
     *
     * @return bool True if the message is an image, false otherwise.
     */
    public function isImage(): bool
    {
        return whatsapp()->webhookIsImage();
    }

    /**
     * Get the MIME type of the image from the webhook.
     *
     * @return string|null The image MIME type, or null if not an image.
     */
    public function imageMimeType(): ?string
    {
        return whatsapp()->webhookImageMimeType();
    }

    /**
     * Get the image URL from the webhook payload.
     *
     * @return string|null The URL to the image, or null if not present.
     */
    public function image(): ?string
    {
        return whatsapp()->webhookImage();
    }

    /**
     * Determine if the webhook message contains a document.
     *
     * @return bool True if the message contains a document, false otherwise.
     */
    public function isDocument(): bool
    {
        return whatsapp()->webhookIsDocument();
    }

    /**
     * Get the MIME type of the document from the webhook.
     *
     * @return string|null The document MIME type, or null if not a document.
     */
    public function documentMimeType(): ?string
    {
        return whatsapp()->webhookDocumentMimeType();
    }

    /**
     * Get the document URL from the webhook payload.
     *
     * @return string|null The URL to the document, or null if not present.
     */
    public function document(): ?string
    {
        return whatsapp()->webhookDocument();
    }

    /**
     * Get the reply instance for replying to the sender.
     */
    public function reply(): WhatsappInterface
    {
        return whatsapp()->to($this->chat())->replyMessage($this->messageId(), $this->sender());
    }
}
