<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Facades\Stedi;
use Illuminate\Support\Facades\Http;

/**
 * Runs against Stedi's real API with a test key, which only answers Stedi's
 * mock requests. Skipped unless STEDI_LIVE_KEY is set:
 *
 *     STEDI_LIVE_KEY=test_... vendor/bin/phpunit --filter LiveTest
 */
class LiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $key = getenv('STEDI_LIVE_KEY');

        if (! is_string($key) || $key === '') {
            $this->markTestSkipped('Set STEDI_LIVE_KEY to a Stedi test key to run the live tests.');
        }

        $this->app['config']->set('stedi.api_key', $key);
        $this->app->forgetInstance(\Cabuz\Stedi\Stedi::class);
        Stedi::clearResolvedInstances();
        Http::preventStrayRequests(false);
    }

    public function test_a_mock_eligibility_check_reads_through_the_helpers(): void
    {
        // Stedi's mock request for Aetna (test keys accept only mock requests).
        $eligibility = Stedi::checkEligibility([
            'payerId' => '60054',
            'provider' => ['name' => ['organization' => 'Acme Clinic'], 'npi' => '1999999984'],
            'subscriber' => [
                'name' => ['person' => ['firstName' => 'Jane', 'lastName' => 'Doe']],
                'memberId' => 'AETNA12345',
                'dateOfBirth' => '2004-04-04',
            ],
            'encounter' => ['services' => [['system' => 'STC', 'value' => '30']]],
        ]);

        $this->assertNotNull($eligibility->id());
        $this->assertFalse($eligibility->hasErrors(), (string) json_encode($eligibility->errors()));
        $this->assertNotEmpty($eligibility->plans());
        $this->assertTrue($eligibility->isActive());
        $this->assertTrue($eligibility->isActive('30'));

        // The mock's values can change; check that each helper finds one.
        $this->assertNotNull($eligibility->copay('98', 'IN_NETWORK', 'INDIVIDUAL'));
        $this->assertNotNull($eligibility->coinsurance('98', 'OUT_OF_NETWORK'));
        $this->assertNotNull($eligibility->deductible('30', 'IN_NETWORK', 'INDIVIDUAL'));
        $this->assertNotNull($eligibility->deductibleRemaining('30', 'IN_NETWORK', 'INDIVIDUAL'));
        $this->assertNotNull($eligibility->outOfPocket('30', 'OUT_OF_NETWORK'));
        $this->assertNotNull($eligibility->outOfPocketRemaining('30', 'OUT_OF_NETWORK'));
    }

    public function test_payer_search(): void
    {
        $payers = Stedi::searchPayers(['query' => 'aetna', 'pageSize' => 10]);

        $this->assertNotEmpty($payers->items());
    }
}
