# getoutbox/outbox-laravel

[Outbox Stack](https://outboxstack.app) mail driver and webhooks for Laravel 11, 12 and 13. Your existing Mailables and notifications work unchanged.

```sh
composer require getoutbox/outbox-laravel
```

## Send mail

Add the mailer to `config/mail.php`:

```php
'mailers' => [
    'outbox' => ['transport' => 'outbox'],
    // ...
],
```

Then in `.env` (use a **Sending only** key from Developers in the dashboard):

```dotenv
MAIL_MAILER=outbox
OUTBOX_API_KEY=mk_...
MAIL_FROM_ADDRESS=hello@your-verified-domain.com
```

That's it: `Mail::to($user)->send(new InvoicePaid($invoice))` now goes through Outbox Stack. Supported from a Mailable:

| Laravel | Outbox Stack |
|---|---|
| `to`, `cc`, `bcc` with names | One message per recipient, each with its own id and bounce tracking (50 recipients per mail) |
| `replyTo` | `replyTo` (first address) |
| `attachments()`, `attachData()` | Attachments (10 files, 10 MB) |
| `$message->embed()` in views | Inline images |
| `->metadata('order_id', 42)` | Metadata, returned in webhooks |
| `->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader('X-Entity-Ref', '...'))` | Custom headers |
| `X-Outbox-Idempotency-Key` header | Idempotency key (not sent as a header) |

`->tag()` is ignored; use `->metadata()`. The API's message id is available as `$sentMessage->getMessageId()` and in the `X-Outbox-Message-Id` header. API errors throw Symfony's `TransportException` with the error code and request id in the message.

Make queued mail safe to retry by giving each Mailable a stable key:

```php
public function headers(): Headers
{
    return new Headers(text: ['X-Outbox-Idempotency-Key' => "invoice-paid:{$this->invoice->id}"]);
}
```

## Webhooks

Add a webhook in Developers pointing at `https://your-app.com/outbox/webhook` and set its secret:

```dotenv
OUTBOX_WEBHOOK_SECRET=whsec_...
```

The package registers `POST /outbox/webhook` (only when the secret is set), rejects bad signatures with 400, and dispatches events:

```php
use Outbox\Laravel\Events\MessageBounced;

Event::listen(function (MessageBounced $event) {
    User::where('email', $event->email())->update(['email_bounced_at' => now()]);
    $orderId = $event->metadata()['order_id'] ?? null;
});
```

Events: `MessageSent`, `MessageDelivered`, `MessageBounced`, `MessageComplained`, `MessageFailed`, `MessageOpened`, `MessageClicked`, `ContactSubscribed`, `ContactUnsubscribed`, plus `WebhookReceived` for every event. Each has `type()`, `data()`, `messageId()`, `email()`, `metadata()` and the raw `payload`. The route has no CSRF or session middleware; requests are authenticated by signature.

## Using the API directly

The facade is the full PHP client: messages (with paging and search), cancel and suppressions. See the [outbox-php README](https://github.com/Outbox-Stack/outbox-php#readme).

```php
use Outbox\Laravel\Facades\Outbox;

Outbox::send(['from' => 'app@your-verified-domain.com', 'to' => 'ada@example.com', 'subject' => 'Hi', 'text' => 'Hello']);
Outbox::getMessage($id);
Outbox::cancelMessage($id);                       // queued messages only
foreach (Outbox::iterateMessages(status: 'bounced') as $m) { /* … */ }
Outbox::addSuppression('ada@example.com');
```

## Configuration

`php artisan vendor:publish --tag=outbox-config` publishes `config/outbox.php`: `OUTBOX_BASE_URL`, `OUTBOX_TIMEOUT`, `OUTBOX_MAX_RETRIES`, `OUTBOX_WEBHOOK_PATH` and webhook middleware. A mailer entry can carry its own `api_key` to send from another workspace.

## Development

```sh
composer install
composer test
composer analyse
```
