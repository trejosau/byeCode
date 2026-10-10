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
    message: 'Mensaje',
    text: 'Texto',
    notes: 'Notas',
    lines: 'Productos',
    quantity: 'Cantidad',
    name: 'Producto',
    color: 'Color',
    size: 'Talla',
    terms: 'Condiciones',
    decision: 'Decisión',
    kind: 'Tipo',
    company: 'Empresa',
    search: 'Búsqueda',
    title: 'Título',
    unit_price_cents: 'Precio por pieza',
};
export function readableValue(value: unknown): string {
    if (value === null || value === undefined) return 'Sin dato';
    if (typeof value === 'boolean') return value ? 'Sí' : 'No';
    if (Array.isArray(value)) return value.map(readableValue).join(' · ');
    if (typeof value === 'object')
        return Object.entries(value)
            .filter(
                ([key]) =>
                    !key.endsWith('_id') && !['id', 'images'].includes(key),
            )
            .map(
                ([key, item]) =>
                    `${labels[key] ?? key.replaceAll('_', ' ')}: ${key.endsWith('_cents') && typeof item === 'number' ? money(item) : readableValue(item)}`,
            )
            .join(' · ');
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : 'Sin dato';
}
export function EvidenceFields({ data }: { data: Record<string, unknown> }) {
    return (
        <dl className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs">
            {Object.entries(data)
                .filter(([key, value]) => labels[key] && value !== undefined)
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
                                      : readableValue(value)}
                        </dd>
                    </div>
                ))}
        </dl>
    );
}
