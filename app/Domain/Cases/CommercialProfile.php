<?php

namespace App\Domain\Cases;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CommercialProfile
{
    /** @return array<string, array<string, mixed>> */
    public function dimensions(): array
    {
        return [
            'business_type' => ['label' => 'Tipo de negocio', 'type' => 'select', 'help' => 'Qué hace el cliente con las prendas.', 'options' => [
                'own_brand' => 'Marca propia', 'reseller' => 'Reventa', 'distributor' => 'Distribución', 'uniforms' => 'Uniformes para su organización', 'events' => 'Eventos y promociones', 'other' => 'Otro']],
            'operating_scale' => ['label' => 'Escala de operación', 'type' => 'select', 'help' => 'Estructura operativa confirmada; el importe de un pedido no determina esta escala.', 'options' => [
                'individual' => 'Operación individual', 'single_site_team' => 'Equipo en una sede', 'multi_site' => 'Operación con varias sedes', 'corporate_group' => 'Cadena o grupo corporativo']],
            'purchase_frequency' => ['label' => 'Recurrencia de compra', 'type' => 'select', 'help' => 'Distingue una compra puntual de una relación de abastecimiento.', 'options' => [
                'one_off' => 'Compra puntual', 'seasonal' => 'Por temporada o campaña', 'recurring' => 'Reposición recurrente', 'supply_program' => 'Programa de abastecimiento']],
            'buying_process' => ['label' => 'Proceso de compra', 'type' => 'select', 'help' => 'Cómo se decide y autoriza una compra.', 'options' => [
                'direct' => 'Decisión directa', 'multiple_approvals' => 'Varias personas aprueban', 'procurement' => 'Área de compras y alta de proveedor', 'tender' => 'Concurso o licitación']],
            'priorities' => ['label' => 'Prioridades declaradas', 'type' => 'multiple', 'help' => 'Selecciona solo las prioridades respaldadas por el cliente.', 'options' => [
                'price' => 'Precio', 'quality' => 'Calidad y consistencia', 'deadline' => 'Fecha de entrega', 'continuity' => 'Continuidad de suministro', 'flexibility' => 'Flexibilidad de cantidades', 'customization' => 'Personalización']],
            'service_requirements' => ['label' => 'Requisitos de atención', 'type' => 'multiple', 'help' => 'Requisitos de la relación comercial. Las condiciones exclusivas de un pedido van en su cotización.', 'options' => [
                'samples' => 'Muestras y aprobación previa', 'supplier_approval' => 'Homologación de proveedor', 'quality_traceability' => 'Control de calidad y trazabilidad', 'purchase_order' => 'Orden de compra', 'scheduled_supply' => 'Calendario de suministro', 'multi_destination' => 'Entregas en varios destinos', 'custom_packaging' => 'Empaque o etiquetado particular', 'payment_terms' => 'Condiciones de pago por acordar']],
            'development_opportunity' => ['label' => 'Oportunidad de desarrollo', 'type' => 'text', 'help' => 'Describe qué podría crecer, en qué plazo y bajo qué condiciones. Una previsión no es una compra confirmada.', 'options' => []],
        ];
    }

    /** @param array<string, mixed> $fact */
    public function validate(array $fact): void
    {
        if (! str_starts_with($fact['name'], 'commercial.')) {
            return;
        }
        $dimension = $this->dimensions()[substr($fact['name'], strlen('commercial.'))] ?? null;
        if (! $dimension || $fact['scope'] !== 'cliente') {
            throw ValidationException::withMessages(['name' => 'Selecciona una dimensión comercial válida con alcance cliente.']);
        }
        $value = json_decode($fact['value_json'], true, 32, JSON_THROW_ON_ERROR);
        // JSON null explicitly withdraws a classification while preserving its history.
        if ($value === null) {
            return;
        }
        $valid = match ($dimension['type']) {
            'select' => is_string($value) && array_key_exists($value, $dimension['options']),
            'multiple' => is_array($value) && array_is_list($value) && count($value) > 0 && count($value) <= count($dimension['options'])
                && collect($value)->every(fn ($v) => is_string($v) && array_key_exists($v, $dimension['options'])) && count(array_unique($value, SORT_REGULAR)) === count($value),
            'text' => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 1000,
            default => false,
        };
        if (! $valid) {
            throw ValidationException::withMessages(['value_json' => 'El valor no corresponde a las opciones de esta dimensión comercial.']);
        }
    }

    /** @param list<int> $customerIds
     * @return array<int, array<string, array<string, mixed>>>
     */
    public function summaries(array $customerIds): array
    {
        $dimensions = $this->dimensions();
        $rows = DB::table('profile_facts')->whereIn('customer_id', $customerIds)->where('status', 'active')->where('scope', 'cliente')
            ->whereIn('name', array_map(fn ($key) => 'commercial.'.$key, array_keys($dimensions)))
            ->where('observed_at', '<=', now())->where('created_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()))->orderByDesc('id')->get();
        $summaries = [];
        foreach ($rows as $row) {
            $key = substr($row->name, strlen('commercial.'));
            if (isset($summaries[$row->customer_id][$key])) {
                continue;
            }
            $value = json_decode($row->value, true);
            $options = $dimensions[$key]['options'];
            $labels = $value === null ? [] : array_map(fn ($v) => $options[$v] ?? (string) $v, is_array($value) ? $value : [$value]);
            $summaries[$row->customer_id][$key] = ['id' => $row->id, 'value' => $value, 'labels' => $labels,
                'nature' => $row->nature, 'confidence' => $row->confidence, 'evidence' => json_decode($row->evidence, true), 'observed_at' => $row->observed_at];
        }

        return $summaries;
    }

    /** Filters deliberately use confirmed, currently valid classifications only.
     * @param  Builder<Customer>  $customers
     * @param  array<string, string|null>  $filters
     */
    public function filter(Builder $customers, array $filters): void
    {
        foreach ($filters as $key => $value) {
            if (! $value) {
                continue;
            }
            $customers->whereExists(function ($q) use ($key, $value) {
                $q->selectRaw('1')->from('profile_facts')->whereColumn('customer_id', 'customers.id')
                    ->where('name', 'commercial.'.$key)->where('scope', 'cliente')->where('status', 'active')->where('nature', 'confirmed')
                    ->where('observed_at', '<=', now())->where('created_at', '<=', now())
                    ->where(fn ($valid) => $valid->whereNull('valid_until')->orWhere('valid_until', '>', now()))->whereJsonContains('profile_facts.value', $value);
            });
        }
    }

    public function instructions(): string
    {
        $vocabulary = [];
        foreach ($this->dimensions() as $key => $dimension) {
            $vocabulary['commercial.'.$key] = ['label' => $dimension['label'], 'type' => $dimension['type'], 'options' => $dimension['options']];
        }

        return ' Para dimensiones comerciales usa este vocabulario con scope=cliente: '.json_encode($vocabulary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            .'. Omite lo desconocido. Distingue importe de una cotización, compras pagadas, estructura del negocio y oportunidad futura. Nunca deduzcas escala, solvencia, recurrencia o trato preferente solo por nombre de marca, importe, navegación o silencio. Requisitos puntuales no son condiciones permanentes. Usa texto libre y un alcance específico para otras propiedades. Un crédito, descuento, plazo o prioridad de atención requiere autorización comercial independiente.';
    }
}
