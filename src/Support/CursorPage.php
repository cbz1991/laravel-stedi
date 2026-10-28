<?php

namespace Cabuz\Stedi\Support;

use ArrayIterator;
use Cabuz\Stedi\Stedi;
use Countable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use IteratorAggregate;
use Traversable;

/**
 * One page of a paginated Stedi list (pageToken / nextPageToken).
 *
 * Iterate it for the items on this page, call nextPage() to move on, or use
 * lazy() to stream every item across all pages.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
class CursorPage implements Countable, IteratorAggregate
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        protected Stedi $stedi,
        protected string $api,
        protected string $path,
        protected array $query,
        protected array $response,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        $items = $this->response['items'] ?? [];

        return is_array($items) ? array_values($items) : [];
    }

    public function nextPageToken(): ?string
    {
        $token = $this->response['nextPageToken'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function hasMore(): bool
    {
        return $this->nextPageToken() !== null;
    }

    /**
     * The total number of matches, for the lists that return it.
     */
    public function totalCount(): ?int
    {
        $total = $this->response['totalCount'] ?? null;

        return is_int($total) ? $total : null;
    }

    /**
     * The full decoded response, including extra keys such as stats.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->response;
    }

    public function nextPage(): ?self
    {
        $token = $this->nextPageToken();

        if ($token === null) {
            return null;
        }

        return $this->stedi->paginate($this->api, 'GET', $this->path, ['pageToken' => $token] + $this->query);
    }

    /**
     * The items on this page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(): Collection
    {
        return new Collection($this->items());
    }

    /**
     * Every item across this page and all following pages, fetched on demand.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function lazy(): LazyCollection
    {
        return new LazyCollection(function () {
            $page = $this;

            while ($page !== null) {
                foreach ($page->items() as $item) {
                    yield $item;
                }

                $page = $page->nextPage();
            }
        });
    }

    public function count(): int
    {
        return count($this->items());
    }

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items());
    }
}
