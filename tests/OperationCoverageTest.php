<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Stedi;
use Cabuz\Stedi\Tests\Concerns\WalksOperations;
use Illuminate\Support\Facades\Http;

/**
 * Calls every operation method and checks it against the operations in
 * Stedi's OpenAPI specs (tests/Fixtures/openapi, refreshed with
 * bin/update-specs and turned into methods by bin/generate).
 */
class OperationCoverageTest extends TestCase
{
    use WalksOperations;

    public function test_every_method_calls_a_documented_operation_and_every_operation_has_a_method(): void
    {
        Http::fake(['*' => Http::response(['items' => [], 'nextPageToken' => null])]);

        $stedi = $this->app->make(Stedi::class);
        $called = [];

        foreach ($this->operationMethods() as $methods) {
            foreach ($methods as $method) {
                $operations = $this->operationsCalled($stedi, $method);

                $this->assertCount(1, $operations, $method->getName().' should send exactly one request.');
                $this->assertStringStartsNotWith('unknown', $operations[0], $method->getName().' called an undocumented operation.');

                $called[] = $operations[0];
            }
        }

        $this->assertSame([], array_values(array_diff($this->documentedOperations(), $called)), 'Operations without a method.');
        $this->assertCount(count($called), array_unique($called), 'Two methods call the same operation.');
    }

    public function test_the_operation_list_matches_the_openapi_snapshots(): void
    {
        $hosts = [
            'healthcare.json' => 'healthcare', 'claims.json' => 'claims', 'manager.json' => 'manager',
            'payers.json' => 'payers', 'enrollment.json' => 'enrollment', 'event-destinations.json' => 'events',
            'core.json' => 'core',
        ];
        $operations = [];

        foreach ($hosts as $file => $api) {
            $spec = json_decode((string) file_get_contents(__DIR__.'/Fixtures/openapi/'.$file), true);

            foreach ($spec['paths'] as $path => $item) {
                // Payer lookups live on both hosts; the payers host is the canonical one.
                if ($api === 'healthcare' && str_starts_with($path, '/payer')) {
                    continue;
                }

                foreach (array_intersect(array_keys($item), ['get', 'post', 'put', 'patch', 'delete']) as $verb) {
                    $operations[] = strtoupper($verb).' '.$api.' '.$path;
                }
            }
        }

        sort($operations);
        $documented = $this->documentedOperations();
        sort($documented);

        $this->assertSame($operations, $documented, 'Run bin/generate: the specs changed.');
    }
}
