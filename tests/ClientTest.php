<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Exceptions\AuthenticationException;
use Cabuz\Stedi\Exceptions\ConflictException;
use Cabuz\Stedi\Exceptions\ConnectionException;
use Cabuz\Stedi\Exceptions\InvalidRequestException;
use Cabuz\Stedi\Exceptions\MissingApiKeyException;
use Cabuz\Stedi\Exceptions\NotFoundException;
use Cabuz\Stedi\Exceptions\PermissionDeniedException;
use Cabuz\Stedi\Exceptions\RateLimitException;
use Cabuz\Stedi\Exceptions\ServerException;
use Cabuz\Stedi\Exceptions\StediException;
use Cabuz\Stedi\Facades\Stedi;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;

class ClientTest extends TestCase
{
    public function test_requests_go_to_the_api_host_with_the_raw_api_key(): void
    {
        Http::fake(['*' => Http::response(['controlNumber' => '123'])]);

        $result = Stedi::createEligibilityCheck(['tradingPartnerServiceId' => '60054']);

        $this->assertSame(['controlNumber' => '123'], $result);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://healthcare.us.stedi.com/2024-04-01/eligibility-check'
            && $request->header('Authorization') === ['test_key']
            && str_starts_with($request->header('User-Agent')[0], 'cabuz/laravel-stedi/')
            && $request->data() === ['tradingPartnerServiceId' => '60054']);
    }

    public function test_list_filters_are_sent_as_repeated_parameters(): void
    {
        Http::fake(['*' => Http::response(['items' => []])]);

        Stedi::searchPayers(['query' => 'blue cross', 'coverageTypes' => ['medical', 'dental'], 'pageSize' => 10, 'skip' => null]);
        Stedi::getElectronicRemittanceAdvicePdf('t 1', ['logo' => false]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://payers.us.stedi.com/2024-04-01/payers/search?query=blue%20cross&coverageTypes=medical&coverageTypes=dental&pageSize=10');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://healthcare.us.stedi.com/2024-04-01/electronic-remittance-advice/t%201/pdf?logo=false');
    }

    public function test_claim_submissions_get_an_idempotency_key_and_are_retried_after_server_errors(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['message' => 'busy'], 503)
            ->push(['claimId' => 'c1'])
            ->push(['claimId' => 'c2']),
        ]);

        $this->assertSame(['claimId' => 'c1'], Stedi::createProfessionalClaimSubmission(['claimInformation' => []]));
        Stedi::createProfessionalClaimSubmission(['claimInformation' => []], 'my-key');

        $keys = Http::recorded()->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0] ?? null)->all();
        $this->assertCount(3, $keys);
        $this->assertSame($keys[0], $keys[1], 'The retry reuses the same key.');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $keys[0]);
        $this->assertSame('my-key', $keys[2]);
    }

    public function test_writes_without_an_idempotency_key_are_not_retried_after_server_errors(): void
    {
        Http::fake(['*' => Http::response(['message' => 'busy'], 503)]);

        try {
            Stedi::createEligibilityCheck(['x' => 1]);
            $this->fail('Expected a ServerException.');
        } catch (ServerException $e) {
            $this->assertSame(503, $e->status());
        }

        Http::assertSentCount(1);
    }

    public function test_reads_are_retried_after_server_errors_and_rate_limits(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['message' => 'slow down'], 429)
            ->push(['message' => 'busy'], 502)
            ->push(['claimId' => 'c1']),
        ]);

        $this->assertSame(['claimId' => 'c1'], Stedi::getClaim('c1'));
        Http::assertSentCount(3);
    }

    public function test_errors_expose_the_stedi_message_code_and_field_errors(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['message' => '1 validation error detected', 'fieldList' => [['path' => '/pageSize', 'message' => 'too small']]], 400)
            ->push(['code' => 'access_denied', 'message' => 'Not available in Test Mode.'], 403)
            ->push(['message' => 'Unauthorized'], 401),
        ]);

        $e = $this->catch(fn () => Stedi::listClaims(['pageSize' => 1]), InvalidRequestException::class);
        $this->assertSame('1 validation error detected', $e->error());
        $this->assertSame([['path' => '/pageSize', 'message' => 'too small']], $e->fieldErrors());
        $this->assertSame(400, $e->status());

        $e = $this->catch(fn () => Stedi::listProviders(), PermissionDeniedException::class);
        $this->assertSame('access_denied', $e->errorCode());

        $this->catch(fn () => Stedi::getClaim('c1'), AuthenticationException::class);
    }

    public function test_every_status_maps_to_its_exception(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['message' => 'Not found'], 404)
            ->push(['message' => 'Already exists'], 409)
            ->push(['message' => 'slow down'], 429, ['Retry-After' => '7'])
            ->push('<html>Bad gateway</html>', 502),
        ]);
        $stedi = Stedi::withRetries(0);

        $this->catch(fn () => $stedi->getClaim('c1'), NotFoundException::class);
        $this->catch(fn () => $stedi->createEnrollment([]), ConflictException::class);
        $this->assertSame(7, $this->catch(fn () => $stedi->getClaim('c1'), RateLimitException::class)->retryAfter());

        $e = $this->catch(fn () => $stedi->getClaim('c1'), ServerException::class);
        $this->assertNull($e->error());
        $this->assertStringContainsString('failed with 502: Bad Gateway', $e->getMessage());
    }

    public function test_field_errors_are_read_from_the_errors_list_too(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalid request', 'errors' => [
            ['path' => '/subscriber/memberId', 'message' => 'is required'],
            ['code' => '79', 'description' => 'Invalid Participant Identification'],
        ]], 400)]);

        $e = $this->catch(fn () => Stedi::createEligibilityCheck([]), InvalidRequestException::class);

        $this->assertSame([['path' => '/subscriber/memberId', 'message' => 'is required']], $e->fieldErrors());
    }

    public function test_connection_errors_are_wrapped(): void
    {
        Http::fake(fn () => throw new HttpConnectionException('timed out'));

        $this->expectException(ConnectionException::class);

        Stedi::withRetries(0)->getClaim('c1');
    }

    public function test_a_missing_api_key_throws(): void
    {
        $this->app['config']->set('stedi.api_key', null);
        $this->app->forgetInstance(\Cabuz\Stedi\Stedi::class);
        Stedi::clearResolvedInstances();

        $this->expectException(MissingApiKeyException::class);

        Stedi::getClaim('c1');
    }

    public function test_with_api_key_headers_and_endpoint_overrides(): void
    {
        Http::fake(['*' => Http::response([])]);

        Stedi::withApiKey('tenant-key')->withHeaders(['X-Trace' => 'abc'])->getClaim('c1');
        Stedi::withConfig(['endpoints' => ['claims' => 'https://proxy.test/claims/']])->getClaim('c2');
        Stedi::getClaim('c3');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'tenant-key') && $request->hasHeader('X-Trace', 'abc'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://proxy.test/claims/claims/c2');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/claims/c3') && $request->hasHeader('Authorization', 'test_key'));
    }

    public function test_file_downloads_return_the_raw_body(): void
    {
        Http::fake(['*' => Http::response('%PDF-1.7', 200, ['Content-Type' => 'application/pdf'])]);

        $this->assertSame('%PDF-1.7', Stedi::getEligibilityCheckPdf('e1'));
        $this->assertSame(200, Stedi::lastResponse()?->status());
    }

    public function test_logging_never_includes_bodies_or_queries(): void
    {
        $this->app['config']->set('stedi.log', 'debug');
        $this->app->forgetInstance(\Cabuz\Stedi\Stedi::class);
        Stedi::clearResolvedInstances();
        Log::spy()->shouldReceive('channel')->andReturnSelf();
        Http::fake(['*' => Http::response([])]);

        Stedi::searchPayers(['query' => 'patient-name']);

        Log::shouldHaveReceived('log')->with('debug', 'Stedi API GET payers/payers/search 200', Mockery::on(
            fn (array $context) => array_keys($context) === ['status', 'duration_ms'],
        ));
    }

    public function test_retry_after_is_capped_by_the_max_delay(): void
    {
        $this->app['config']->set('stedi.retry_max_delay', 50);
        $this->app->forgetInstance(\Cabuz\Stedi\Stedi::class);
        Stedi::clearResolvedInstances();
        Http::fake(['*' => Http::sequence()
            ->push(['message' => 'slow down'], 429, ['Retry-After' => '120'])
            ->push(['items' => []]),
        ]);

        $started = microtime(true);
        Stedi::searchPayers();

        $this->assertLessThan(5, microtime(true) - $started);
        Http::assertSentCount(2);
    }

    public function test_each_scope_gets_a_fresh_client(): void
    {
        Http::fake(['*' => Http::response(['claimId' => 'c1'])]);

        Stedi::getClaim('c1');
        $this->assertNotNull(Stedi::lastResponse());

        // What Octane and the queue worker do between requests and jobs.
        $this->app->forgetScopedInstances();
        Stedi::clearResolvedInstances();

        $this->assertNull(Stedi::lastResponse());
    }

    /**
     * @template T of StediException
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function catch(callable $callback, string $class): StediException
    {
        try {
            $callback();
        } catch (StediException $e) {
            $this->assertInstanceOf($class, $e);

            return $e;
        }

        $this->fail("Expected a {$class}.");
    }
}
