<?php

namespace App\Domain\Decision;

use InvalidArgumentException;

final class CommercialPlans
{
    /** @param array<string, mixed> $snapshot
     * @param  array<string, mixed>  $candidate
     */
    public function validate(array $snapshot, array $candidate): void
    {
        $plans = $candidate['plans'];
        if (! is_array($plans) || ! array_is_list($plans) || count($plans) < 1 || count($plans) > 6) {
            throw new InvalidArgumentException('Planes comerciales incompletos.');
        }
        $fields = ['action', 'title', 'reason', 'steps', 'message', 'proposed_total_cents', 'additional_delivery_days', 'checks', 'evidence_event_ids'];
        $actions = [];
        $ids = array_keys(app(BehaviorEvidence::class)->catalog($snapshot));
        $rules = app(RuleEngine::class)->assess($snapshot, $candidate['signals']);
        foreach ($plans as $plan) {
            if (! is_array($plan) || count($plan) !== count($fields) || array_diff($fields, array_keys($plan))
                || ! is_string($plan['action']) || ! in_array($plan['action'], RuleEngine::ACTIONS, true)
                || in_array($plan['action'], $actions, true)) {
                throw new InvalidArgumentException('Plan comercial inválido o duplicado.');
            }
            $actions[] = $plan['action'];
            foreach (['title' => 180, 'reason' => 1200] as $field => $limit) {
                $this->text($plan[$field], $limit);
            }
            foreach (['steps', 'checks', 'evidence_event_ids'] as $field) {
                if (! is_array($plan[$field]) || ! array_is_list($plan[$field]) || count($plan[$field]) > 10) {
                    throw new InvalidArgumentException('Lista de plan inválida.');
                }
                foreach ($plan[$field] as $text) {
                    $this->text($text, 600);
                }
            }
            if (! $plan['steps'] || ! $plan['evidence_event_ids'] || array_diff($plan['evidence_event_ids'], $ids)) {
                throw new InvalidArgumentException('Plan sin pasos o evidencia del caso.');
            }
            if ($plan['message'] !== null) {
                $this->text($plan['message'], 1800);
                if ($rules['contact_prohibited'] || $rules['reevaluate_at'] || $rules['waiting_for_customer']
                    || $snapshot['state']['status'] !== 'open' || in_array($plan['action'], ['wait', 'no_action'], true)) {
                    throw new InvalidArgumentException('No corresponde redactar contacto bajo una restricción.');
                }
            }
            $price = $plan['proposed_total_cents'];
            $days = $plan['additional_delivery_days'];
            if ($price !== null && (! is_int($price) || $price < 1 || ! $snapshot['quote'] || $price > $snapshot['quote']['total_cents'])) {
                throw new InvalidArgumentException('Total propuesto fuera de la cotización.');
            }
            $economics = $snapshot['production_economics'] ?? null;
            if ($price !== null && $economics !== null
                && (! $economics['all_lines_costed'] || $price < $economics['minimum_proposed_quote_total_cents'])) {
                throw new InvalidArgumentException('La propuesta requiere costos completos y no puede quedar por debajo del costo de producción más impuestos y envío.');
            }
            if ($days !== null && (! is_int($days) || $days < 1 || $days > 365)) {
                throw new InvalidArgumentException('Días adicionales inválidos.');
            }
            if ($price !== null || $days !== null) {
                $supported = array_filter($candidate['signals'], fn ($signal) => in_array($signal['kind'], ['objection', 'change_request', 'conditional_acceptance'], true)
                    && in_array($signal['event_id'], $plan['evidence_event_ids'], true));
                if (! $supported || ! $plan['checks'] || $plan['action'] !== 'escalate') {
                    throw new InvalidArgumentException('Una concesión requiere evidencia, comprobaciones y aprobación interna.');
                }
            }
        }
        if (! in_array($candidate['action'], $actions, true)) {
            throw new InvalidArgumentException('Falta el plan de la acción principal.');
        }
    }

    private function text(mixed $value, int $limit): void
    {
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > $limit) {
            throw new InvalidArgumentException('Texto de plan inválido.');
        }
    }
}
