<?php

namespace App\Domain\Decision;

use App\Domain\Support\Utc;
use App\Models\CommercialCase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class BehaviorEvidence
{
    public const SECTIONS = ['products' => 'Productos', 'prices' => 'Precios', 'terms' => 'Condiciones', 'decision' => 'Decisión', 'conversation' => 'Conversación'];

    /** @return array<string, mixed> */
    public function context(CommercialCase $case, CarbonImmutable $at): array
    {
        $sections = [];
        $scroll = [];
        $evidence = [];
        $count = 0;
        $rows = DB::table('activity_events')->where('commercial_case_id', $case->id)
            ->where('customer_id', $case->customer_id)->where('occurred_at', '<=', $at)->where('ingested_at', '<=', $at)->orderBy('id')->cursor();
        foreach ($rows as $row) {
            $data = json_decode($row->data, true);
            $version = $data['version'] ?? $data['quote_version'] ?? null;
            $section = $data['section'] ?? '';
            $key = ($version ?? 'unknown').':'.$section;
            $text = null;
            if ($row->actor === 'customer' && isset(self::SECTIONS[$section]) && in_array($row->type, ['ui.section_visible', 'ui.section_visibility_duration'], true)) {
                $sections[$key] ??= ['section' => $section, 'label' => self::SECTIONS[$section], 'version' => $version, 'visible_events' => 0, 'active_duration_ms' => 0];
                if ($row->type === 'ui.section_visible') {
                    $sections[$key]['visible_events']++;
                    $text = self::SECTIONS[$section].': sección visible en el navegador.';
                } else {
                    $duration = max(0, min(30000, (int) ($data['duration_ms'] ?? 0)));
                    $sections[$key]['active_duration_ms'] += $duration;
                    $text = self::SECTIONS[$section].': '.round($duration / 1000, 1).' s de permanencia activa reportada en este evento.';
                }
            } elseif ($row->actor === 'customer' && $row->type === 'ui.scroll_depth_reached') {
                $depth = max(0, min(100, (int) ($data['depth'] ?? 0)));
                $scrollKey = (string) ($version ?? 'unknown');
                $scroll[$scrollKey] = ['version' => $version, 'max_depth' => max($depth, $scroll[$scrollKey]['max_depth'] ?? 0)];
                $text = 'Desplazamiento reportado: '.$depth.'% de la página; no demuestra lectura completa.';
            } elseif ($row->source === 'application') {
                $label = ['request.submitted' => 'Solicitud enviada por el cliente', 'buyer.accept' => 'Aceptación registrada (revisar alcance y condiciones)', 'buyer.reject' => 'Rechazo registrado',
                    'buyer.change' => 'Solicitud de cambios registrada', 'sale.payment' => 'Pago registrado', 'sale.formalized' => 'Formalización registrada',
                    'sale.rejected' => 'Rechazo comercial registrado', 'sale.cancelled' => 'Cancelación registrada'][$row->type] ?? null;
                if ($label) {
                    $text = $label;
                    if (isset($data['amount_cents'])) {
                        $text .= ': $'.number_format($data['amount_cents'] / 100, 2, '.', ',').' MXN';
                    }
                    if (! empty($data['conditions'])) {
                        $text .= '. Condiciones: '.$data['conditions'];
                    }
                    foreach ($data['lines'] ?? [] as $line) {
                        $reference = isset($line['line_id']) ? 'Línea '.$line['line_id'] : 'Producto '.($line['product_id'] ?? 'solicitado');
                        $text .= '. '.$reference.': '.$line['quantity'].' unidades';
                        if (isset($line['proposed_total_cents'])) {
                            $text .= ', total solicitado $'.number_format($line['proposed_total_cents'] / 100, 2, '.', ',').' MXN';
                        }
                    }
                    if (! empty($data['reference'])) {
                        $text .= '. Referencia: '.$data['reference'];
                    }
                }
            }
            if ($text === null) {
                continue;
            }
            $count++;
            // Keep every business fact and the latest sample per navigation type/section/version.
            $bucket = $row->source === 'application' ? $row->event_id : $row->type.':'.$key;
            $evidence[$bucket] = ['event_id' => $row->event_id, 'type' => $row->type, 'source' => $row->source,
                'occurred_at' => Utc::iso(CarbonImmutable::parse($row->occurred_at, 'UTC')), 'version' => $version, 'summary' => $text, 'data' => $data];
        }

        return ['sections' => array_values($sections), 'scroll' => array_values($scroll), 'events' => array_values($evidence),
            'total_relevant_events' => $count, 'omitted_navigation_samples' => $count - count($evidence),
            'measurement_note' => 'Duraciones reportadas por el navegador con pestaña visible y actividad en los últimos 60 s. Secciones simultáneas pueden solaparse: no sumar como tiempo total. Sin datos no significa desinterés. Separado por versión; versión desconocida no equivale a la actual.'];
    }

    /** @param array<string, mixed> $snapshot
     * @return array<string, array<string, mixed>>
     */
    public function catalog(array $snapshot): array
    {
        $catalog = array_column($snapshot['behavior_context']['events'] ?? [], null, 'event_id');
        foreach ($snapshot['events'] as $event) {
            $message = $event['data']['message'] ?? null;
            $version = $event['data']['version'] ?? null;
            $prefix = 'Decisión estructurada del comprador: ';
            if (is_string($message) && str_starts_with($message, $prefix)) {
                $payload = json_decode(substr($message, strlen($prefix)), true);
                if (is_array($payload) && isset($catalog[$payload['event_id'] ?? ''])) {
                    $message = $catalog[$payload['event_id']]['summary'];
                    $version = $payload['quote_version'] ?? null;
                }
            }
            $catalog[$event['event_id']] = ['event_id' => $event['event_id'], 'type' => $event['event_type'], 'source' => 'case_event',
                'occurred_at' => $event['occurred_at'], 'version' => $version,
                'summary' => $message ?? match ($event['event_type']) {
                    'case.opened' => 'Solicitud abierta.', 'case.closed' => 'Caso cerrado: '.$event['data']['status'],
                    'quote.published' => 'Cotización publicada.', 'proposal.viewed' => 'Apertura de la cotización registrada.',
                    'constraint.updated' => 'Restricción de contacto actualizada.', 'action.executed' => 'Acción comercial registrada.',
                    default => 'Hecho registrado en el historial del caso.',
                }];
        }

        return $catalog;
    }
}
