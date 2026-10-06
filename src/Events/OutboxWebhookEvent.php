<?php

declare(strict_types=1);

namespace Outbox\Laravel\Events;

/** Base class for every verified webhook; listen to a subclass, or to WebhookReceived for all. */
abstract class OutboxWebhookEvent
{
    /** @param array{type: string, created_at: string, data: array<string, mixed>} $payload */
    public function __construct(public readonly array $payload)
    {
    }

    public function type(): string
    {
        return $this->payload['type'];
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->payload['data'];
    }

    public function messageId(): ?string
    {
        $id = $this->payload['data']['message_id'] ?? null;
        return is_string($id) ? $id : null;
    }

    public function email(): ?string
    {
        $email = $this->payload['data']['to'] ?? $this->payload['data']['email'] ?? null;
        return is_string($email) ? $email : null;
    }

    /**
     * The metadata you sent with the message.
     *
     * @return array<string, string>
     */
    public function metadata(): array
    {
        $metadata = $this->payload['data']['metadata'] ?? null;
        $out = [];
        foreach (is_array($metadata) ? $metadata : [] as $key => $value) {
            if (is_scalar($value)) {
                $out[(string) $key] = (string) $value;
            }
        }
        return $out;
    }
}
