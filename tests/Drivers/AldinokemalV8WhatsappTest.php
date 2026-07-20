<?php

use FahriGunadi\Whatsapp\Drivers\AldinokemalWhatsapp;
use FahriGunadi\Whatsapp\Drivers\WuzapiWhatsapp;

describe('driver base defaults', function () {
    it('throws not implemented for file() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->file('https://example.com/a.pdf');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for video() on aldinokemal v1', function () {
        (new AldinokemalWhatsapp)->video('https://example.com/a.mp4');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for file() on wuzapi', function () {
        (new WuzapiWhatsapp)->file('https://example.com/a.pdf');
    })->throws(Exception::class, 'Not implemented');

    it('throws not implemented for video() on wuzapi', function () {
        (new WuzapiWhatsapp)->video('https://example.com/a.mp4');
    })->throws(Exception::class, 'Not implemented');

    it('stores optional flags fluently without affecting drivers that do not read them', function () {
        $driver = (new AldinokemalWhatsapp)
            ->forwarded()
            ->duration(86400)
            ->viewOnce()
            ->compress()
            ->gifPlayback();

        expect($driver)->toBeInstanceOf(AldinokemalWhatsapp::class);
    });
});

use FahriGunadi\Whatsapp\Drivers\AldinokemalV8Whatsapp;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'whatsapp.username' => 'user',
        'whatsapp.password' => 'pass',
        'whatsapp.base_url' => 'https://gowa.example.com',
    ]);
});

describe('AldinokemalV8Whatsapp::send() video', function () {
    it('posts to /send/video with an absolute video url', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->video('https://example.com/sample.mp4')
            ->message('a caption')
            ->replyMessage('3EB089B9D6ADD58153C561')
            ->viewOnce()
            ->compress()
            ->gifPlayback()
            ->duration(86400)
            ->forwarded()
            ->send();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/send/video'
                && $request['phone'] === '6289685028129@s.whatsapp.net'
                && $request['caption'] === 'a caption'
                && $request['reply_message_id'] === '3EB089B9D6ADD58153C561'
                && $request['view_once'] === true
                && $request['video_url'] === 'https://example.com/sample.mp4'
                && $request['compress'] === true
                && $request['gif_playback'] === true
                && $request['duration'] === 86400
                && $request['is_forwarded'] === true;
        });
    });

    it('allows sending a video without a caption', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->video('https://example.com/sample.mp4')
            ->send();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gowa.example.com/send/video');
    });
});

describe('AldinokemalV8Whatsapp::send() file', function () {
    it('posts to /send/file with an absolute file url', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->file('https://example.com/document.pdf')
            ->message('a caption')
            ->replyMessage('3EB089B9D6ADD58153C561')
            ->duration(604800)
            ->forwarded()
            ->send();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/send/file'
                && $request['phone'] === '6289685028129@s.whatsapp.net'
                && $request['caption'] === 'a caption'
                && $request['reply_message_id'] === '3EB089B9D6ADD58153C561'
                && $request['file_url'] === 'https://example.com/document.pdf'
                && $request['duration'] === 604800
                && $request['is_forwarded'] === true;
        });
    });

    it('prefers video over file when both are set', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->file('https://example.com/document.pdf')
            ->video('https://example.com/sample.mp4')
            ->send();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gowa.example.com/send/video');
    });
});

describe('AldinokemalV8Whatsapp::send() image', function () {
    it('posts to /send/image with the optional flags', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->image('https://example.com/photo.jpg')
            ->message('a caption')
            ->viewOnce()
            ->compress()
            ->duration(7776000)
            ->forwarded()
            ->send();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/send/image'
                && $request['image_url'] === 'https://example.com/photo.jpg'
                && $request['view_once'] === true
                && $request['compress'] === true
                && $request['duration'] === 7776000
                && $request['is_forwarded'] === true;
        });
    });
});

describe('AldinokemalV8Whatsapp::send() text', function () {
    it('posts to /send/message with the optional flags', function () {
        Http::fake(['*' => Http::response(['status' => 200], 200)]);

        (new AldinokemalV8Whatsapp)
            ->to('6289685028129@s.whatsapp.net')
            ->message('selamat malam')
            ->duration(86400)
            ->forwarded()
            ->send();

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://gowa.example.com/send/message'
                && $request['message'] === 'selamat malam'
                && $request['duration'] === 86400
                && $request['is_forwarded'] === true;
        });
    });
});
