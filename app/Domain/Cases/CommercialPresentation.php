<?php

namespace App\Domain\Cases;

/** Presentation of known seeded templates; never mutates the sealed evidence. */
final class CommercialPresentation
{
    /** @var array<string, string>|null */
    private ?array $templates = null;

    public function text(?string $value, bool $seeded): ?string
    {
        if (! $seeded || $value === null) {
            return $value;
        }
        if ($this->templates === null) {
            $this->templates = [];
            $old = json_decode((string) file_get_contents(database_path('seeders/fixtures/operational-demo-v1/scenarios.json')), true, 512, JSON_THROW_ON_ERROR);
            $new = json_decode((string) file_get_contents(database_path('seeders/fixtures/operational-demo-v2/scenarios.json')), true, 512, JSON_THROW_ON_ERROR);
            $operations = array_column($new['operations'], null, 'key');
            foreach ($old['operations'] as $operation) {
                foreach (['body', 'reason', 'conditions'] as $field) {
                    if (isset($operation[$field], $operations[$operation['key']][$field])) {
                        $this->templates[$operation[$field]] = $operations[$operation['key']][$field];
                    }
                }
            }
        }
        if (isset($this->templates[$value])) {
            return $this->templates[$value];
        }
        if ($value === 'Observación sintética del catálogo de demostración') {
            return 'Ficha comercial; medidas orientativas por confirmar en muestra.';
        }
        if (str_starts_with($value, 'Precios de demostración en MXN. ')) {
            return str_replace(['Precios de demostración en MXN.', 'Abastecimiento sujeto a confirmación operativa.'], ['Precios en MXN.', 'Entrega sujeta a fecha acordada.'], $value);
        }
        if (preg_match('/^DEMO-AUT-([A-Z0-9]+-V[0-9]+)$/D', $value, $match)) {
            return 'AUT-'.$match[1];
        }
        if (preg_match('/^DEMO-([A-Z0-9.\-]+) · registro sintético del escenario; sin transacción bancaria\.$/D', $value, $match)) {
            return 'REF-'.$match[1];
        }

        return $value;
    }

    /** @param array<string, mixed> $quote
     * @return array<string, mixed>
     */
    public function quote(array $quote, bool $seeded): array
    {
        foreach (['terms', 'authorization_reference'] as $field) {
            if (isset($quote[$field])) {
                $quote[$field] = $this->text($quote[$field], $seeded);
            }
        }

        return $quote;
    }
}
