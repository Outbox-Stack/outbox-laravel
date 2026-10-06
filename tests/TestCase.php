<?php

declare(strict_types=1);

namespace Outbox\Laravel\Tests;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Orchestra\Testbench\TestCase as Testbench;
use Outbox\Client;
use Outbox\Laravel\OutboxServiceProvider;

abstract class TestCase extends Testbench
{
    public const SECRET = 'whsec_b3V0Ym94LXNoYXJlZC10ZXN0LXZlY3Rvci1rZXktMzI=';

    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    protected array $history = [];

    protected function getPackageProviders($app): array
    {
        return [OutboxServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('mail.default', 'outbox');
        $app['config']->set('mail.mailers.outbox', ['transport' => 'outbox']);
        $app['config']->set('outbox.api_key', 'test-key');
        $app['config']->set('outbox.webhook.secret', self::SECRET);
    }

    /** @param list<Response> $responses */
    protected function fakeApi(array $responses): void
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $this->app->instance(Client::class, new Client(apiKey: 'test-key', baseUrl: 'https://mail.example.com', maxRetries: 0, http: new Guzzle(['handler' => $stack])));
    }

    /** @return array<string, mixed> */
    protected function sentPayload(int $index = 0): array
    {
        return json_decode((string) $this->history[$index]['request']->getBody(), true);
    }
}
