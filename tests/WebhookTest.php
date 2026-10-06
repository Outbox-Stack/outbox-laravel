<?php

declare(strict_types=1);

namespace Outbox\Laravel\Tests;

use Illuminate\Support\Facades\Event;
use Outbox\Laravel\Events\MessageBounced;
use Outbox\Laravel\Events\WebhookReceived;

final class WebhookTest extends TestCase
{
    private function signedPost(string $body, string $secret = self::SECRET, ?int $timestamp = null): \Illuminate\Testing\TestResponse
    {
        $id = 'msg_' . bin2hex(random_bytes(4));
        $timestamp ??= time();
        $key = base64_decode(substr($secret, strlen('whsec_')));
        $signature = 'v1,' . base64_encode(hash_hmac('sha256', "$id.$timestamp.$body", $key, true));
        return $this->call('POST', '/outbox/webhook', [], [], [], [
            'HTTP_WEBHOOK_ID' => $id, 'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function testVerifiedWebhookDispatchesTypedEvents(): void
    {
        Event::fake([MessageBounced::class, WebhookReceived::class]);
        $body = json_encode(['type' => 'message.bounced', 'created_at' => '2026-10-06T16:00:00Z', 'data' => ['message_id' => 'm1', 'to' => 'ada@example.com', 'metadata' => ['order_id' => '42']]]);
        $this->signedPost($body)->assertOk()->assertJson(['received' => true]);
        Event::assertDispatched(MessageBounced::class, fn (MessageBounced $e) => $e->email() === 'ada@example.com' && $e->metadata() === ['order_id' => '42'] && $e->messageId() === 'm1');
        Event::assertDispatched(WebhookReceived::class);
    }

    public function testBadSignaturesAndStaleTimestampsAreRejected(): void
    {
        Event::fake([MessageBounced::class, WebhookReceived::class]);
        $body = json_encode(['type' => 'message.bounced', 'created_at' => 'x', 'data' => []]);
        $this->signedPost($body, 'whsec_' . base64_encode('another-secret-another-secret!!'))->assertStatus(400);
        $this->signedPost($body, timestamp: time() - 3600)->assertStatus(400);
        $this->postJson('/outbox/webhook', ['type' => 'message.bounced'])->assertStatus(400);
        Event::assertNotDispatched(MessageBounced::class);
        Event::assertNotDispatched(WebhookReceived::class);
    }
}
