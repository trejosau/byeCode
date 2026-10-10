<?php

namespace App\Domain\Decision;

use App\Domain\Support\Utc;
use Carbon\CarbonImmutable;

final class RuleEngine
{
    public const ACTIONS = ['contact', 'request_information', 'resolve_objection', 'escalate', 'wait', 'no_action'];

    public const CONTACT = ['contact', 'request_information', 'resolve_objection'];

    /**
     * @param  array<string, mixed>  $s
     * @param  list<array<string, mixed>>  $signals
     * @return array<string, mixed>
     */
    public function assess(array $s, array $signals = []): array
    {
        $at = Utc::parse($s['evaluation_at']);
        $state = $s['state'];
        $blocked = [];
        $checks = [];
        $dates = [];
        $evidence = [];
        $closed = $state['status'] !== 'open';
        $checks['R1'] = ['blocked' => $closed, 'reason' => $closed ? 'Caso cerrado: únicamente no_action.' : 'Caso abierto.'];
        if ($closed) {
            foreach (array_diff(self::ACTIONS, ['no_action']) as $a) {
                $blocked[$a][] = 'R1';
            }
        }
        $semanticRefusal = array_filter($signals, fn ($x) => $x['kind'] === 'contact_refusal');
        $permission = $state['contact_allowed'] === false || count($semanticRefusal) > 0;
        $checks['R2'] = ['blocked' => $permission, 'reason' => $permission ? 'El contacto no está autorizado.' : 'Sin prohibición vigente identificada.'];
        $minimum = $state['contact_not_before'] ? Utc::parse($state['contact_not_before']) : null;
        $r3 = $minimum !== null && $at->lessThan($minimum);
        if ($r3) {
            $dates[] = $minimum;
        }
        $checks['R3'] = ['blocked' => $r3, 'reason' => $r3 ? 'Fecha mínima de contacto todavía no alcanzada.' : 'Sin bloqueo por fecha mínima.'];
        $lastContact = $s['metrics']['last_contact_at'] ? Utc::parse($s['metrics']['last_contact_at']) : null;
        $r4 = $lastContact !== null && $at->lessThan($lastContact->addHours(24)) && ! $s['metrics']['new_message_after_contact'];
        if ($r4) {
            $dates[] = $lastContact->addHours(24);
        }
        $checks['R4'] = ['blocked' => $r4, 'reason' => $r4 ? 'Pausa de 24 horas desde la última acción, sin mensaje posterior.' : ($s['metrics']['new_message_after_contact'] ? 'Mensaje estrictamente posterior: levanta únicamente R4.' : 'Sin pausa de contacto vigente.')];
        foreach (['R2' => $permission, 'R3' => $r3, 'R4' => $r4] as $rule => $active) {
            if ($active) {
                foreach (self::CONTACT as $a) {
                    $blocked[$a][] = $rule;
                }
            }
        }
        $onlyViews = $s['metrics']['messages'] === 0;
        $checks['R5'] = ['blocked' => $onlyViews, 'reason' => $onlyViews ? 'Aperturas e importe no demuestran intención ni urgencia.' : 'Interpretaciones sujetas a evidencia y revisión semántica.'];
        $checks['R6'] = ['blocked' => (bool) $s['integrity_blocked'], 'reason' => $s['integrity_blocked'] ? 'Historial incompleto o conflictivo: evaluación bloqueada.' : 'No hay ejecución automática; los faltantes permanecen explícitos.'];
        $waitSignals = array_filter($signals, fn ($x) => in_array($x['kind'], ['wait_request', 'approval_pending'], true));
        if ($waitSignals) {
            foreach (self::CONTACT as $a) {
                $blocked[$a][] = 'customer_wait';
            }
        }
        foreach ($s['rule_evidence'] as $key => $id) {
            if ($id && (($key === 'closure' && $closed) || ($key === 'permission' && $permission) || ($key === 'date' && $r3) || ($key === 'contact' && $r4))) {
                $evidence[] = $id;
            }
        }
        foreach ([...$semanticRefusal, ...$waitSignals] as $signal) {
            $evidence[] = $signal['event_id'];
        }
        usort($dates, fn (CarbonImmutable $a, CarbonImmutable $b) => $b->getTimestamp() <=> $a->getTimestamp());

        return ['allowed' => array_values(array_diff(self::ACTIONS, array_keys($blocked))), 'blocked' => $blocked, 'checks' => $checks,
            'reevaluate_at' => isset($dates[0]) ? Utc::iso($dates[0]) : null,
            'contact_prohibited' => $permission, 'waiting_for_customer' => count($waitSignals) > 0,
            'evidence' => array_values(array_unique($evidence))];
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array<string, mixed>|null
     */
    public function deterministic(array $s): ?array
    {
        $r = $this->assess($s);
        if ($s['integrity_blocked']) {
            return null;
        }
        if ($s['state']['status'] !== 'open') {
            return $this->result($s, 'no_action', 'not_applicable', null,
                'El caso tiene un cierre confirmado. No corresponde intervenir ni reabrirlo por nuevas interacciones.', $r['evidence']);
        }
        // Do not skip language interpretation if messages exist: an internal escalation may still be needed.
        if ($s['metrics']['messages'] > 0) {
            return null;
        }
        if ($r['contact_prohibited']) {
            return $this->result($s, 'no_action', 'not_applicable', null,
                'El contacto está prohibido y no hay otra intervención sustentada. Un cambio autorizado de restricciones requiere reevaluar.', $r['evidence']);
        }
        if ($r['reevaluate_at']) {
            return $this->result($s, 'wait', 'at_date', $r['reevaluate_at'],
                'Existe una pausa de contacto vigente. Reevaluar al vencer la última restricción; esa fecha no autoriza un envío.', $r['evidence']);
        }

        $decision = $this->result($s, 'wait', 'on_new_information', null,
            'No hay un mensaje que sustente una intervención. Esperar información explícita; las vistas y el importe no demuestran intención de compra.',
            array_column($s['events'], 'event_id'));
        $decision['confidence'] = 'low';
        $decision['confidence_reason'] = 'La intención del comprador es desconocida. No es una probabilidad de compra.';
        $decision['missing_information'] = ['Intención o requisitos explícitos del comprador.'];

        return $decision;
    }

    /**
     * @param  array<string, mixed>  $s
     * @param  list<string>  $evidence
     * @return array<string, mixed>
     */
    public function result(array $s, string $action, string $timing, ?string $date, string $reason, array $evidence): array
    {
        return ['case_id' => $s['case_id'], 'action' => $action, 'timing' => $timing, 'scheduled_for' => $date,
            'reason' => $reason, 'evidence_event_ids' => $evidence, 'confidence' => 'high',
            'confidence_reason' => 'La decisión deriva de una regla verificable; no expresa probabilidad de compra.',
            'risk_level' => 'low', 'requires_human_review' => false, 'missing_information' => []];
    }
}
