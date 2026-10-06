<?php

declare(strict_types=1);

namespace Outbox\Laravel\Http;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Outbox\Exception\WebhookVerificationException;
use Outbox\Laravel\Events;
use Outbox\Webhook;

final class WebhookController
{
    private const EVENTS = [
        'message.sent' => Events\MessageSent::class,
        'message.delivered' => Events\MessageDelivered::class,
        'message.bounced' => Events\MessageBounced::class,
        'message.complained' => Events\MessageComplained::class,
        'message.failed' => Events\MessageFailed::class,
        'message.opened' => Events\MessageOpened::class,
        'message.clicked' => Events\MessageClicked::class,
        'contact.subscribed' => Events\ContactSubscribed::class,
        'contact.unsubscribed' => Events\ContactUnsubscribed::class,
    ];

    public function __invoke(Request $request, Dispatcher $events, Repository $config): JsonResponse
    {
        $secret = $config->get('outbox.webhook.secret');
        $tolerance = $config->get('outbox.webhook.tolerance', 300);
        try {
            /** @var array<string, list<string|null>> $headers */
            $headers = $request->headers->all();
            $payload = Webhook::verify($request->getContent(), array_map(static fn (array $v) => array_map(strval(...), $v), $headers), is_string($secret) ? $secret : '', is_int($tolerance) ? $tolerance : 300);
        } catch (WebhookVerificationException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
        $events->dispatch(new Events\WebhookReceived($payload));
        $class = self::EVENTS[$payload['type']] ?? null;
        if ($class !== null) {
            $events->dispatch(new $class($payload));
        }
        return new JsonResponse(['received' => true]);
    }
}
