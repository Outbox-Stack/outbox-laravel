<?php

declare(strict_types=1);

namespace Outbox\Laravel\Tests;

use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Outbox\Laravel\Facades\Outbox;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Part\DataPart;

final class MailTransportTest extends TestCase
{
    private const ID = '11111111-1111-4111-8111-111111111111';

    public function testLaravelMailBecomesOneApiCallWithEveryField(): void
    {
        $this->fakeApi([new Response(202, [], json_encode(['id' => self::ID, 'status' => 'queued']))]);
        $sent = Mail::html('<p>Hi <img src="cid:logo@test"></p>', function (Message $m): void {
            $m->from('billing@example.com', 'Obi, "Billing"')
                ->to('ada@example.com', 'Ada')->to('bo@example.com')
                ->cc('cy@example.com')->bcc('archive@example.com')
                ->replyTo('help@example.com')->subject('Your invoice')
                ->attachData('%PDF', 'invoice.pdf', ['mime' => 'application/pdf']);
            $symfony = $m->getSymfonyMessage();
            $symfony->addPart((new DataPart('PNG', 'logo.png', 'image/png'))->asInline()->setContentId('logo@test'));
            $symfony->getHeaders()->add(new MetadataHeader('order_id', '42'));
            $symfony->getHeaders()->addTextHeader('X-Entity-Ref', 'inv-1');
            $symfony->getHeaders()->addTextHeader('X-Outbox-Idempotency-Key', 'invoice-42');
        });

        self::assertSame(self::ID, $sent?->getMessageId());
        self::assertCount(1, $this->history);
        self::assertSame('invoice-42', $this->history[0]['request']->getHeaderLine('Idempotency-Key'));
        $body = $this->sentPayload();
        self::assertSame('billing@example.com', $body['from']);
        self::assertSame('Obi, "Billing"', $body['fromName']);
        self::assertSame(['"Ada" <ada@example.com>', 'bo@example.com'], $body['to']);
        self::assertSame(['cy@example.com'], $body['cc']);
        self::assertSame(['archive@example.com'], $body['bcc']);
        self::assertSame('help@example.com', $body['replyTo']);
        self::assertSame('Your invoice', $body['subject']);
        self::assertStringContainsString('cid:logo@test', $body['html']);
        self::assertSame(['order_id' => '42'], $body['metadata']);
        self::assertSame(['X-Entity-Ref' => 'inv-1'], $body['headers'], 'idempotency and standard headers must not be forwarded');
        $files = array_column($body['attachments'], null, 'filename');
        self::assertSame(['filename' => 'invoice.pdf', 'content' => base64_encode('%PDF'), 'contentType' => 'application/pdf'], $files['invoice.pdf']);
        self::assertSame('logo@test', $files['logo.png']['contentId']);
    }

    public function testApiErrorsSurfaceAsTransportExceptionsWithCode(): void
    {
        $this->fakeApi([new Response(422, [], json_encode(['error' => 'verify example.com', 'code' => 'domain_not_verified', 'request_id' => 'req_9']))]);
        try {
            Mail::raw('Hi', fn (Message $m) => $m->from('a@example.com')->to('b@example.com')->subject('s'));
            self::fail('expected TransportException');
        } catch (TransportException $e) {
            self::assertStringContainsString('domain_not_verified', $e->getMessage());
            self::assertStringContainsString('req_9', $e->getMessage());
        }
    }

    public function testFacadeExposesTheClient(): void
    {
        $this->fakeApi([new Response(200, [], json_encode(['messages' => [['id' => self::ID]]]))]);
        self::assertSame([['id' => self::ID]], Outbox::listMessages(limit: 5));
    }
}
