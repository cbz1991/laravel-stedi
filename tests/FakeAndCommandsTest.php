<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Facades\Stedi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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
        $this->assertSame([], Stedi::getClaim('c1'));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/eligibility-check'));
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
