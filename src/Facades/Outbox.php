<?php

declare(strict_types=1);

namespace Outbox\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Outbox\Client;

/**
 * @method static array{id: string, status: string, duplicate?: bool, recipients?: list<array{email: string, id: string, status: string, duplicate: bool}>} send(array<string, mixed> $message, ?string $idempotencyKey = null)
 * @method static array{accepted: int, failed: int, results: list<array<string, mixed>>} sendBatch(list<array<string, mixed>> $messages)
 * @method static array<string, mixed> getMessage(string $id)
 * @method static list<array<string, mixed>> listMessages(?string $status = null, ?int $limit = null, ?string $to = null, ?string $after = null)
 * @method static array{messages: list<array<string, mixed>>, next: string|null} listMessagesPage(?string $status = null, ?int $limit = null, ?string $to = null, ?string $after = null)
 * @method static \Generator<int, array<string, mixed>> iterateMessages(?string $status = null, ?int $limit = null, ?string $to = null, ?string $after = null)
 * @method static array{id: string, status: string} cancelMessage(string $id)
 * @method static list<array{email: string, reason: string, created_at: string}> listSuppressions(?int $limit = null, ?string $after = null)
 * @method static array{suppressions: list<array{email: string, reason: string, created_at: string}>, next: string|null} listSuppressionsPage(?int $limit = null, ?string $after = null)
 * @method static \Generator<int, array{email: string, reason: string, created_at: string}> iterateSuppressions(?int $limit = null, ?string $after = null)
 * @method static array{email: string} addSuppression(string $email)
 * @method static array{removed: string} removeSuppression(string $email)
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
