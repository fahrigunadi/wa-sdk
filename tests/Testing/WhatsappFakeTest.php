<?php

use FahriGunadi\Whatsapp\Contracts\WhatsappInterface;
use FahriGunadi\Whatsapp\Testing\WhatsappFake;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\ExpectationFailedException;

it('implements WhatsappInterface', function () {
    expect(new WhatsappFake)->toBeInstanceOf(WhatsappInterface::class);
});

it('throws not implemented for methods not yet faked', function (string $method, array $arguments) {
    $fake = new WhatsappFake;

    $fake->{$method}(...$arguments);
})->throws(Exception::class, 'Not implemented')->with([
    'request' => ['request', []],
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

it('records send() and can be asserted with assertSent()', function () {
    $fake = new WhatsappFake;

    $fake->to('6289685028129@s.whatsapp.net')
        ->message('halo')
        ->replyMessage('3EB089B9D6ADD58153C561')
        ->forwarded()
        ->duration(86400)
        ->send();

    $fake->assertSent(fn (array $sent) => $sent['to'] === '6289685028129@s.whatsapp.net'
        && $sent['message'] === 'halo'
        && $sent['reply_message_id'] === '3EB089B9D6ADD58153C561'
        && $sent['forwarded'] === true
        && $sent['duration'] === 86400);
});

it('records image/file/video sends with their flags', function () {
    $fake = new WhatsappFake;

    $fake->to('6289685028129@s.whatsapp.net')->image('https://example.com/a.jpg')->viewOnce()->compress()->send();
    $fake->to('6289685028129@s.whatsapp.net')->file('https://example.com/a.pdf')->send();
    $fake->to('6289685028129@s.whatsapp.net')->video('https://example.com/a.mp4')->gifPlayback()->send();

    $fake->assertSentCount(3);
    $fake->assertSent(fn (array $sent) => $sent['image'] === 'https://example.com/a.jpg' && $sent['view_once'] === true && $sent['compress'] === true);
    $fake->assertSent(fn (array $sent) => $sent['file'] === 'https://example.com/a.pdf');
    $fake->assertSent(fn (array $sent) => $sent['video'] === 'https://example.com/a.mp4' && $sent['gif_playback'] === true);
});

it('send() returns a genuine Response with no network call', function () {
    $fake = new WhatsappFake;

    $response = $fake->to('6289685028129@s.whatsapp.net')->message('halo')->send();

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->successful())->toBeTrue();
});

it('assertNotSent() passes when nothing matches', function () {
    $fake = new WhatsappFake;

    $fake->to('6289685028129@s.whatsapp.net')->message('halo')->send();

    $fake->assertNotSent(fn (array $sent) => $sent['to'] === 'someone-else@s.whatsapp.net');
});

it('assertNotSent() fails when a match exists', function () {
    $fake = new WhatsappFake;

    $fake->to('6289685028129@s.whatsapp.net')->message('halo')->send();

    $fake->assertNotSent(fn (array $sent) => $sent['to'] === '6289685028129@s.whatsapp.net');
})->throws(ExpectationFailedException::class);

it('assertNothingSent() passes when send() was never called', function () {
    (new WhatsappFake)->assertNothingSent();
});

it('records revokeMessage() and can be asserted with assertCalled()', function () {
    $fake = new WhatsappFake;

    $fake->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('revokeMessage', fn (array $args) => $args['messageId'] === '3EB089B9D6ADD58153C561'
        && $args['phone'] === '6289685028129@s.whatsapp.net');
});

it('records reactMessage() with the emoji', function () {
    $fake = new WhatsappFake;

    $fake->reactMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', '🙏');

    $fake->assertCalled('reactMessage', fn (array $args) => $args['emoji'] === '🙏');
});

it('records updateMessage() with the new message text', function () {
    $fake = new WhatsappFake;

    $fake->updateMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', 'edited');

    $fake->assertCalled('updateMessage', fn (array $args) => $args['message'] === 'edited');
});

it('records deleteMessage()', function () {
    $fake = new WhatsappFake;

    $fake->deleteMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('deleteMessage');
});

it('records readMessage()', function () {
    $fake = new WhatsappFake;

    $fake->readMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('readMessage');
});

it('records starMessage() and unstarMessage()', function () {
    $fake = new WhatsappFake;

    $fake->starMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    $fake->unstarMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('starMessage');
    $fake->assertCalled('unstarMessage');
});

it('records forwardMessage() with the optional fields', function () {
    $fake = new WhatsappFake;

    $fake->forwardMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', 86400, true);

    $fake->assertCalled('forwardMessage', fn (array $args) => $args['duration'] === 86400 && $args['forceReupload'] === true);
});

it('records downloadMessage()', function () {
    $fake = new WhatsappFake;

    $fake->downloadMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertCalled('downloadMessage');
});

it('message-manipulation methods return a genuine Response with no network call', function () {
    $response = (new WhatsappFake)->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->successful())->toBeTrue();
});

it('assertNotCalled() passes when the method was never recorded', function () {
    (new WhatsappFake)->assertNotCalled('revokeMessage');
});

it('assertNotCalled() fails when a matching call was recorded', function () {
    $fake = new WhatsappFake;

    $fake->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');

    $fake->assertNotCalled('revokeMessage');
})->throws(ExpectationFailedException::class);

it('assertCalledCount() counts only the given method', function () {
    $fake = new WhatsappFake;

    $fake->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    $fake->revokeMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net');
    $fake->reactMessage('3EB089B9D6ADD58153C561', '6289685028129@s.whatsapp.net', '🙏');

    $fake->assertCalledCount('revokeMessage', 2);
    $fake->assertCalledCount('reactMessage', 1);
});
