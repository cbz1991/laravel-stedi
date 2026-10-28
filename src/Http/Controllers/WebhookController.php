<?php

namespace Cabuz\Stedi\Http\Controllers;

use Cabuz\Stedi\Events\WebhookReceived;
use Cabuz\Stedi\Exceptions\InvalidWebhookSignatureException;
use Cabuz\Stedi\Webhooks\WebhookSignature;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Receives Stedi event destination deliveries: verifies the signature,
 * drops redelivered events and fires WebhookReceived.
 */
class WebhookController
{
    public function __invoke(Request $request, Cache $cache, Dispatcher $events): Response
    {
        $config = (array) config('stedi.webhook', []);
        $secrets = array_values(array_filter(array_map('trim', explode(',', (string) ($config['secret'] ?? '')))));

        if ($secrets === []) {
            throw new RuntimeException('Set STEDI_WEBHOOK_SECRET to receive Stedi webhooks.');
        }

        $payload = $request->getContent();
        $id = (string) $request->header('webhook-id', '');

        try {
            WebhookSignature::verify(
                $payload,
                $id,
                (string) $request->header('webhook-timestamp', ''),
                (string) $request->header('webhook-signature', ''),
                $secrets,
                (int) ($config['tolerance'] ?? 300),
            );
        } catch (InvalidWebhookSignatureException) {
            return new Response('Invalid signature.', 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return new Response('Invalid payload.', 400);
        }

        // Stedi retries until it gets a 2xx; acknowledge repeats without firing
        // again. The ID is stored only after the listeners ran, so a failed
        // delivery is processed again on retry.
        if (! (bool) ($config['deduplicate'] ?? true)) {
            $events->dispatch(new WebhookReceived($event, $id));

            return new Response('', 200);
        }

        $key = 'stedi-webhook:'.$id;
        $store = $cache->getStore();

        // While another delivery of the same event is being handled, ask Stedi
        // to retry later instead of firing the listeners twice.
        $lock = $store instanceof LockProvider ? $store->lock($key.':lock', 120) : null;

        if ($lock !== null && ! $lock->get()) {
            return new Response('Delivery in progress.', 409);
        }

        try {
            if ($cache->has($key)) {
                return new Response('', 200);
            }

            $events->dispatch(new WebhookReceived($event, $id));

            $cache->put($key, true, now()->addDays(3));
        } finally {
            $lock?->release();
        }

        return new Response('', 200);
    }
}
