<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Facades\Stedi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\ExpectationFailedException;

class FakeAndCommandsTest extends TestCase
{
    public function test_fake_stubs_api_relative_paths_and_swallows_the_rest(): void
    {
        Stedi::fake([
            'healthcare/eligibility-check' => ['controlNumber' => '1'],
            'payers/payers/search*' => ['items' => [['payer' => ['displayName' => 'Aetna']]], 'nextPageToken' => null],
        ]);

        $this->assertSame(['controlNumber' => '1'], Stedi::createEligibilityCheck([]));
        $this->assertSame('Aetna', Stedi::searchPayers(['query' => 'aetna'])->items()[0]['payer']['displayName']);
        $this->assertSame('clm_01K6XFP3TZ8RA9X84963NMW40N', Stedi::getClaim('c1')['id'] ?? null, "Stedi's example claim.");

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/eligibility-check'));
    }

    public function test_unstubbed_requests_get_stedis_example_response(): void
    {
        Stedi::fake();

        $eligibility = Stedi::checkEligibility([]);
        $this->assertTrue($eligibility->isActive());
        $this->assertSame('8000', $eligibility->outOfPocket('30', 'IN_NETWORK'));

        $payers = Stedi::searchPayers(['query' => 'aetna']);
        $this->assertNotEmpty($payers->items());
        $this->assertSame($payers->items(), $payers->lazy()->all(), 'Examples are a single page.');

        $this->assertSame('', Stedi::getElectronicRemittanceAdvicePdf('t1'));
    }

    public function test_every_example_is_served_for_its_own_operation(): void
    {
        Stedi::fake();
        $examples = json_decode((string) file_get_contents(__DIR__.'/../resources/fakes.json'), true);

        foreach ($examples as $operation => $example) {
            [$method, $api, $path] = explode(' ', $operation, 3);
            $data = in_array($method, ['POST', 'PUT', 'PATCH'], true) ? [] : null;

            $this->assertSame($example, Stedi::request($api, $method, (string) preg_replace('/\{\w+\}/', 'id1', $path), [], $data), $operation);
        }

        $this->assertGreaterThan(70, count($examples));
    }

    public function test_examples_can_be_turned_off(): void
    {
        Stedi::fake(examples: false);

        $this->assertSame([], Stedi::createEligibilityCheck([]));
    }

    public function test_fake_assertions_match_api_relative_paths(): void
    {
        Stedi::fake();
        Stedi::assertNothingSent();

        Stedi::createEligibilityCheck(['payerId' => '60054']);
        Stedi::searchPayers(['query' => 'aetna']);

        Stedi::assertSent('healthcare/eligibility-check');
        Stedi::assertSent('healthcare/eligibility-check', fn (array $data) => $data['payerId'] === '60054');
        Stedi::assertSent('payers/payers/search', fn (array $data, Request $request) => str_contains($request->url(), 'query=aetna'));
        Stedi::assertNotSent('healthcare/eligibility-check', fn (array $data) => $data['payerId'] === '1');
        Stedi::assertNotSent('claims/*');
        Stedi::assertSentCount(2);
        Stedi::assertSentCount(1, 'payers/*');

        try {
            Stedi::assertSent('claims/claims');
            $this->fail('assertSent() should fail for a request that was not sent.');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('claims/claims', $e->getMessage());
        }
    }

    public function test_check_reports_the_key_mode(): void
    {
        Http::fake(['*' => Http::response(['items' => []])]);

        $this->artisan('stedi:check')->expectsOutputToContain('test key')->assertSuccessful();
    }

    public function test_check_fails_with_the_api_error(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->artisan('stedi:check')->expectsOutputToContain('Unauthorized')->assertFailed();
    }

    public function test_payers_lists_matching_payer_ids(): void
    {
        Http::fake(['*' => Http::response(['items' => [
            ['payer' => ['displayName' => 'Aetna', 'primaryPayerId' => '60054', 'stediId' => 'HPQRS']],
        ], 'nextPageToken' => null])]);

        $this->artisan('stedi:payers', ['query' => 'aetna'])
            ->expectsTable(['Payer', 'Payer ID', 'Stedi ID'], [['Aetna', '60054', 'HPQRS']])
            ->assertSuccessful();
    }
}
