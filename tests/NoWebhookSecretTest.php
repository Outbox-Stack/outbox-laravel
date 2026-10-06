<?php

declare(strict_types=1);

namespace Outbox\Laravel\Tests;

final class NoWebhookSecretTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('outbox.webhook.secret', null);
    }

    public function testRouteIsNotRegisteredWithoutASecret(): void
    {
        $this->post('/outbox/webhook')->assertNotFound();
    }
}
