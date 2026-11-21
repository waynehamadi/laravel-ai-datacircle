<?php

declare(strict_types=1);

namespace Datacircle\LaravelAi;

use Datacircle\LaravelAi\Tools\BestPlans;
use Datacircle\LaravelAi\Tools\DatacircleTool;
use Datacircle\LaravelAi\Tools\ListApis;
use Datacircle\LaravelAi\Tools\Provider;
use Datacircle\LaravelAi\Tools\SearchPlans;

/**
 * Datacircle's four Laravel AI SDK tools: find the best GTM APIs. https://datacircle.dev
 */
final class Datacircle
{
    /**
     * datacircle_list_apis, datacircle_best_plans, datacircle_search_plans and datacircle_provider, for an agent's tools().
     *
     * @return list<DatacircleTool>
     */
    public static function tools(?Client $client = null): array
    {
        $client ??= new Client;

        return [
            new ListApis($client),
            new BestPlans($client),
            new SearchPlans($client),
            new Provider($client),
        ];
    }
}
