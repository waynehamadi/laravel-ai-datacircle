<?php

declare(strict_types=1);

namespace Datacircle\LaravelAi\Tools;

use Datacircle\LaravelAi\Client;
use Laravel\Ai\Contracts\Tool;

/**
 * A Laravel AI SDK tool on Datacircle's open API. Its result is a JSON object. An answer of 4xx is {"error": "..."}
 * with the API's own words, so that the model reads them and can call again; any other failure is thrown.
 */
abstract class DatacircleTool implements Tool
{
    /** The tool's name, as the model calls it. */
    public const NAME = '';

    protected Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client;
    }

    public function name(): string
    {
        return static::NAME;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected static function json(array $result): string
    {
        return json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected static function error(string $message): string
    {
        return self::json(['error' => $message]);
    }

    /**
     * A string argument as the model sent it: trimmed, '' when it sent none or something else.
     */
    protected static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
