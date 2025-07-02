<?php

namespace FahriGunadi\Whatsapp\Contracts;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

interface WhatsappInterface
{
    /**
     * Set phone number
     */
    public function to(string $phone): static;

    /**
     * Set message
     */
    public function message(string $message): static;

    /**
     * Set image
     */
    public function image(string $image): static;

    /**
     * Setup request instance
     */
    public function request(): PendingRequest;

    /**
     * Send message
     */
    public function send(): Response;

    /**
     * Format Collection to whatapp table
     */
    public function formatTable(Collection $items, array $columns): string;

    /**
     * Get webhook sender
     */
    public function webhookSender(): string;

    /**
     * Get webhook chat
     */
    public function webhookChat(): string;

    /**
     * Get webhook message
     */
    public function webhookMessage(): ?string;

    /**
     * Get webhook is group
     */
    public function webhookIsGroup(): bool;

    /**
     * Format phone number
     */
    public function formatPhone(string $phone): string;

    /**
     * Check if phone number is valid
     */
    public function hasValidPhone(string $phone): bool;
}
