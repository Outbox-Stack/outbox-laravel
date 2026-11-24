# Changelog

## 0.4.0

- Requires `getoutbox/outbox-php` ^0.3. The `Outbox` facade's message list methods take `stream`.
- New `MessageSuppressed` event for `message.suppressed` webhooks: the message was held back and never sent. `data()['reason']` is a suppression reason or `known_dead_address`; `data()['description']` says why.

## 0.3.0

- New `MessageDeferred` event for `message.deferred` webhooks (soft bounces and delivery delays). Before, these events weren't sent at all; don't treat them as bounces.

## 0.2.0

- Requires `getoutbox/outbox-php` ^0.2. The `Outbox` facade now covers message paging (`listMessagesPage`, `iterateMessages`, `to` and `after` filters), `cancelMessage`, and the suppression methods.
- Mailables without a subject, or without an HTML or text body, now fail before the API is called instead of being rejected by it.

## 0.1.0

First release: the `outbox` mail transport, webhook route and events, and the `Outbox` facade.
