<?php

declare(strict_types=1);

namespace Datacircle\LaravelAi\Tests;

use Datacircle\LaravelAi\Client;
use Datacircle\LaravelAi\Datacircle;
use Datacircle\LaravelAi\Tools\BestPlans;
use Datacircle\LaravelAi\Tools\ListApis;
use Datacircle\LaravelAi\Tools\Provider;
use Datacircle\LaravelAi\Tools\SearchPlans;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;

class ToolsTest extends TestCase
{
    public function test_four_tools_with_their_names_and_schemas(): void
    {
        $tools = Datacircle::tools();

        $this->assertContainsOnlyInstancesOf(Tool::class, $tools);
        $this->assertSame(
            ['datacircle_list_apis', 'datacircle_best_plans', 'datacircle_search_plans', 'datacircle_provider'],
            array_map(ToolNameResolver::resolve(...), $tools),
        );
        $schema = (new ObjectSchema((new BestPlans)->schema(new JsonSchemaTypeFactory)))->toSchema();
        $this->assertSame(['slug'], $schema['required']);
        $this->assertEquals(['type' => 'integer', 'description' => 'How many plans to give, 1 to 50. 10 when left out', 'minimum' => 1, 'maximum' => 50], $schema['properties']['limit']);
        $search = (new ObjectSchema((new SearchPlans)->schema(new JsonSchemaTypeFactory)))->toSchema();
        $this->assertSame(['score', 'provider', 'price'], $search['properties']['sort']['enum']);
        $this->assertArrayNotHasKey('required', $search);
    }

    public function test_list_apis_puts_the_most_words_matched_first(): void
    {
        Http::fake(['api.datacircle.dev/v1/apis' => Http::response([
            ['api' => 'Employee phone', 'slug' => 'phone-finder', 'plans' => 3, 'providers' => 2, 'terms' => 'dropped'],
            ['api' => 'Employee email', 'slug' => 'email-finder', 'plans' => 5, 'providers' => 4],
            ['api' => 'Email validation', 'slug' => 'email-verification', 'plans' => 2, 'providers' => 2],
        ])]);

        $result = json_decode((string) (new ListApis)->handle(new Request(['query' => 'email finder'])), true);

        $this->assertSame(3, $result['total']);
        $this->assertSame(['email-finder', 'phone-finder', 'email-verification'], array_column($result['apis'], 'slug'));
        $this->assertArrayNotHasKey('terms', $result['apis'][1]);
    }

    public function test_best_plans_sends_the_slug_and_limit_and_briefs_each_plan(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response([
            'api' => 'Employee email', 'slug' => 'email-finder', 'total' => 1,
            'plans' => [['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5, 'perThousand' => 2.5, 'aggregators' => ['x']]],
        ])]);

        $result = json_decode((string) (new BestPlans)->handle(new Request(['slug' => 'Email Finder', 'limit' => '70'])), true);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.datacircle.dev/v1/apis/email-finder/best?limit=50'
            && str_starts_with($request->header('User-Agent')[0], 'laravel-ai-datacircle/'));
        $this->assertSame(['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5, 'perThousand' => 2.5], $result['plans'][0]);
    }

    public function test_a_missing_slug_asks_for_one_without_a_call(): void
    {
        Http::fake();

        $this->assertStringContainsString('Give an API\'s slug', (string) (new BestPlans)->handle(new Request([])));
        $this->assertStringContainsString('Give a provider\'s slug', (string) (new Provider)->handle(new Request(['slug' => ['x']])));
        Http::assertNothingSent();
    }

    public function test_an_answer_of_4xx_is_the_apis_words_for_the_model(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response(['error' => 'no API has the slug nope'], 404)]);

        $this->assertStringStartsWith('{"error":"no API has the slug nope. datacircle_list_apis gives', (string) (new BestPlans)->handle(new Request(['slug' => 'nope'])));
    }

    public function test_a_failure_of_the_server_is_thrown(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response('', 503)]);

        $this->expectExceptionMessage('datacircle: 503');
        (new Provider)->handle(new Request(['slug' => 'bright-data']));
    }

    public function test_search_plans_takes_a_provider_sent_as_the_api_as_its_name(): void
    {
        Http::fake(function (HttpRequest $request) {
            return isset($request->data()['api'])
                ? Http::response(['error' => 'api must be one of: Employee email'], 400)
                : Http::response(['total' => 1, 'offset' => 0, 'plans' => [['provider' => 'Bright Data', 'plan' => 'Free']]]);
        });

        $result = json_decode((string) (new SearchPlans)->handle(new Request(['api' => 'Bright Data', 'sort' => 'cheap'])), true);

        Http::assertSent(fn (HttpRequest $request): bool => $request->data() === ['query' => 'Bright Data']);
        $this->assertSame(1, $result['total']);
        $this->assertStringContainsString('No API is named "Bright Data"', $result['note']);
    }

    public function test_search_plans_retries_a_slug_with_its_words(): void
    {
        Http::fake(fn (HttpRequest $request) => Http::response(['total' => $request->data()['query'] === 'bright data' ? 2 : 0, 'offset' => 0, 'plans' => []]));

        $result = json_decode((string) (new SearchPlans)->handle(new Request(['query' => 'bright-data'])), true);

        $this->assertSame(2, $result['total']);
        $this->assertStringContainsString('"bright data"', $result['note']);
    }

    public function test_provider_gives_its_best_rank_first(): void
    {
        Http::fake(['api.datacircle.dev/v1/providers/bright-data' => Http::response([
            'provider' => 'Bright Data', 'slug' => 'bright-data',
            'apis' => [
                ['api' => 'A', 'slug' => 'a', 'rank' => null, 'plans' => [['plan' => 'x', 'rank' => null]]],
                ['api' => 'B', 'slug' => 'b', 'rank' => 7, 'plans' => [['plan' => 'y', 'rank' => 9], ['plan' => 'z', 'rank' => 7, 'score' => 50]]],
                ['api' => 'C', 'slug' => 'c', 'rank' => 2, 'plans' => [['plan' => 'w', 'rank' => 2]]],
            ],
        ])]);

        $result = json_decode((string) (new Provider)->handle(new Request(['slug' => 'Bright Data'])), true);

        $this->assertSame(['c', 'b', 'a'], array_column($result['apis'], 'slug'));
        $this->assertSame(['plan' => 'z', 'score' => 50], $result['apis'][1]['best']);
        $this->assertSame([3, 2], [$result['total'], $result['ranked']]);
    }

    public function test_a_429_is_waited_for_once(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::sequence()
            ->push(['error' => 'slow down'], 429, ['Retry-After' => '1'])
            ->push(['provider' => 'X', 'slug' => 'x', 'apis' => []])]);

        $result = json_decode((string) (new Provider(new Client))->handle(new Request(['slug' => 'x'])), true);

        $this->assertSame(0, $result['total']);
        Http::assertSentCount(2);
    }

    public function test_another_base_url(): void
    {
        Http::fake(['127.0.0.1:9/v1/apis' => Http::response([])]);

        Datacircle::tools(new Client('http://127.0.0.1:9/v1/'))[0]->handle(new Request([]));

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://127.0.0.1:9/v1/apis');
    }

    public function test_slugify(): void
    {
        $this->assertSame('bright-data', Client::slugify(' Bright Data '));
        $this->assertSame('societe-generale', Client::slugify('Société Générale'));
        $this->assertSame('', Client::slugify(null));
    }
}
