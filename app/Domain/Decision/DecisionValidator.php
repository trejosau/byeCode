<?php

namespace App\Domain\Decision;

use App\Domain\Support\Utc;
use InvalidArgumentException;

final class DecisionValidator
{
    /**
     * @param  array<string, mixed>  $s
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    public function compile(array $s, array $candidate): array
    {
        $expected = ['action', 'timing', 'scheduled_for', 'signals', 'missing_information', 'confidence', 'risk_level', 'requires_human_review'];
        if (array_diff(array_keys($candidate), $expected) || array_diff($expected, array_keys($candidate))) {
            throw new InvalidArgumentException('Contrato semántico incompleto o con campos extra.');
        }
        if (! is_array($candidate['signals']) || ! array_is_list($candidate['signals']) || count($candidate['signals']) > 20
            || ! is_array($candidate['missing_information']) || ! array_is_list($candidate['missing_information'])
            || ! is_bool($candidate['requires_human_review'])) {
            throw new InvalidArgumentException('Tipos de salida semántica inválidos.');
        }
        $events = array_column($s['events'], null, 'event_id');
        $signals = $candidate['signals'];
        $ids = [];
        foreach ($signals as $signal) {
            if (! is_array($signal) || count($signal) !== 3 || ! isset($signal['kind'],$signal['event_id'],$signal['quote'])
                || ! in_array($signal['kind'], SemanticContract::KINDS, true) || ! is_string($signal['event_id'])
                || ! is_string($signal['quote']) || trim($signal['quote']) === '' || mb_strlen($signal['quote']) > 1200) {
                throw new InvalidArgumentException('Señal sin forma válida.');
            }
            $event = $events[$signal['event_id']] ?? null;
            if (! $event || $event['event_type'] !== 'message.received' || ! str_contains($event['data']['message'], $signal['quote'])) {
                throw new InvalidArgumentException('Cita no literal, evidencia ajena o inexistente.');
            }
            $ids[] = $signal['event_id'];
        }
        foreach ($candidate['missing_information'] as $code) {
            if (! is_string($code) || ! array_key_exists($code, SemanticContract::MISSING)) {
                throw new InvalidArgumentException('Faltante no reconocido.');
            }
        }
        $rules = app(RuleEngine::class)->assess($s, $signals);
        if (! in_array($candidate['action'], $rules['allowed'], true)) {
            throw new InvalidArgumentException('Acción bloqueada por políticas o preferencias del comprador.');
        }
        $kinds = array_column($signals, 'kind');
        $action = $candidate['action'];
        if (in_array($action, RuleEngine::CONTACT, true) && ! $signals) {
            throw new InvalidArgumentException('Contacto sin señal explícita.');
        }
        if ($action === 'resolve_objection' && ! array_intersect($kinds, ['question', 'objection'])) {
            throw new InvalidArgumentException('No hay pregunta u objeción explícita.');
        }
        if ($action === 'request_information' && ! array_intersect($candidate['missing_information'], ['size_breakdown', 'customer_requirements'])) {
            throw new InvalidArgumentException('El faltante no corresponde al comprador.');
        }
        if (in_array($action, RuleEngine::CONTACT, true) && array_intersect($candidate['missing_information'], ['stock_confirmation', 'delivery_confirmation', 'authorization', 'commercial_terms', 'quotation_details'])) {
            throw new InvalidArgumentException('Una condición interna sin verificar necesita revisión interna.');
        }
        if ($s['metrics']['messages'] === 0 && ($action === 'contact' || $action === 'resolve_objection' || $candidate['timing'] === 'immediate')) {
            throw new InvalidArgumentException('R5: vistas no justifican intención ni urgencia.');
        }
        if ($action === 'contact' && ! in_array('interest', $kinds, true)) {
            throw new InvalidArgumentException('Seguimiento sin interés explícito sustentado.');
        }
        if ($action === 'wait' && $candidate['timing'] === 'at_date') {
            if (! is_string($candidate['scheduled_for']) || ! $rules['reevaluate_at'] || ! Utc::parse($candidate['scheduled_for'])->equalTo(Utc::parse($rules['reevaluate_at']))) {
                throw new InvalidArgumentException('Fecha de espera sin respaldo determinista; usar nueva información.');
            }
        }
        $templates = [
            'contact' => 'La señal de interés explícito justifica un seguimiento breve con las condiciones verificadas. La decisión requiere confirmación del vendedor.',
            'request_information' => 'Faltan requisitos que debe aportar el comprador. Solicitar únicamente esos datos permitiría continuar la cotización.',
            'resolve_objection' => 'Existe una pregunta u objeción explícita. Revisar la aclaración con las condiciones documentadas antes de realizar cualquier contacto.',
            'escalate' => 'Se requiere revisión interna de la información, condición o dependencia identificada antes de comprometer una respuesta comercial.',
            'wait' => 'Esperar hasta la condición indicada o nueva información del caso y reevaluar. La espera no es autorización para contactar después.',
            'no_action' => 'No existe una intervención sustentada y admisible en el contexto actual. Esto no declara perdida una oportunidad abierta.',
        ];
        $reason = $templates[$action] ?? '';
        if ($rules['contact_prohibited']) {
            $reason .= ' El contacto continúa prohibido.';
        }
        if ($rules['reevaluate_at']) {
            $reason .= ' Sigue vigente una pausa temporal de contacto.';
        }
        $requiresReview = $candidate['requires_human_review'] || $candidate['risk_level'] === 'high'
            || count(array_intersect($kinds, ['ambiguity', 'conditional_acceptance', 'partial_acceptance', 'change_request', 'contact_refusal'])) > 0
            || in_array($action, [...RuleEngine::CONTACT, 'escalate'], true);
        $result = [
            'case_id' => $s['case_id'], 'action' => $action, 'timing' => $candidate['timing'], 'scheduled_for' => $candidate['scheduled_for'],
            'reason' => $reason, 'evidence_event_ids' => array_values(array_unique([...$ids, ...$rules['evidence']])),
            'confidence' => $s['metrics']['messages'] === 0 ? 'low' : $candidate['confidence'],
            'confidence_reason' => $s['metrics']['messages'] === 0 ? 'No hay intención explícita; las aperturas no permiten estimar compra.' : 'Interpretación basada en fragmentos citados, sujeta a revisión; no es una probabilidad de cierre.',
            'risk_level' => $candidate['risk_level'], 'requires_human_review' => $requiresReview,
            'missing_information' => array_values(array_map(fn ($code) => SemanticContract::MISSING[$code], array_unique($candidate['missing_information']))),
        ];
        if (! $result['evidence_event_ids'] && $s['events']) {
            $result['evidence_event_ids'] = [$s['events'][0]['event_id']];
        }
        $this->validate($s, $result, $signals);

        return ['result' => $result, 'signals' => $signals, 'validation' => $rules];
    }

    /**
     * @param  array<string, mixed>  $s
     * @param  array<string, mixed>  $r
     * @param  list<array<string, mixed>>  $signals
     */
    public function validate(array $s, array $r, array $signals = []): void
    {
        $fields = ['case_id', 'action', 'timing', 'scheduled_for', 'reason', 'evidence_event_ids', 'confidence', 'confidence_reason', 'risk_level', 'requires_human_review', 'missing_information'];
        if (count($r) !== 11 || array_diff($fields, array_keys($r)) || $r['case_id'] !== $s['case_id']) {
            throw new InvalidArgumentException('El contrato oficial exige exactamente once campos del mismo caso.');
        }
        $rules = app(RuleEngine::class)->assess($s, $signals);
        if ($s['integrity_blocked'] || ! in_array($r['action'], $rules['allowed'], true)) {
            throw new InvalidArgumentException('Reglas de publicación incumplidas.');
        }
        $timings = match ($r['action']) {
            'wait' => ['at_date', 'on_new_information'], 'no_action' => ['not_applicable'], default => ['immediate', 'within_24h', 'at_date']
        };
        if (! in_array($r['timing'], $timings, true)) {
            throw new InvalidArgumentException('Acción y timing incompatibles.');
        }
        if ($r['timing'] === 'at_date') {
            $date = Utc::parse($r['scheduled_for']);
            if (! $date->greaterThan(Utc::parse($s['evaluation_at'])) || ($rules['reevaluate_at'] && $date->lessThan(Utc::parse($rules['reevaluate_at'])))) {
                throw new InvalidArgumentException('Fecha programada incoherente.');
            }
        } elseif ($r['scheduled_for'] !== null) {
            throw new InvalidArgumentException('scheduled_for debe ser null.');
        }
        foreach (['confidence', 'risk_level'] as $key) {
            if (! in_array($r[$key], ['low', 'medium', 'high'], true)) {
                throw new InvalidArgumentException('Nivel inválido.');
            }
        }
        foreach (['reason', 'confidence_reason'] as $key) {
            if (! is_string($r[$key]) || trim($r[$key]) === '' || mb_strlen($r[$key]) > 3000) {
                throw new InvalidArgumentException('Explicación inválida.');
            }
        }
        if (! is_bool($r['requires_human_review'])) {
            throw new InvalidArgumentException('Revisión debe ser booleana.');
        }
        foreach (['evidence_event_ids', 'missing_information'] as $key) {
            if (! is_array($r[$key]) || ! array_is_list($r[$key])) {
                throw new InvalidArgumentException('Lista inválida.');
            }
            foreach ($r[$key] as $v) {
                if (! is_string($v) || trim($v) === '') {
                    throw new InvalidArgumentException('Elemento inválido.');
                }
            }
        }
        if (count(array_unique($r['evidence_event_ids'])) !== count($r['evidence_event_ids']) || array_diff($r['evidence_event_ids'], array_column($s['events'], 'event_id'))) {
            throw new InvalidArgumentException('Evidencia duplicada, futura o ajena.');
        }
    }
}
