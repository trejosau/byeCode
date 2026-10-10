import { Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Pencil, X } from 'lucide-react';
import { Errors } from '@/components/byecode/journey';
import {
    crmControl,
    customerProfileUrl,
    crmLink,
    eventLabel,
    eventExcerpt,
    ProfileValue,
} from '@/components/byecode/crm';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { date } from '@/lib/byecode';

export type CommercialDimension = {
    label: string;
    type: 'select' | 'multiple' | 'text';
    help: string;
    options: Record<string, string>;
};
export type CommercialDimensions = Record<string, CommercialDimension>;
export type CommercialFacts = Record<
    string,
    {
        id: number;
        value: string | string[] | null;
        labels: string[];
        nature: string;
        confidence: string;
        evidence: number[];
        observed_at: string;
    }
>;
type Evidence = {
    id: number;
    type: string;
    occurred_at: string;
    data: unknown;
    actor?: string;
};

export function EvidencePicker({
    events,
    selected,
    onChange,
    id,
}: {
    events: Evidence[];
    selected: number[];
    onChange: (ids: number[]) => void;
    id: string;
}) {
    return (
        <fieldset className="space-y-3">
            <legend className="mb-2 text-sm font-medium">
                Evidencia de esta clasificación
            </legend>
            <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border bg-background p-2">
                {events
                    .filter((e) => e.actor !== 'ai')
                    .map((event) => (
                        <label
                            key={event.id}
                            className="flex cursor-pointer items-start gap-3 rounded-md p-2 hover:bg-muted"
                        >
                            <input
                                id={`${id}-${event.id}`}
                                type="checkbox"
                                className="mt-1 size-4 shrink-0 accent-primary"
                                checked={selected.includes(event.id)}
                                onChange={(e) =>
                                    onChange(
                                        e.target.checked
                                            ? [...selected, event.id]
                                            : selected.filter(
                                                  (item) => item !== event.id,
                                              ),
                                    )
                                }
                            />
                            <span className="min-w-0 text-sm">
                                <span className="block font-medium">
                                    {eventLabel(event.type)} · Evento {event.id}
                                </span>
                                <span className="mt-1 block text-xs text-muted-foreground">
                                    {date(event.occurred_at)}
                                </span>
                                <span className="mt-1 block break-words text-muted-foreground">
                                    {eventExcerpt(event.data).slice(0, 350)}
                                </span>
                            </span>
                        </label>
                    ))}
                {!events.some((e) => e.actor !== 'ai') && (
                    <p className="p-3 text-sm text-muted-foreground">
                        Todavía no hay evidencia. Registra primero la
                        conversación o solicitud del cliente.
                    </p>
                )}
            </div>
            <p className="text-xs text-muted-foreground">
                Selecciona hasta 20 eventos de este cliente.
            </p>
        </fieldset>
    );
}

function DimensionEditor({
    customerId,
    dimensionKey,
    definition,
    fact,
    events,
    onClose,
}: {
    customerId: number;
    dimensionKey: string;
    definition: CommercialDimension;
    fact: CommercialFacts[string] | undefined;
    events: Evidence[];
    onClose: () => void;
}) {
    const form = useForm({
        name: 'commercial.' + dimensionKey,
        scope: 'cliente',
        value_json: JSON.stringify(fact?.value ?? null),
        confidence: 'high',
        evidence: [] as number[],
    });
    const value = JSON.parse(form.data.value_json) as string | string[] | null;
    const setValue = (next: string | string[] | null) =>
        form.setData('value_json', JSON.stringify(next));
    return (
        <form
            className="space-y-4 rounded-lg bg-muted/50 p-4"
            aria-label={
                'Corregir ' + definition.label.toLocaleLowerCase('es-MX')
            }
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/customers/${customerId}/facts`, {
                    preserveScroll: true,
                    onSuccess: onClose,
                });
            }}
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h4 className="text-sm font-medium">
                        Confirmar o corregir{' '}
                        {definition.label.toLocaleLowerCase('es-MX')}
                    </h4>
                    <p className="mt-1 max-w-prose text-sm leading-6 text-muted-foreground">
                        {definition.help}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Cerrar editor"
                    disabled={form.processing}
                    onClick={onClose}
                >
                    <X />
                </Button>
            </div>
            <Errors errors={form.errors} />
            {definition.type === 'select' && (
                <label className="block space-y-2 text-sm">
                    <span>Valor confirmado</span>
                    <select
                        autoFocus
                        className={crmControl + ' w-full'}
                        value={typeof value === 'string' ? value : ''}
                        onChange={(e) => setValue(e.target.value || null)}
                    >
                        <option value="">Por confirmar</option>
                        {Object.entries(definition.options).map(
                            ([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ),
                        )}
                    </select>
                </label>
            )}
            {definition.type === 'multiple' && (
                <fieldset className="grid gap-3 sm:grid-cols-2">
                    <legend className="mb-3 text-sm">
                        Valores confirmados
                    </legend>
                    {Object.entries(definition.options).map(
                        ([key, label], index) => (
                            <label
                                key={key}
                                className="flex items-start gap-2 text-sm"
                            >
                                <input
                                    autoFocus={index === 0}
                                    type="checkbox"
                                    className="mt-0.5 size-4 accent-primary"
                                    checked={
                                        Array.isArray(value) &&
                                        value.includes(key)
                                    }
                                    onChange={(e) => {
                                        const current = Array.isArray(value)
                                            ? value
                                            : [];
                                        const next = e.target.checked
                                            ? [...current, key]
                                            : current.filter(
                                                  (item) => item !== key,
                                              );
                                        setValue(next.length ? next : null);
                                    }}
                                />
                                {label}
                            </label>
                        ),
                    )}
                </fieldset>
            )}
            {definition.type === 'text' && (
                <label className="block space-y-2 text-sm">
                    <span>Oportunidad y condiciones</span>
                    <textarea
                        autoFocus
                        className={crmControl + ' h-auto w-full py-2'}
                        rows={3}
                        maxLength={1000}
                        value={typeof value === 'string' ? value : ''}
                        onChange={(e) => setValue(e.target.value || null)}
                        placeholder="Describe el desarrollo previsto, sus condiciones y plazo."
                    />
                </label>
            )}
            <EvidencePicker
                events={events}
                selected={form.data.evidence}
                onChange={(ids) => form.setData('evidence', ids)}
                id={dimensionKey}
            />
            <p className="max-w-prose text-xs leading-5 text-muted-foreground">
                Guardar «Por confirmar» retira la clasificación anterior y
                conserva su historial. Una hipótesis no autoriza descuentos,
                crédito ni condiciones especiales.
            </p>
            <div className="flex flex-wrap gap-2">
                <Button
                    disabled={
                        form.processing ||
                        !form.data.evidence.length ||
                        form.data.evidence.length > 20
                    }
                >
                    {form.processing ? 'Guardando…' : 'Guardar con evidencia'}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onClose}
                    disabled={form.processing}
                >
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

export function CommercialProfile({
    customerId,
    directoryQuery = '',
    dimensions,
    profile,
    events,
    readOnly,
}: {
    customerId: number;
    directoryQuery?: string;
    dimensions: CommercialDimensions;
    profile: CommercialFacts;
    events: Evidence[];
    readOnly: boolean;
}) {
    const [editing, setEditing] = useState<string | null>(null);
    const reviewButtons = useRef<Record<string, HTMLButtonElement | null>>({});
    return (
        <section
            aria-labelledby="commercial-profile-title"
            className="overflow-hidden rounded-xl border"
        >
            <header className="border-b px-5 py-4">
                <h2 id="commercial-profile-title" className="font-semibold">
                    Perfil comercial
                </h2>
                <p className="mt-1 text-sm leading-6 text-muted-foreground">
                    Estructura y necesidades del cliente, respaldadas por
                    evidencia.
                </p>
            </header>
            <div className="divide-y">
                {Object.entries(dimensions).map(([key, definition]) => {
                    const fact = profile[key];
                    return (
                        <div key={key} className="space-y-4 px-5 py-4">
                            <div className="grid items-start gap-3 sm:grid-cols-[160px_minmax(0,1fr)_auto]">
                                <h3 className="text-sm text-muted-foreground">
                                    {definition.label}
                                </h3>
                                <div className="min-w-0 space-y-2 text-sm">
                                    <ProfileValue fact={fact} />
                                    {fact?.labels.length > 0 && (
                                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                            <span>
                                                {fact.nature === 'confirmed'
                                                    ? 'Confirmado por comercio'
                                                    : 'Pendiente de revisión'}
                                            </span>
                                            {fact.evidence.map((id) => (
                                                <Link
                                                    key={id}
                                                    className={crmLink}
                                                    href={customerProfileUrl(
                                                        customerId,
                                                        directoryQuery,
                                                        'activity',
                                                        id,
                                                    )}
                                                >
                                                    Evento {id}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                {!readOnly && (
                                    <Button
                                        type="button"
                                        ref={(element) => {
                                            reviewButtons.current[key] =
                                                element;
                                        }}
                                        variant="ghost"
                                        size="sm"
                                        className="justify-self-start sm:justify-self-end"
                                        aria-expanded={editing === key}
                                        onClick={() =>
                                            setEditing(
                                                editing === key ? null : key,
                                            )
                                        }
                                    >
                                        <Pencil className="size-3.5" />
                                        <span>
                                            Revisar
                                            <span className="sr-only">
                                                {' '}
                                                {definition.label.toLocaleLowerCase(
                                                    'es-MX',
                                                )}
                                            </span>
                                        </span>
                                    </Button>
                                )}
                                {readOnly &&
                                    fact?.nature === 'confirmed' &&
                                    fact.labels.length > 0 && (
                                        <Badge variant="outline">
                                            Confirmado
                                        </Badge>
                                    )}
                            </div>
                            {editing === key && !readOnly && (
                                <DimensionEditor
                                    key={key}
                                    customerId={customerId}
                                    dimensionKey={key}
                                    definition={definition}
                                    fact={fact}
                                    events={events}
                                    onClose={() => {
                                        setEditing(null);
                                        reviewButtons.current[key]?.focus();
                                    }}
                                />
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
