<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Events\EligibilityBatchFinished;
use Cabuz\Stedi\Facades\Stedi;
use Cabuz\Stedi\Jobs\PollEligibilityBatch;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PollEligibilityBatchTest extends TestCase
{
    public function test_a_running_batch_is_checked_again_after_the_interval(): void
    {
        Stedi::fake(['manager/eligibility-manager/batch/b1' => ['id' => 'b1', 'status' => 'IN_PROGRESS']]);
        Bus::fake();
        Event::fake([EligibilityBatchFinished::class]);

        $this->app->call([new PollEligibilityBatch('b1', interval: 30), 'handle']);

        Bus::assertDispatched(PollEligibilityBatch::class, fn (PollEligibilityBatch $job) => $job->batchId === 'b1' && $job->delay === 30);
        Event::assertNotDispatched(EligibilityBatchFinished::class);
    }

    public function test_a_finished_batch_fires_the_event_with_the_batch_key(): void
    {
        Stedi::fake(['manager/eligibility-manager/batch/b1' => ['id' => 'b1', 'status' => 'COMPLETED_WITH_ERRORS']]);
        Bus::fake();
        Event::fake([EligibilityBatchFinished::class]);

        $this->app->call([new PollEligibilityBatch('b1', apiKey: 'tenant-key'), 'handle']);

        Event::assertDispatched(EligibilityBatchFinished::class, fn (EligibilityBatchFinished $event) => $event->batchId() === 'b1'
            && $event->status() === 'COMPLETED_WITH_ERRORS'
            && ! $event->successful());
        Bus::assertNotDispatched(PollEligibilityBatch::class);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'tenant-key'));
    }

    public function test_the_job_fails_after_the_timeout(): void
    {
        Stedi::fake(['manager/eligibility-manager/batch/b1' => ['id' => 'b1', 'status' => 'IN_PROGRESS']]);
        Bus::fake();

        $this->expectException(RuntimeException::class);

        $this->app->call([new PollEligibilityBatch('b1', interval: 60, timeout: 30), 'handle']);
    }
}
