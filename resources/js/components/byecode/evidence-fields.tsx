import { money } from '@/lib/byecode';

const labels: Record<string, string> = {
    status: 'Estado',
    proposal_amount_mxn: 'Importe de propuesta (MXN)',
    total_cents: 'Total',
    version: 'Versión',
    contact_allowed: 'Contacto permitido',
    contact_not_before: 'Contactar a partir de',
    action: 'Acción',
    action_type: 'Acción',
    section: 'Sección',
    note: 'Observación',
    topic: 'Tema',
    source: 'Fuente',
    verified_until: 'Válido hasta',
    reference: 'Referencia',
    amount_cents: 'Importe',
    conditions: 'Condiciones',
    reason: 'Motivo',
    desired_date: 'Fecha solicitada',
};
export function EvidenceFields({ data }: { data: Record<string, unknown> }) {
    return (
        <dl className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs">
            {Object.entries(data)
                .filter(
                    ([key, value]) =>
                        labels[key] &&
                        (value === null || typeof value !== 'object'),
                )
                .map(([key, value]) => (
                    <div key={key}>
                        <dt className="text-muted-foreground">{labels[key]}</dt>
                        <dd className="mt-1 break-words">
                            {value === null
                                ? 'Sin dato'
                                : typeof value === 'boolean'
                                  ? value
                                      ? 'Sí'
                                      : 'No'
                                  : key.endsWith('_cents') &&
                                      typeof value === 'number'
                                    ? money(value)
                                    : typeof value === 'string' ||
                                        typeof value === 'number'
                                      ? value
                                      : 'Sin dato'}
                        </dd>
                    </div>
                ))}
        </dl>
    );
}
