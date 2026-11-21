<?php

declare(strict_types=1);

namespace Datacircle\LaravelAi\Tests;

use Datacircle\LaravelAi\Datacircle;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

use function Laravel\Ai\agent;

/**
 * An agent's own tool loop, on Ollama's API faked: the model asks for a tool, the agent runs it, the model answers.
 */
class AgentTest extends TestCase
{
    public function test_an_agent_runs_a_tool_the_model_asks_for(): void
    {
        $chat = fn (array $message, string $reason): array => [
            'model' => 'scripted', 'created_at' => '2026-11-21T00:00:00Z', 'message' => $message,
            'done' => true, 'done_reason' => $reason, 'prompt_eval_count' => 1, 'eval_count' => 1,
        ];
        Http::fake([
            '127.0.0.1:11434/api/chat' => Http::sequence()
                ->push($chat(['role' => 'assistant', 'content' => '', 'tool_calls' => [
                    ['function' => ['name' => 'datacircle_best_plans', 'arguments' => ['slug' => 'email-finder', 'limit' => 1]]],
                ]], 'stop'))
                ->push($chat(['role' => 'assistant', 'content' => 'A is best.'], 'stop')),
            'api.datacircle.dev/*' => Http::response([
                'api' => 'Employee email', 'slug' => 'email-finder', 'total' => 1,
                'plans' => [['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5]],
            ]),
        ]);

        $response = agent('Answer from the tools.', tools: Datacircle::tools())
            ->prompt('Who is best for email-finder?', provider: 'ollama', model: 'scripted');

        $this->assertSame('A is best.', $response->text);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.datacircle.dev/v1/apis/email-finder/best?limit=1');
        Http::assertSent(function (HttpRequest $request): bool {
            if (! str_ends_with($request->url(), '/api/chat')) {
                return false;
            }
            $names = array_column(array_column($request->data()['tools'] ?? [], 'function'), 'name');
            $last = end($request->data()['messages']);

            return $names === ['datacircle_list_apis', 'datacircle_best_plans', 'datacircle_search_plans', 'datacircle_provider']
                && $last['role'] === 'tool' && str_contains($last['content'], '"provider":"A"');
        });
    }
}
