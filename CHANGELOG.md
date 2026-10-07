# Changelog

## 0.2.0

- Requires `getoutbox/outbox-php` ^0.2. The `Outbox` facade now covers message paging (`listMessagesPage`, `iterateMessages`, `to` and `after` filters), `cancelMessage`, and the suppression methods.
- Mailables without a subject, or without an HTML or text body, now fail before the API is called instead of being rejected by it.

## 0.1.0

First release: the `outbox` mail transport, webhook route and events, and the `Outbox` facade.
