<?php

declare(strict_types=1);

namespace FahriGunadi\WhatsApp\Drivers;

use Exception;
use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AldinokemalWhatsapp implements WhatsappInterface
{
    private ?string $to = null;

    private ?string $message = null;

    private ?string $image = null;

    public function to(string $phone): static
    {
        $this->to = $phone;

        return $this;
    }

    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function image(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function request(): PendingRequest
    {
        $username = config('whatsapp.username');
        $password = config('whatsapp.password');
        $baseUrl = config('whatsapp.base_url');

        throw_unless($username, new Exception('Username is required'));

        throw_unless($password, new Exception('Password is required'));

        throw_unless($baseUrl, new Exception('Base url is required'));

        return Http::withBasicAuth($username, $password)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Request-With' => 'XMLHttpRequest',
                'User-Agent' => 'Fahri/1.0',
            ])
            ->baseUrl($baseUrl);
    }

    public function send(): Response
    {
        if ($this->image) {
            return $this->request()->post('/send/image', [
                'phone' => $this->to,
                'caption' => $this->message,
                'image_url' => str($this->image)->isUrl() ? $this->image : Storage::url($this->image),
            ]);
        }

        return $this->request()->post('/send/message', [
            'phone' => $this->to,
            'message' => $this->message,
        ]);
    }

    public function formatTable(Collection $items, array $columns): string
    {
        $maxLengths = [];
        foreach ($columns as $field => $header) {
            $maxFromValues = $items->max(fn ($item) => strlen((string) data_get($item, $field)));
            $maxLengths[$field] = max(strlen($header), $maxFromValues);
        }

        $lines = [];

        $headerLine = '';
        foreach ($columns as $field => $header) {
            $headerLine .= str_pad($header, $maxLengths[$field]).' | ';
        }
        $lines[] = rtrim($headerLine, ' | ');

        $separatorLine = '';
        foreach ($columns as $field => $_) {
            $separatorLine .= str_repeat('-', $maxLengths[$field]).'-|-';
        }
        $lines[] = rtrim($separatorLine, '-|-');

        foreach ($items as $item) {
            $rowLine = '';
            foreach ($columns as $field => $_) {
                $value = (string) data_get($item, $field);
                $rowLine .= str_pad($value, $maxLengths[$field]).' | ';
            }
            $lines[] = rtrim($rowLine, ' | ');
        }

        return "```\n".implode("\n", $lines)."\n```";
    }

    public function webhookSender(): string
    {
        return str(request()->from)->before(' ')->toString();
    }

    public function webhookChat(): string
    {
        return str(request()->from)->afterLast(' ')->toString();
    }

    public function webhookMessage(): ?string
    {
        return request()->message['text'] ?? null;
    }

    public function webhookIsGroup(): bool
    {
        $chat = $this->webhookChat();

        return (bool) preg_match('/^[\w\d\-]+@g\.us$/', $chat);
    }

    public function formatPhone(string $phone): string
    {
        throw_if(! $phone, new Exception('Phone number is required'));

        $phone = str_replace([' ', '-', '(', ')', '+'], '', $phone);

        if (str_starts_with($phone, '08')) {
            return '62'.substr($phone, 1);
        }

        return $phone;
    }

    public function hasValidPhone(string $phone): bool
    {
        return (bool) preg_match('/^(\+62|62|0)8[1-9][0-9]{6,9}$/', $phone);
    }

    protected function validateData()
    {
        throw_if(! $this->message && ! $this->image, new Exception('Message or image is required'));

        throw_unless($this->to, new Exception('Target is required'));

        throw_unless($this->hasValidPhone($this->to), new Exception('Target is invalid'));
    }
}
