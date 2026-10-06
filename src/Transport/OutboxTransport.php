<?php

declare(strict_types=1);

namespace Outbox\Laravel\Transport;

use Outbox\Client;
use Outbox\Exception\ApiException;
use Outbox\Exception\OutboxException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/** Sends Laravel mail through the Outbox Stack API (MAIL_MAILER=outbox). */
final class OutboxTransport extends AbstractTransport
{
    public const IDEMPOTENCY_HEADER = 'X-Outbox-Idempotency-Key';

    /** Headers the API derives from message fields, or that SMTP-era code adds and we must not forward. */
    private const SKIPPED_HEADERS = ['from', 'to', 'cc', 'bcc', 'reply-to', 'sender', 'subject', 'date', 'message-id', 'mime-version', 'content-type', 'content-transfer-encoding', 'content-disposition', 'return-path'];

    public function __construct(private readonly Client $client)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (!$original instanceof Message) {
            throw new TransportException('Outbox sends structured messages; a pre-rendered raw message cannot be converted');
        }
        $email = MessageConverter::toEmail($original);
        [$payload, $idempotencyKey] = $this->payload($email);
        try {
            $result = $this->client->send($payload, $idempotencyKey);
        } catch (ApiException $e) {
            throw new TransportException(sprintf('Outbox API rejected the message (%d %s): %s [request %s]', $e->status(), $e->errorCode(), $e->getMessage(), $e->requestId() ?? 'n/a'), 0, $e);
        } catch (OutboxException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
        $message->setMessageId($result['id']);
        $email->getHeaders()->addTextHeader('X-Outbox-Message-Id', $result['id']);
    }

    /** @return array{0: array<string, mixed>, 1: string|null} */
    public function payload(Email $email): array
    {
        $from = $email->getFrom()[0] ?? null;
        if ($from === null) {
            throw new TransportException('The message has no From address');
        }
        $payload = array_filter([
            'from' => $from->getAddress(),
            'fromName' => $from->getName(),
            'to' => self::addresses($email->getTo()),
            'cc' => self::addresses($email->getCc()),
            'bcc' => self::addresses($email->getBcc()),
            'replyTo' => ($email->getReplyTo()[0] ?? null)?->getAddress(),
            'subject' => $email->getSubject(),
            'html' => self::body($email->getHtmlBody()),
            'text' => self::body($email->getTextBody()),
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        $attachments = [];
        foreach ($email->getAttachments() as $i => $part) {
            $attachments[] = self::attachment($part, $i);
        }
        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        $headers = [];
        $metadata = [];
        $idempotencyKey = null;
        foreach ($email->getHeaders()->all() as $header) {
            if (!$header instanceof HeaderInterface) {
                continue;
            }
            $name = $header->getName();
            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
            } elseif ($header instanceof TagHeader) {
                continue; // The API has no tags; use ->metadata() instead.
            } elseif (strcasecmp($name, self::IDEMPOTENCY_HEADER) === 0) {
                $idempotencyKey = $header->getBodyAsString();
            } elseif (!in_array(strtolower($name), self::SKIPPED_HEADERS, true)) {
                $headers[$name] = $header->getBodyAsString();
            }
        }
        if ($headers !== []) {
            $payload['headers'] = $headers;
        }
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }
        return [$payload, $idempotencyKey];
    }

    /**
     * @param Address[] $list
     * @return list<string>
     */
    private static function addresses(array $list): array
    {
        return array_values(array_map(static function (Address $a): string {
            $name = $a->getName();
            if ($name === '') {
                return $a->getAddress();
            }
            return '"' . addcslashes($name, '"\\') . '" <' . $a->getAddress() . '>';
        }, $list));
    }

    /** @param resource|string|null $body */
    private static function body(mixed $body): ?string
    {
        if (is_resource($body)) {
            $contents = stream_get_contents($body);
            return $contents === false ? null : $contents;
        }
        return is_string($body) ? $body : null;
    }

    /** @return array<string, string> */
    private static function attachment(DataPart $part, int $index): array
    {
        $file = [
            'filename' => $part->getFilename() ?? 'attachment-' . ($index + 1),
            'content' => $part->getBody(),
            'contentType' => $part->getMediaType() . '/' . $part->getMediaSubtype(),
        ];
        if ($part->getDisposition() === 'inline') {
            $file['contentId'] = $part->getContentId();
        }
        return $file;
    }

    public function __toString(): string
    {
        return 'outbox';
    }
}
