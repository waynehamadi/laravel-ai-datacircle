# Datacircle tools for the Laravel AI SDK

Find the best GTM APIs. We benchmark all the GTM APIs and we put the results on our website.

https://datacircle.dev

This package gives [Laravel AI SDK](https://github.com/laravel/ai) agents four tools that call Datacircle's public API, https://api.datacircle.dev/v1. You need no key and no account.

| Tool | What the tool gives | Call |
| --- | --- | --- |
| `datacircle_list_apis` | Every GTM API on Datacircle, with the API's slug, number of plans, number of providers and best plan | `GET /apis` |
| `datacircle_best_plans` | One API's plans, sorted by score out of 100, best first. Each plan comes with a rank and a price per 1,000 calls | `GET /apis/{slug}/best` |
| `datacircle_search_plans` | Plans that match a provider name or plan name. Search all APIs or one API. Sort the plans by score, provider or price | `GET /plans` |
| `datacircle_provider` | The APIs a provider sells, and the provider's rank on each API | `GET /providers/{slug}` |

## Install

```bash
composer require datacircle/laravel-ai-tools
```

The package needs PHP 8.3 or later, Laravel 12 or 13 and `laravel/ai` 1.0 or later.

## Give the tools to an agent

`Datacircle::tools()` returns all four Datacircle tools. Return them from your agent's `tools()` method:

```php
namespace App\Ai\Agents;

use Datacircle\LaravelAi\Datacircle;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class GtmAnalyst implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'You find the best GTM APIs. Answer from the Datacircle tools.';
    }

    public function tools(): iterable
    {
        return Datacircle::tools();
    }
}
```

The agent calls the Datacircle tools to answer a question:

```php
use App\Ai\Agents\GtmAnalyst;

$response = GtmAnalyst::make()->prompt('Who are the 3 best providers for the email-finder API?');

echo $response->text;
```

The tools work the same with `stream()` and `queue()`. The `agent()` helper takes the Datacircle tools too:

```php
use Datacircle\LaravelAi\Datacircle;

use function Laravel\Ai\agent;

$response = agent('Answer from the Datacircle tools.', tools: Datacircle::tools())
    ->prompt('On which APIs does Bright Data rank?');
```

To give the agent only some tools, list them yourself:

```php
use Datacircle\LaravelAi\Tools\BestPlans;
use Datacircle\LaravelAi\Tools\Provider;

public function tools(): iterable
{
    return [new BestPlans, new Provider];
}
```

## Run a tool yourself

```php
use Datacircle\LaravelAi\Tools\BestPlans;
use Laravel\Ai\Tools\Request;

echo (new BestPlans)->handle(new Request(['slug' => 'email-finder', 'limit' => 3]));
```

Each tool returns a JSON object. `datacircle_best_plans` and `datacircle_search_plans` return plans. Each plan has a rank, provider, plan name, score, price per 1,000 (`perThousand`, in dollars) and a page on datacircle.dev. The page on datacircle.dev lists every field. When the API gives no data, the tool returns `{"error": "..."}` with the API's own message. An unknown slug also returns `{"error": "..."}`. The model can then call again. On a network failure, the tool throws an exception. The SDK passes the exception to the model. The agent run goes on.

`new Client(baseUrl: ..., timeout: ...)` sets the API address and the timeout in seconds. The timeout is 30 seconds by default. Pass the client to `Datacircle::tools($client)` or to each tool's constructor. The tools call through Laravel's HTTP client. In your tests, `Http::fake()` fakes the tool calls.

## Network

Each tool call makes one HTTPS GET request to https://api.datacircle.dev/v1 and nothing else. The request holds only the tool's own arguments: no key and nothing from your chats or config. The API takes up to 60 calls a minute from one IP address. The tool waits once, for a minute at most, when the API asks the tool to wait. The package stores nothing.

## Links

- Datacircle: https://datacircle.dev
- The API: https://datacircle.dev/api
- The API's spec: https://api.datacircle.dev/v1/openapi.json
- What each key means: https://datacircle.dev/fields

MIT license.
