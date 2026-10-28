<?php

namespace Cabuz\Stedi\Webhooks;

use Cabuz\Stedi\Exceptions\InvalidWebhookSignatureException;

/**
 * Stedi signs event destination payloads with the Standard Webhooks scheme:
 * base64(HMAC-SHA256(secret, "{webhook-id}.{webhook-timestamp}.{body}")),
 * sent as "v1,<signature>" in the webhook-signature header.
 */
class WebhookSignature
{
    /**
     * @param  string|list<string>  $secrets  One secret, or several while rotating ("whsec_...").
     * @param  int  $tolerance  Seconds the timestamp may differ from now.
     *
     * @throws InvalidWebhookSignatureException
     */
    public static function verify(
        string $payload,
        string $id,
        string $timestamp,
        string $signatures,
        string|array $secrets,
        int $tolerance = 300,
        ?int $now = null,
    ): void {
        if ($id === '' || $signatures === '' || ! ctype_digit($timestamp)) {
            throw InvalidWebhookSignatureException::because('missing or malformed webhook headers');
        }

        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            throw InvalidWebhookSignatureException::because('the timestamp is outside the tolerance');
        }

        foreach ((array) $secrets as $secret) {
            if ($secret === '') {
                continue;
            }

            $expected = static::sign($payload, $id, (int) $timestamp, $secret);

            foreach (explode(' ', $signatures) as $signature) {
                if (hash_equals($expected, $signature)) {
                    return;
                }
            }
        }

        throw InvalidWebhookSignatureException::because('no signature matches');
    }

    /**
     * The "v1,<signature>" value for a payload; useful to build signed
     * requests in your tests.
     */
    public static function sign(string $payload, string $id, int $timestamp, string $secret): string
    {
        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);

        if ($key === false || $key === '') {
            throw InvalidWebhookSignatureException::because('the webhook secret is not valid base64');
        }

        return 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$payload}", $key, true));
    }
}
