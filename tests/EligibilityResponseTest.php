<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Facades\Stedi;
use Cabuz\Stedi\Support\EligibilityResponse;
use Illuminate\Support\Facades\Http;

class EligibilityResponseTest extends TestCase
{
    public function test_check_eligibility_wraps_stedis_example_response(): void
    {
        Http::fake(['*' => Http::response($this->specExample())]);

        $eligibility = Stedi::checkEligibility(['payerId' => '61101']);

        $this->assertInstanceOf(EligibilityResponse::class, $eligibility);
        $this->assertSame('ec_01a06d4a-a0fa-77d2-8b49-ca9a74c9888a', $eligibility->id());
        $this->assertSame('61101', $eligibility['payerId']);
        $this->assertFalse($eligibility->hasErrors());
        $this->assertSame(['Humana Gold Plan'], array_column($eligibility->plans(), 'name'));

        $this->assertTrue($eligibility->isActive());
        $this->assertTrue($eligibility->isActive('30'));
        $this->assertFalse($eligibility->isActive('98'));

        $this->assertSame('2000', $eligibility->copay('49'));
        $this->assertSame('100', $eligibility->copay('86', 'IN_NETWORK'));
        $this->assertNull($eligibility->copay('86', 'OUT_OF_NETWORK'));
        $this->assertSame('0', $eligibility->coinsurance('49'));
        $this->assertSame('8000', $eligibility->outOfPocket('30', 'IN_NETWORK', 'INDIVIDUAL'));
        $this->assertNull($eligibility->outOfPocket('30', level: 'FAMILY'));
    }

    public function test_totals_and_remaining_amounts_are_told_apart(): void
    {
        $eligibility = EligibilityResponse::from(['plans' => [['name' => 'PPO', 'benefits' => [
            'statuses' => [['status' => 'INACTIVE', 'service' => ['system' => 'STC', 'value' => '30']]],
            'deductible' => [
                ['amount' => '450', 'timePeriod' => 'REMAINING', 'network' => ['indicator' => 'IN_NETWORK']],
                ['amount' => '1050', 'timePeriod' => 'YEAR_TO_DATE', 'network' => ['indicator' => 'IN_NETWORK']],
                ['amount' => '1500', 'timePeriod' => 'CALENDAR_YEAR', 'network' => ['indicator' => 'IN_NETWORK']],
                ['amount' => '3000', 'timePeriod' => 'CALENDAR_YEAR', 'network' => ['indicator' => 'OUT_OF_NETWORK']],
                ['amount' => '3000', 'timePeriod' => 'CALENDAR_YEAR', 'coverageLevel' => 'FAMILY'],
            ],
            'outOfPocket' => [
                ['amount' => '6000', 'timePeriod' => 'CALENDAR_YEAR'],
                ['amount' => '5200', 'timePeriod' => 'REMAINING'],
            ],
        ]]], 'errors' => [['code' => '75', 'description' => 'Subscriber/Insured Not Found']]]);

        $this->assertFalse($eligibility->isActive());
        $this->assertSame('75', $eligibility->errors()[0]['code']);
        $this->assertSame('1500', $eligibility->deductible(network: 'IN_NETWORK'));
        $this->assertSame('3000', $eligibility->deductible(network: 'OUT_OF_NETWORK'));
        $this->assertSame('3000', $eligibility->deductible(level: 'FAMILY'));
        $this->assertSame('450', $eligibility->deductibleRemaining(network: 'IN_NETWORK'));
        $this->assertSame('6000', $eligibility->outOfPocket());
        $this->assertSame('5200', $eligibility->outOfPocketRemaining());
        $this->assertCount(1, $eligibility->benefits('deductible', period: 'YEAR_TO_DATE'));
        $this->assertSame([], EligibilityResponse::from([])->plans());
    }

    /**
     * @return array<string, mixed>
     */
    private function specExample(): array
    {
        $spec = json_decode((string) file_get_contents(__DIR__.'/Fixtures/openapi/healthcare.json'), true);

        foreach ($spec['paths'] as $path => $operations) {
            if (str_ends_with($path, '/eligibility-check')) {
                return $operations['post']['responses']['200']['content']['application/json']['examples']['CreateEligibilityCheck_example1']['value'];
            }
        }

        $this->fail('The spec has no createEligibilityCheck example.');
    }
}
