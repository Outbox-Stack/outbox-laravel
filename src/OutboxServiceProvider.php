<?php

declare(strict_types=1);

namespace Outbox\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Outbox\Client;
use Outbox\Laravel\Http\WebhookController;
use Outbox\Laravel\Transport\OutboxTransport;

final class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/outbox.php', 'outbox');

        $this->app->singleton(Client::class, static function (Application $app): Client {
            $config = $app->make(Repository::class);
            return new Client(
                apiKey: self::string($config->get('outbox.api_key')),
                baseUrl: self::string($config->get('outbox.base_url')),
                timeout: (float) self::scalar($config->get('outbox.timeout', 10)),
                maxRetries: (int) self::scalar($config->get('outbox.max_retries', 2)),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/outbox.php' => $this->app->configPath('outbox.php')], 'outbox-config');

        $this->callAfterResolving(MailManager::class, function (MailManager $mail): void {
            // A mailer entry may carry its own key: 'outbox' => ['transport' => 'outbox', 'api_key' => ...].
            $mail->extend('outbox', fn (array $config) => new OutboxTransport(isset($config['api_key'])
                ? new Client(apiKey: self::string($config['api_key']), baseUrl: self::string($config['base_url'] ?? null))
                : $this->app->make(Client::class)));
        });

        $config = $this->app->make(Repository::class);
        if (self::string($config->get('outbox.webhook.secret')) !== null) {
            /** @var array<int, string> $middleware */
            $middleware = (array) $config->get('outbox.webhook.middleware', []);
            Route::post(self::string($config->get('outbox.webhook.path')) ?? 'outbox/webhook', WebhookController::class)
                ->middleware($middleware)
                ->name('outbox.webhook');
        }
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function scalar(mixed $value): int|float|string
    {
        return is_int($value) || is_float($value) || is_string($value) ? $value : 0;
    }
}
