<?php

namespace Cabuz\Stedi\Tests;

use Cabuz\Stedi\Facades\Stedi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PaginationTest extends TestCase
{
    public function test_lazy_follows_next_page_tokens_and_keeps_the_filters(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['items' => [['id' => 'c1'], ['id' => 'c2']], 'nextPageToken' => 'p2'])
            ->push(['items' => [['id' => 'c3']], 'nextPageToken' => null]),
        ]);

        $page = Stedi::listClaims(['status' => ['REJECTED'], 'pageSize' => 2]);

        $this->assertCount(2, $page);
        $this->assertTrue($page->hasMore());
        $this->assertSame(['c1', 'c2', 'c3'], $page->lazy()->pluck('id')->all());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://claims.us.stedi.com/2025-03-07/claims?pageToken=p2&status=REJECTED&pageSize=2');
    }

    public function test_extra_response_keys_stay_available(): void
    {
        Http::fake(['*' => Http::response(['items' => [], 'nextPageToken' => null, 'totalCount' => 7, 'stats' => ['total' => 7]])]);

        $page = Stedi::searchPayers(['query' => 'aetna']);

        $this->assertSame(7, $page->totalCount());
        $this->assertSame(['total' => 7], $page->raw()['stats']);
        $this->assertNull($page->nextPage());
    }
}
