<?php

declare(strict_types=1);

namespace Outbox\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Outbox\Client;

/**
 * @method static array{id: string, status: string, duplicate?: bool, recipients?: list<array{email: string, id: string, status: string, duplicate: bool}>} send(array<string, mixed> $message, ?string $idempotencyKey = null)
 * @method static array{accepted: int, failed: int, results: list<array<string, mixed>>} sendBatch(list<array<string, mixed>> $messages)
 * @method static array<string, mixed> getMessage(string $id)
 * @method static list<array<string, mixed>> listMessages(?string $status = null, ?int $limit = null)
 *
 * @see Client
 */
final class Outbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
