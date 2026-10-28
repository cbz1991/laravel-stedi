<?php

namespace Cabuz\Stedi\Tests\Concerns;

use Cabuz\Stedi\Stedi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Calls the generated operation methods with placeholder arguments and maps
 * each request back to a documented operation.
 */
trait WalksOperations
{
    /**
     * The operation methods, grouped by the trait (API) that defines them.
     *
     * @return array<string, list<ReflectionMethod>>
     */
    protected function operationMethods(): array
    {
        $groups = [];

        foreach ((new ReflectionClass(Stedi::class))->getTraits() as $trait) {
            $groups[$trait->getShortName()] = array_values(array_map(
                fn (ReflectionMethod $method) => new ReflectionMethod(Stedi::class, $method->getName()),
                array_filter($trait->getMethods(ReflectionMethod::IS_PUBLIC), fn (ReflectionMethod $method) => ! $method->isStatic()),
            ));
        }

        return $groups;
    }

    /**
     * @return list<mixed>
     */
    protected function placeholderArguments(ReflectionMethod $method): array
    {
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                break;
            }

            $type = $parameter->getType();

            $arguments[] = $type instanceof ReflectionNamedType && $type->getName() === 'array' ? ['x' => 1] : 'id1';
        }

        return $arguments;
    }

    /**
     * "VERB api /path/{param}" of every request the method sends.
     *
     * @return list<string>
     */
    protected function operationsCalled(Stedi $stedi, ReflectionMethod $method): array
    {
        $before = count(Http::recorded());
        $method->invokeArgs($stedi, $this->placeholderArguments($method));

        return array_map(
            fn (array $pair) => $this->operationOf($stedi, $pair[0]) ?? 'unknown '.$pair[0]->url(),
            array_slice(Http::recorded()->all(), $before),
        );
    }

    protected function operationOf(Stedi $stedi, Request $request): ?string
    {
        $url = strtok($request->url(), '?');

        foreach (array_keys(Stedi::ENDPOINTS) as $api) {
            $base = $stedi->endpoint($api);

            if (! str_starts_with((string) $url, $base.'/')) {
                continue;
            }

            $path = substr((string) $url, strlen($base));

            foreach ($this->documentedOperations() as $operation) {
                [$verb, $opApi, $opPath] = explode(' ', $operation, 3);
                $pattern = '#^'.preg_replace('#\\\\\{[^/]+\\\\\}#', '[^/]+', preg_quote($opPath, '#')).'$#';

                if ($verb === $request->method() && $opApi === $api && preg_match($pattern, $path)) {
                    return $operation;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function documentedOperations(): array
    {
        /** @var list<string> */
        return json_decode((string) file_get_contents(__DIR__.'/../Fixtures/operations.json'), true);
    }
}
