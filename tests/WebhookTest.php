<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Events\WebhookReceived;
use Cabuz\Stedi\Exceptions\InvalidWebhookSignatureException;
use Cabuz\Stedi\Webhooks\WebhookSignature;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use RuntimeException;

class WebhookTest extends TestCase
{
    // A random secret in Stedi's format (whsec_ + base64 of 32 bytes).
    private const SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSwq5Tk1Zg7Lfw=';

    private const EVENT = [
        'account' => 'acc_1',
        'created' => '2026-10-28T12:00:00Z',
        'environment' => 'PRODUCTION',
        'id' => 'evt_a81659f1-16a5-9bec-03e1-0ba8ab5e9652',
        'object' => 'v1.event',
        'resource' => ['id' => 'enr_1', 'type' => 'enrollment'],
        'type' => 'enrollment.activated',
    ];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('stedi.webhook.secret', self::SECRET);
    }

    public function test_signatures_match_the_standard_webhooks_reference_vector(): void
    {
        $this->assertSame(
            'v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=',
            WebhookSignature::sign('{"test": 2432232314}', 'msg_p5jXN8AQM9LWM0D4loKWxJek', 1614265330, 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw'),
        );

        WebhookSignature::verify(
            '{"test": 2432232314}', 'msg_p5jXN8AQM9LWM0D4loKWxJek', '1614265330',
            'v1,bogus v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=',
            ['whsec_cmV0aXJlZA==', 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw'],
            now: 1614265330,
        );
        $this->addToAssertionCount(1);
    }

    public function test_stale_timestamps_are_rejected(): void
    {
        $signature = WebhookSignature::sign('{}', 'msg_1', 1000, self::SECRET);

        $this->expectException(InvalidWebhookSignatureException::class);

        WebhookSignature::verify('{}', 'msg_1', '1000', $signature, self::SECRET, tolerance: 300, now: 1400);
    }

    public function test_a_signed_delivery_fires_webhook_received(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->deliver(self::EVENT)->assertOk();

        Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event) => $event->type() === 'enrollment.activated'
            && $event->is('enrollment.*')
            && $event->resourceId() === 'enr_1'
            && $event->resourceType() === 'enrollment'
            && $event->isProduction()
            && $event->webhookId === 'msg_1');
    }

    public function test_tampered_or_unsigned_deliveries_are_rejected(): void
    {
        Event::fake([WebhookReceived::class]);
        $body = (string) json_encode(self::EVENT);

        $this->call('POST', '/stedi/webhook', [], [], [], $this->headers('msg_1', time(), $body), $body.' ')->assertStatus(400);
        $this->call('POST', '/stedi/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400);

        Event::assertNotDispatched(WebhookReceived::class);
    }

    public function test_redeliveries_are_acknowledged_without_firing_again(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->deliver(self::EVENT, 'msg_1')->assertOk();
        $this->deliver(self::EVENT, 'msg_1')->assertOk();
        $this->deliver(self::EVENT, 'msg_2')->assertOk();

        Event::assertDispatchedTimes(WebhookReceived::class, 2);
    }

    public function test_a_failed_listener_lets_stedi_retry_the_delivery(): void
    {
        $calls = 0;
        Event::listen(WebhookReceived::class, function () use (&$calls) {
            if (++$calls === 1) {
                throw new RuntimeException('database down');
            }
        });

        $this->deliver(self::EVENT, 'msg_1')->assertStatus(500);
        $this->deliver(self::EVENT, 'msg_1')->assertOk();

        $this->assertSame(2, $calls);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function deliver(array $event, string $id = 'msg_1'): TestResponse
    {
        $body = (string) json_encode($event);

        return $this->call('POST', '/stedi/webhook', [], [], [], $this->headers($id, time(), $body), $body);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $id, int $timestamp, string $body): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $id,
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => WebhookSignature::sign($body, $id, $timestamp, self::SECRET),
        ];
    }
}
