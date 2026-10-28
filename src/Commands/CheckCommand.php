<?php

namespace Cabuz\Stedi\Commands;

use Cabuz\Stedi\Exceptions\StediException;
use Cabuz\Stedi\Stedi;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = 'stedi:check';

    protected $description = 'Check that the Stedi API key works';

    public function handle(Stedi $stedi): int
    {
        try {
            $stedi->searchPayers(['query' => 'aetna', 'pageSize' => 10]);
        } catch (StediException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $key = (string) $stedi->config('api_key');

        $this->components->info(sprintf(
            'Connected to Stedi with a %s key.',
            str_starts_with($key, 'test_') ? 'test' : 'production',
        ));

        return self::SUCCESS;
    }
}
