<?php

namespace App\Domain\Evaluation;

use App\Domain\Decision\RuleEngine;
use App\Domain\Decision\SemanticContract;
use App\Models\CommercialCase;
use App\Models\Evaluation;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenAiAdapter
{
    public function __construct(private BudgetLedger $budget, private SemanticContract $contract) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(Evaluation $evaluation): array
    {
        if (CommercialCase::findOrFail($evaluation->commercial_case_id)->demo_run_id) {
            throw new RuntimeException('demo_ai_disabled');
        }
        $key = config('integrations.openai.api_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('missing_api_key');
        }
        $snapshot = $evaluation->snapshot;
        unset($snapshot['case_pk'],$snapshot['data_set_id']);
        $snapshot['preliminary_rules'] = app(RuleEngine::class)->assess($snapshot);
        $input = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($input) > config('byecode.max_context_bytes')) {
            throw new RuntimeException('context_too_large');
        }
        $body = [
            'model' => config('byecode.model'), 'store' => false,
            'instructions' => $this->contract->instructions(),
            'input' => [['role' => 'user', 'content' => $input]],
            'max_output_tokens' => (int) config('byecode.max_output_tokens'),
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'byecode_semantic_v2', 'strict' => true, 'schema' => $this->contract->schema()]],
        ];
        if (str_starts_with((string) config('byecode.model'), 'gpt-5')) {
            $body['reasoning'] = ['effort' => 'none'];
        }
        // One UTF-8 byte per token plus protocol allowance is a conservative upper bound for this text-only request.
        $upperBound = strlen(json_encode($body, JSON_THROW_ON_ERROR)) + 2048;
        $usage = $this->budget->reserve($evaluation, $upperBound);
        try {
            $response = Http::withToken($key)->acceptJson()->connectTimeout(8)->timeout(35)
                ->post('https://api.openai.com/v1/responses', $body);
            $json = $response->json();
            $inputTokens = is_array($json) ? ($json['usage']['input_tokens'] ?? null) : null;
            $outputTokens = is_array($json) ? ($json['usage']['output_tokens'] ?? null) : null;
            if (is_int($inputTokens) && $inputTokens >= 0 && is_int($outputTokens) && $outputTokens >= 0) {
                $this->budget->settle($usage, $inputTokens, $outputTokens, $response->header('x-request-id') ?: null);
            } else {
                $usage->update(['status' => 'uncertain']);
            }
            if (! $response->successful()) {
                throw new RuntimeException('provider_http_'.$response->status());
            }
            if (! is_array($json) || ($json['status'] ?? null) !== 'completed') {
                throw new RuntimeException('provider_incomplete');
            }
            $evaluation->update(['model' => is_string($json['model'] ?? null) ? $json['model'] : config('byecode.model')]);
            $text = '';
            foreach ($json['output'] ?? [] as $output) {
                foreach ($output['content'] ?? [] as $part) {
                    if (($part['type'] ?? '') === 'refusal') {
                        throw new RuntimeException('provider_refusal');
                    }
                    if (($part['type'] ?? '') === 'output_text') {
                        $text .= $part['text'];
                    }
                }
            }
            $candidate = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($candidate) || array_is_list($candidate)) {
                throw new RuntimeException('provider_invalid_json');
            }

            return $candidate;
        } catch (Throwable $e) {
            if ($usage->fresh()?->status === 'reserved') {
                $usage->update(['status' => 'uncertain']);
            }
            if ($e instanceof RuntimeException && preg_match('/^[a-z_0-9]+$/D', $e->getMessage())) {
                throw $e;
            }
            // Never expose HTTP bodies, authorization headers, or customer text to logs/UI.
            throw new RuntimeException('provider_transport_or_format');
        }
    }
}
