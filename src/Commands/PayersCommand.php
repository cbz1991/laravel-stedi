<?php

namespace Cabuz\Stedi\Commands;

use Cabuz\Stedi\Exceptions\StediException;
use Cabuz\Stedi\Stedi;
use Illuminate\Console\Command;

class PayersCommand extends Command
{
    protected $signature = 'stedi:payers
        {query : Payer name or ID to search for}
        {--limit=10 : How many results to show}';

    protected $description = 'Search the Stedi payer network for payer IDs';

    public function handle(Stedi $stedi): int
    {
        try {
            $page = $stedi->searchPayers(['query' => (string) $this->argument('query'), 'pageSize' => 10]);
        } catch (StediException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = $page->lazy()
            ->take(max(1, (int) $this->option('limit')))
            ->map(fn (array $item) => [
                $item['payer']['displayName'] ?? '',
                $item['payer']['primaryPayerId'] ?? '',
                $item['payer']['stediId'] ?? '',
            ])
            ->all();

        if ($rows === []) {
            $this->components->warn('No payers found.');

            return self::SUCCESS;
        }

        $this->table(['Payer', 'Payer ID', 'Stedi ID'], $rows);

        return self::SUCCESS;
    }
}
