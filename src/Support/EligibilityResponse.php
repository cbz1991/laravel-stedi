<?php

namespace Cabuz\Stedi\Support;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use LogicException;

/**
 * A real-time eligibility check response (createEligibilityCheck), with
 * helpers for the questions every practice asks: is the coverage active, and
 * what does the patient owe (copay, coinsurance, deductible, out-of-pocket).
 *
 * The benefit helpers take the same optional filters:
 *  - service: a Service Type Code, e.g. "30" (plan coverage) or "98" (office visit)
 *  - network: "IN_NETWORK" or "OUT_OF_NETWORK" (entries for both networks match either)
 *  - level:   "INDIVIDUAL", "FAMILY", ... (entries without one are individual,
 *             and "INDIVIDUAL" also matches the payer's "EMPLOYEE_ONLY")
 *
 * When several entries match (payers often send one per place of service or
 * with different messages), the amount helpers return the first; use
 * benefits() to see them all.
 *
 * The array access reads the raw response, so $response['plans'] still works.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class EligibilityResponse implements Arrayable, ArrayAccess, JsonSerializable
{
    /**
     * Time periods that describe what is left or already used, not the plan's total.
     */
    protected const PROGRESS_PERIODS = ['REMAINING', 'LIFETIME_REMAINING', 'YEAR_TO_DATE', 'EXCEEDED', 'NOT_EXCEEDED'];

    /**
     * Coverage levels that mean the same for a filter: payers send cost
     * sharing for the subscriber alone as either INDIVIDUAL or EMPLOYEE_ONLY.
     */
    protected const LEVELS = ['INDIVIDUAL' => ['INDIVIDUAL', 'EMPLOYEE_ONLY']];

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(public readonly array $response) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function from(array $response): self
    {
        return new self($response);
    }

    /**
     * Stedi's ID for the check ("ec_..."), for getEligibilityCheckPdf().
     */
    public function id(): ?string
    {
        $id = $this->response['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * The payer's AAA rejections (code, description, followupAction, ...),
     * e.g. code "75" when the subscriber is not found.
     *
     * @return list<array<string, mixed>>
     */
    public function errors(): array
    {
        return $this->listOf($this->response['errors'] ?? []);
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    /**
     * The patient's plans, each with its name and benefits by category.
     *
     * @return list<array<string, mixed>>
     */
    public function plans(): array
    {
        return $this->listOf($this->response['plans'] ?? []);
    }

    /**
     * Whether the payer reports active coverage, for one service type or any.
     */
    public function isActive(?string $service = null): bool
    {
        return $this->benefits('statuses', $service)
            ->contains(fn (array $entry) => str_starts_with((string) ($entry['status'] ?? ''), 'ACTIVE'));
    }

    /**
     * The entries of one benefit category ("coPayment", "deductible",
     * "statuses", "limitations", ...) across every plan, filtered.
     *
     * @param  string|list<string>|null  $period  Time period(s) to keep, e.g. "CALENDAR_YEAR" or "REMAINING".
     * @return Collection<int, array<string, mixed>>
     */
    public function benefits(string $category, ?string $service = null, ?string $network = null, ?string $level = null, string|array|null $period = null): Collection
    {
        $entries = [];

        foreach ($this->plans() as $plan) {
            foreach ($this->listOf($plan['benefits'][$category] ?? []) as $entry) {
                if ($this->matches($entry, $service, $network, $level, $period === null ? null : (array) $period)) {
                    $entries[] = $entry;
                }
            }
        }

        return new Collection($entries);
    }

    /**
     * The copay amount, e.g. "25".
     */
    public function copay(?string $service = null, ?string $network = null, ?string $level = null): ?string
    {
        return $this->value('coPayment', 'amount', $service, $network, $level);
    }

    /**
     * The patient's coinsurance share as a decimal, e.g. "0.2" for 20%.
     */
    public function coinsurance(?string $service = null, ?string $network = null, ?string $level = null): ?string
    {
        return $this->value('coInsurance', 'percent', $service, $network, $level);
    }

    /**
     * The plan's deductible for the period, e.g. "1500".
     */
    public function deductible(?string $service = null, ?string $network = null, ?string $level = null): ?string
    {
        return $this->value('deductible', 'amount', $service, $network, $level, total: true);
    }

    /**
     * What is left of the deductible.
     */
    public function deductibleRemaining(?string $service = null, ?string $network = null, ?string $level = null): ?string
    {
        return $this->value('deductible', 'amount', $service, $network, $level, ['REMAINING']);
    }

    /**
     * The out-of-pocket maximum for the period.
     */
    public function outOfPocket(?string $service = null, ?string $network = null, ?string $level = null): ?string
    {
        return $this->value('outOfPocket', 'amount', $service, $network, $level, total: true);
    }

    /**
     * What is left before the out-of-pocket maximum is reached.
     */
    public function outOfPocketRemaining(?string $service = null, ?string $network = null, ?string $level = null): ?string
    {
        return $this->value('outOfPocket', 'amount', $service, $network, $level, ['REMAINING']);
    }

    /**
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->response;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->response;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->response;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->response[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->response[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Eligibility responses are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Eligibility responses are read-only.');
    }

    /**
     * The first matching entry's field. Totals skip the entries that report
     * progress (remaining, year to date, ...).
     *
     * @param  list<string>|null  $periods
     */
    protected function value(string $category, string $field, ?string $service, ?string $network, ?string $level, ?array $periods = null, bool $total = false): ?string
    {
        $entry = $this->benefits($category, $service, $network, $level, $periods)
            ->first(fn (array $entry) => isset($entry[$field])
                && (! $total || ! in_array($entry['timePeriod'] ?? null, static::PROGRESS_PERIODS, true)));

        return $entry === null ? null : (string) $entry[$field];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>|null  $periods
     */
    protected function matches(array $entry, ?string $service, ?string $network, ?string $level, ?array $periods): bool
    {
        if ($service !== null && (string) ($entry['service']['value'] ?? '') !== $service) {
            return false;
        }

        $indicator = $entry['network']['indicator'] ?? null;

        if ($network !== null && $indicator !== null && $indicator !== 'IN_AND_OUT_OF_NETWORK' && $indicator !== strtoupper($network)) {
            return false;
        }

        if ($level !== null && ! in_array($entry['coverageLevel'] ?? 'INDIVIDUAL', static::LEVELS[strtoupper($level)] ?? [strtoupper($level)], true)) {
            return false;
        }

        return $periods === null || in_array($entry['timePeriod'] ?? null, $periods, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listOf(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
