<?php

namespace Cabuz\Stedi;

use Cabuz\Stedi\Concerns\Claims;
use Cabuz\Stedi\Concerns\Core;
use Cabuz\Stedi\Concerns\EligibilityManager;
use Cabuz\Stedi\Concerns\Enrollment;
use Cabuz\Stedi\Concerns\EventDestinations;
use Cabuz\Stedi\Concerns\Healthcare;
use Cabuz\Stedi\Concerns\Payers;
use Cabuz\Stedi\Exceptions\ConnectionException;
use Cabuz\Stedi\Exceptions\MissingApiKeyException;
use Cabuz\Stedi\Exceptions\StediException;
use Cabuz\Stedi\Support\CursorPage;
use Composer\InstalledVersions;
use Illuminate\Container\Container;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Log\LogManager;
use InvalidArgumentException;
use stdClass;
use Throwable;

/**
 * One client for every Stedi API, like the official SDK: each operation is a
 * method named after its OpenAPI operation and routed to the right host.
 */
class Stedi
{
    use Claims;
    use Core;
    use EligibilityManager;
    use Enrollment;
    use EventDestinations;
    use Healthcare;
    use Payers;

    /**
     * Base URL of each Stedi API, including its version.
     */
    public const ENDPOINTS = [
        'healthcare' => 'https://healthcare.us.stedi.com/2024-04-01',
        'claims' => 'https://claims.us.stedi.com/2025-03-07',
        'manager' => 'https://manager.us.stedi.com/2024-04-01',
        'payers' => 'https://payers.us.stedi.com/2024-04-01',
        'enrollment' => 'https://enrollments.us.stedi.com/2024-09-01',
        'events' => 'https://events.us.stedi.com/2026-02-01',
        'core' => 'https://core.us.stedi.com/2023-08-01',
    ];

    /**
     * The raw response of the most recent request made by this client.
     */
    protected ?Response $lastResponse = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected HttpFactory $http,
        protected array $config = [],
    ) {}

    /**
     * A copy of the client that uses another API key (for example per tenant).
     */
    public function withApiKey(string $apiKey): static
    {
        return $this->withConfig(['api_key' => $apiKey]);
    }

    /**
     * A copy of the client that sends these headers with every request.
     *
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        return $this->withConfig(['headers' => array_replace((array) $this->config('headers', []), $headers)]);
    }

    /**
     * A copy of the client with another request timeout, in seconds.
     */
    public function withTimeout(int $seconds): static
    {
        return $this->withConfig(['timeout' => $seconds]);
    }

    /**
     * A copy of the client with another number of retries (0 disables them).
     */
    public function withRetries(int $retries): static
    {
        return $this->withConfig(['retries' => $retries]);
    }

    /**
     * A copy of the client with some config values replaced.
     *
     * @param  array<string, mixed>  $config
     */
    public function withConfig(array $config): static
    {
        $clone = clone $this;
        $clone->config = array_replace($this->config, $config);
        $clone->lastResponse = null;

        return $clone;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * The raw response (status, headers, body) of this client's most recent
     * request. Failed requests set it too, before the exception is thrown.
     */
    public function lastResponse(): ?Response
    {
        return $this->lastResponse;
    }

    /**
     * The base URL of a Stedi API ("healthcare", "claims", "manager",
     * "payers", "enrollment", "events" or "core").
     */
    public function endpoint(string $api): string
    {
        $endpoint = ((array) $this->config('endpoints', []))[$api] ?? static::ENDPOINTS[$api] ?? null;

        if (! is_string($endpoint) || $endpoint === '') {
            throw new InvalidArgumentException("Unknown Stedi API [{$api}].");
        }

        return rtrim($endpoint, '/');
    }

    // ------------------------------------------------------------------
    // Raw requests
    // ------------------------------------------------------------------

    /**
     * Send a request and return the decoded JSON body.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $data  null sends no body; [] sends {}.
     * @return array<string, mixed>
     *
     * @throws StediException
     */
    public function request(string $api, string $method, string $path, array $query = [], ?array $data = null, ?string $idempotencyKey = null): array
    {
        $json = $this->send($api, $method, $path, $query, $data, $idempotencyKey)->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Fetch the first page of a paginated list.
     *
     * @param  array<string, mixed>  $query
     */
    public function paginate(string $api, string $method, string $path, array $query = []): CursorPage
    {
        return new CursorPage($this, $api, $path, $query, $this->request($api, $method, $path, $query));
    }

    /**
     * Send a request and return the raw response (for PDFs, CSVs and other
     * documents). Failed responses still throw.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $data
     *
     * @throws StediException
     */
    public function send(string $api, string $method, string $path, array $query = [], ?array $data = null, ?string $idempotencyKey = null): Response
    {
        $method = strtoupper($method);
        $url = $this->endpoint($api).'/'.ltrim($path, '/');
        $queryString = static::buildQuery($query);

        if ($queryString !== '') {
            $url .= '?'.$queryString;
        }

        $request = $this->pendingRequest($method, $idempotencyKey !== null);

        if ($idempotencyKey !== null) {
            $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $options = $data === null ? [] : ['json' => $data === [] ? new stdClass : $data];
        $started = hrtime(true);

        try {
            $response = $this->lastResponse = $request->send($method, $url, $options);
        } catch (HttpConnectionException $e) {
            $this->log('error', "Stedi API {$method} {$api}{$path} could not connect", ['error' => $e->getMessage()]);

            throw new ConnectionException(sprintf('Could not reach the Stedi API (%s %s): %s', $method, $url, $e->getMessage()), 0, $e);
        }

        $context = [
            'status' => $response->status(),
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ];

        if ($response->failed()) {
            $this->log($response->serverError() ? 'error' : 'warning', "Stedi API {$method} {$api}{$path} failed with {$response->status()}", $context);

            throw StediException::fromResponse($response, $method, $url);
        }

        $this->log('debug', "Stedi API {$method} {$api}{$path} {$response->status()}", $context);

        return $response;
    }

    /**
     * The configured HTTP request, before the method and URL are applied.
     *
     * @param  bool  $idempotent  Whether the request may be repeated after a
     *                            server or connection error (reads, and writes
     *                            sent with an idempotency key).
     */
    public function pendingRequest(string $method = 'GET', bool $idempotent = false): PendingRequest
    {
        $apiKey = $this->config('api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw MissingApiKeyException::make();
        }

        $request = $this->http
            ->acceptJson()
            ->asJson()
            ->withUserAgent('cabuz/laravel-stedi/'.static::version())
            ->withHeaders((array) $this->config('headers', []))
            ->withHeaders(['Authorization' => $apiKey])
            ->timeout((int) $this->config('timeout', 60));

        $retries = max(0, (int) $this->config('retries', 2));

        if ($retries === 0) {
            return $request;
        }

        $delay = max(0, (int) $this->config('retry_delay', 500));
        $idempotent = $idempotent || in_array($method, ['GET', 'HEAD'], true);

        return $request->retry(
            $retries + 1,
            static function (int $attempt, ?Throwable $e = null) use ($delay): int {
                $retryAfter = $e instanceof RequestException ? $e->response->header('Retry-After') : '';

                if (is_numeric($retryAfter)) {
                    return min((int) $retryAfter, 60) * 1000;
                }

                return $delay * (2 ** ($attempt - 1));
            },
            function (Throwable $e) use ($idempotent, $method): bool {
                $status = $e instanceof RequestException ? $e->response->status() : null;
                $retry = match (true) {
                    $e instanceof HttpConnectionException => $idempotent,
                    $status !== null => $status === 429 || ($idempotent && $status >= 500),
                    default => false,
                };

                if ($retry) {
                    $this->log('info', "Retrying Stedi API {$method} request", ['status' => $status]);
                }

                return $retry;
            },
            throw: false,
        );
    }

    /**
     * Build a URL-encoded path, encoding every parameter.
     */
    protected function path(string $format, string|int ...$params): string
    {
        return vsprintf($format, array_map(static fn ($param) => rawurlencode((string) $param), $params));
    }

    /**
     * Stedi expects list filters as repeated parameters (status=A&status=B)
     * and booleans as "true"/"false"; null values and empty lists are dropped.
     *
     * @param  array<string, mixed>  $query
     */
    public static function buildQuery(array $query): string
    {
        $pairs = [];

        foreach ($query as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $item = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
                $pairs[] = rawurlencode((string) $key).'='.rawurlencode($item);
            }
        }

        return implode('&', $pairs);
    }

    /**
     * Write to the log when the "log" config level allows it. Request and
     * response bodies, query strings and the API key are never logged: they
     * can hold protected health information.
     *
     * @param  'debug'|'info'|'warning'|'error'  $level
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $threshold = strtolower((string) $this->config('log', ''));
        $threshold = $threshold === 'warn' ? 'warning' : $threshold;

        if (! isset($levels[$threshold]) || $levels[$level] < $levels[$threshold]) {
            return;
        }

        $container = Container::getInstance();

        if (! $container->bound('log')) {
            return;
        }

        $channel = $this->config('log_channel');

        /** @var LogManager $logger */
        $logger = $container->make('log');
        $logger->channel(is_string($channel) && $channel !== '' ? $channel : null)->log($level, $message, $context);
    }

    /**
     * The installed package version, for the User-Agent header.
     */
    public static function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('cabuz/laravel-stedi') ?? 'dev';
        } catch (Throwable) {
            return 'dev';
        }
    }
}
