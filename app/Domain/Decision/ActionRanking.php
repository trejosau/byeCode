<?php

namespace App\Domain\Decision;

use InvalidArgumentException;

final class ActionRanking
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $decision
     * @param  list<array<string, mixed>>  $signals
     * @param  array<string, mixed>|null  $candidate
     * @return list<array<string, mixed>>
     */
    public function build(array $snapshot, array $decision, array $signals, ?array $candidate): array
    {
        $rules = app(RuleEngine::class)->assess($snapshot, $signals);
        $order = $candidate['action_order'] ?? array_values(array_unique([$decision['action'], 'wait', 'no_action', ...RuleEngine::ACTIONS]));
        $rows = [];
        $plans = [];
        foreach ($candidate['plans'] ?? [] as $plan) {
            $plans[$plan['action']] = $plan;
        }
        $rank = 0;
        $evidence = app(BehaviorEvidence::class)->catalog($snapshot);
        foreach ($order as $action) {
            $result = null;
            $excluded = $rules['blocked'][$action] ?? [];
            $reason = $excluded ? 'Bloqueada por las restricciones vigentes: '.implode(', ', $excluded).'.' : '';
            if (! $excluded && $action === 'no_action' && ! in_array($decision['action'], ['wait', 'no_action'], true)) {
                $excluded = ['supported_intervention'];
                $reason = 'Existe una intervención sustentada; omitirla no es una alternativa equivalente.';
            }
            if (! $excluded) {
                if ($action === $decision['action']) {
                    $result = $decision;
                } elseif ($candidate) {
                    $timing = match ($action) {
                        'no_action' => 'not_applicable', 'wait' => $rules['reevaluate_at'] ? 'at_date' : 'on_new_information', default => 'within_24h',
                    };
                    try {
                        $result = app(DecisionValidator::class)->compile($snapshot, [...$candidate, 'action' => $action,
                            'action_order' => array_values(array_unique([$action, ...$order])), 'timing' => $timing,
                            'scheduled_for' => $timing === 'at_date' ? $rules['reevaluate_at'] : null])['result'];
                    } catch (InvalidArgumentException $e) {
                        $excluded = ['insufficient_evidence'];
                        $reason = $e->getMessage();
                    }
                } else {
                    // Deterministic outcomes do not manufacture competing AI interpretations.
                    $excluded = ['not_evaluated'];
                    $reason = 'Sin interpretación adicional que sustente esta alternativa.';
                }
            }
            $evidenceIds = $result ? array_values(array_unique([...($plans[$action]['evidence_event_ids'] ?? []), ...($result['evidence_event_ids'] ?? [])])) : [];
            $rows[] = ['action' => $action, 'allowed' => $result !== null, 'selected' => $action === $decision['action'],
                'evidence' => array_values(array_intersect_key($evidence, array_flip($evidenceIds))),
                'behavior_summary' => $result && $action === $decision['action'] && isset($snapshot['behavior_context'])
                    ? array_intersect_key($snapshot['behavior_context'], array_flip(['sections', 'scroll', 'measurement_note'])) : null,
                'plan' => $result ? ($plans[$action] ?? null) : null,
                'rank' => $result ? ++$rank : null, 'excluded_by' => $excluded, 'reason' => $result['reason'] ?? $reason,
                'evidence_event_ids' => $result['evidence_event_ids'] ?? [], 'timing' => $result['timing'] ?? null,
                'scheduled_for' => $result['scheduled_for'] ?? null, 'confidence' => $result['confidence'] ?? null,
                'requires_human_review' => $result['requires_human_review'] ?? false,
                'ranking_source' => $candidate ? 'model_validated' : 'rules',
                'probability' => null, 'probability_status' => 'not_estimated_per_action'];
        }
        usort($rows, fn ($a, $b) => ($a['rank'] ?? 99) <=> ($b['rank'] ?? 99));

        return $rows;
    }
}
