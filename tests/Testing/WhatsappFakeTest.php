<?php

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Testing\WhatsappFake;

it('implements WhatsappInterface', function () {
    expect(new WhatsappFake)->toBeInstanceOf(WhatsappInterface::class);
});

it('throws not implemented for methods not yet faked', function (string $method, array $arguments) {
    $fake = new WhatsappFake;

    $fake->{$method}(...$arguments);
})->throws(Exception::class, 'Not implemented')->with([
    'to' => ['to', ['628123456789']],
    'replyMessage' => ['replyMessage', ['3EB089B9D6ADD58153C561']],
    'message' => ['message', ['hello']],
    'image' => ['image', ['https://example.com/a.jpg']],
    'request' => ['request', []],
    'send' => ['send', []],
    'webhookSender' => ['webhookSender', []],
    'webhookChat' => ['webhookChat', []],
    'webhookMessageText' => ['webhookMessageText', []],
    'webhookMessageId' => ['webhookMessageId', []],
    'webhookMessageTimestamp' => ['webhookMessageTimestamp', []],
    'webhookPushname' => ['webhookPushname', []],
    'webhookIsGroup' => ['webhookIsGroup', []],
    'webhookIsImage' => ['webhookIsImage', []],
    'webhookImageMimeType' => ['webhookImageMimeType', []],
    'webhookImage' => ['webhookImage', []],
    'webhookIsDocument' => ['webhookIsDocument', []],
    'webhookDocumentMimeType' => ['webhookDocumentMimeType', []],
    'webhookDocument' => ['webhookDocument', []],
    'getMyGroups' => ['getMyGroups', []],
]);
