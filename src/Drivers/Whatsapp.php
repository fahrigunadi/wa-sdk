<?php

declare(strict_types=1);

namespace FahriGunadi\WhatsApp\Drivers;

use FahriGunadi\Whatsapp\Traits\Logging;
use Illuminate\Support\Collection;
use InvalidArgumentException;

abstract class Whatsapp
{
    use Logging;

    /**
     * Format the given phone number to WhatsApp standard (starts with 62).
     *
     * - Removes characters like spaces, dashes, parentheses, and plus sign.
     * - Strips suffixes like `@s.whatsapp.net` or any `:` delimited part.
     * - Converts numbers starting with "08" to "628".
     *
     * @param  string  $phone  Raw phone number input.
     * @return string Formatted phone number compatible with WhatsApp.
     *
     * @throws InvalidArgumentException If the phone number is empty.
     */
    public function formatPhone(string $phone): string
    {
        throw_if(! $phone, new InvalidArgumentException('Phone number must be set'));

        $phone = str($phone)
            ->replace([' ', '-', '(', ')', '+'], '')
            ->trim()
            ->whenContains('@s.whatsapp.net', fn ($p) => $p->before('@s.whatsapp.net'))
            ->whenContains(':', fn ($p) => $p->before(':'))
            ->whenStartsWith('08', fn ($p) => $p->substr(1)->prepend('62'));

        return $phone->toString();
    }

    /**
     * Determine whether the given phone number is a valid Indonesian number.
     *
     * Valid formats:
     * - +628xxx
     * - 628xxx
     * - 08xxx
     *
     * @param  string  $phone  Phone number to validate.
     * @return bool True if valid, false otherwise.
     */
    public function hasValidPhone(string $phone): bool
    {
        return (bool) preg_match('/^(\+62|62|0)8[1-9][0-9]{6,9}$/', $phone);
    }

    /**
     * Generate a plain-text table from a collection of items.
     *
     * This is useful for rendering aligned data in monospaced outputs,
     * such as in WhatsApp messages or CLI outputs.
     *
     * @param  Collection  $items  The data to display.
     * @param  array  $columns  Key-value pairs where key is the field name, value is the column label.
     * @return string Formatted text table wrapped in triple backticks.
     */
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

    /**
     * Check if the given string is a valid base64 encoded string.
     *
     * @param  string  $string  The string to check.
     * @return bool True if the string is valid base64, false otherwise.
     */
    public function isValidBase64($string)
    {
        if (! preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $string)) {
            return false;
        }

        // lenght mus be a multiple of 4
        if (strlen($string) % 4 !== 0) {
            return false;
        }

        $decoded = base64_decode($string, true);
        if ($decoded === false) {
            return false;
        }

        return base64_encode($decoded) === $string;
    }
}
