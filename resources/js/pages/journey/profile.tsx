import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    BrainCircuit,
    Plus,
    History,
} from 'lucide-react';
import { useState } from 'react';
import { Errors } from '@/components/byecode/journey';
import {
    CommercialProfile,
    EvidencePicker,
    type CommercialDimensions,
    type CommercialFacts,
} from '@/components/byecode/commercial-profile';
import {
    CrmPage,
    customerProfileUrl,
    crmControl,
    crmLink,
    EvidenceEvent,
    eventLabel,
    type ProfileEvent,
} from '@/components/byecode/crm';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { date, money, statuses } from '@/lib/byecode';

type Fact = {
    id: number;
    name: string;
    scope: string;
    value: unknown;
    nature: string;
    confidence: string;
    status: string;
    evidence: number[];
    created_at: string;
};
type Props = {
    customer: {
        id: number;
        company: string;
        contact_name: string | null;
        email: string | null;
    };
    indicators: {
        requests: number;
        events: number;
        paid_cents: number;
        formalized_cents: number;
        average_paid_per_case_cents: number | null;
        last_activity: string | null;
        digital_activity: { type: string; total: number }[];
    };
    facts: Fact[];
    events: ProfileEvent[];
    selectedEvent: ProfileEvent | null;
    directoryQuery: string;
    activeTab: 'profile' | 'activity' | 'opportunities';
    runs: {
        id: number;
        cutoff: number;
        status: string;
        error: string | null;
    }[];
    cases: {
        id: number;
        title: string;
        public_id: string | null;
        status: string;
    }[];
    readOnly: boolean;
    aiReady: boolean;
    commercialDimensions: CommercialDimensions;
    commercialProfile: CommercialFacts;
};
export default function Profile({
    customer,
    indicators,
    facts,
    events,
    runs,
    cases,
    readOnly,
    aiReady,
    commercialDimensions,
    commercialProfile,
    activeTab,
    selectedEvent,
    directoryQuery,
}: Props) {
    const [adding, setAdding] = useState(false);
    const [analyzing, setAnalyzing] = useState(false);
    const form = useForm({
        name: '',
        scope: 'cliente',
        value_json: '',
        confidence: 'high',
        evidence: [] as number[],
    });
    const valueText = form.data.value_json
        ? (JSON.parse(form.data.value_json) as string)
        : '';
    const factValue = (fact: Fact) => {
        const definition =
            commercialDimensions[fact.name.replace('commercial.', '')];
        if (fact.name.startsWith('commercial.') && definition) {
            if (fact.value === null) return 'Por confirmar';
            return (Array.isArray(fact.value) ? fact.value : [fact.value])
                .map(
                    (value) =>
                        definition.options[String(value)] ?? String(value),
                )
                .join(' · ');
        }
        return typeof fact.value === 'string'
            ? fact.value
            : JSON.stringify(fact.value);
    };
    const renderFact = (fact: Fact) => (
        <article key={fact.id} className="space-y-2 border-b p-4 last:border-0">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <h3 className="text-sm font-medium">
                    {commercialDimensions[fact.name.replace('commercial.', '')]
                        ?.label ?? fact.name}
                </h3>
                <Badge variant="outline">
                    {fact.nature === 'confirmed' ? 'Confirmado' : 'Hipótesis'} ·{' '}
                    {(
                        {
                            active: 'Vigente',
                            superseded: 'Sustituido',
                            conflicting: 'En conflicto',
                        } as Record<string, string>
                    )[fact.status] ?? fact.status}
                </Badge>
            </div>
            <p className="text-sm leading-6 break-words whitespace-pre-wrap">
                {factValue(fact)}
            </p>
            <p className="text-xs text-muted-foreground">
                Contexto: {fact.scope} · Confianza interpretativa:{' '}
                {(
                    { high: 'alta', medium: 'media', low: 'baja' } as Record<
                        string,
                        string
                    >
                )[fact.confidence] ?? fact.confidence}
            </p>
            <div className="flex flex-wrap gap-3 text-xs">
                {fact.evidence.map((id) => (
                    <Link
                        key={id}
                        className={crmLink}
                        href={customerProfileUrl(
                            customer.id,
                            directoryQuery,
                            'activity',
                            id,
                        )}
                    >
                        Evento {id}
                    </Link>
                ))}
            </div>
        </article>
    );
    const particular = facts.filter(
        (f) => !f.name.startsWith('commercial.') && f.status === 'active',
    );
    const conflicts = facts.filter((f) => f.status === 'conflicting');
    const history = facts.filter((f) => f.status === 'superseded');
    const selectedIsOlder =
        selectedEvent && !events.some((event) => event.id === selectedEvent.id);
    return (
        <CrmPage
            detail
            title={customer.company}
            description={[customer.contact_name, customer.email]
                .filter(Boolean)
                .join(' · ')}
            actions={
                <>
                    <Button asChild variant="outline">
                        <Link
                            href={
                                readOnly
                                    ? '/admin'
                                    : '/customers' +
                                      (directoryQuery
                                          ? '?' + directoryQuery
                                          : '')
                            }
                        >
                            <ArrowLeft />
                            {readOnly ? 'Administración' : 'Clientes'}
                        </Link>
                    </Button>
                    {readOnly && (
                        <Badge variant="secondary">Solo lectura</Badge>
                    )}
                </>
            }
        >
            <dl className="grid grid-cols-2 gap-y-5 rounded-xl border bg-muted/20 py-4 md:grid-cols-4">
                {[
                    ['Solicitudes', indicators.requests],
                    ['Formalizado · MXN', money(indicators.formalized_cents)],
                    ['Pagado · MXN', money(indicators.paid_cents)],
                    [
                        'Promedio pagado por oportunidad',
                        indicators.average_paid_per_case_cents === null
                            ? 'Sin pagos confirmados'
                            : money(indicators.average_paid_per_case_cents),
                    ],
                ].map(([label, value]) => (
                    <div key={label} className="min-w-0 px-4">
                        <dt className="text-xs leading-5 text-muted-foreground">
                            {label}
                        </dt>
                        <dd className="mt-1 text-lg font-semibold break-words tabular-nums">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            <nav
                className="grid grid-cols-3 gap-2 border-b sm:flex sm:gap-5"
                aria-label="Secciones del cliente"
            >
                {(
                    [
                        ['profile', 'Perfil comercial'],
                        ['activity', 'Actividad y evidencia'],
                        ['opportunities', 'Oportunidades'],
                    ] as const
                ).map(([key, label]) => (
                    <Link
                        key={key}
                        href={customerProfileUrl(
                            customer.id,
                            directoryQuery,
                            key,
                        )}
                        preserveScroll
                        aria-current={activeTab === key ? 'page' : undefined}
                        className={
                            'border-b-2 px-1 pb-3 text-center text-sm font-medium focus-visible:outline-2 focus-visible:outline-foreground sm:text-left ' +
                            (activeTab === key
                                ? 'border-foreground text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground')
                        }
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            {activeTab === 'profile' && (
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
                    <div className="min-w-0 space-y-6">
                        <CommercialProfile
                            customerId={customer.id}
                            directoryQuery={directoryQuery}
                            dimensions={commercialDimensions}
                            profile={commercialProfile}
                            events={events}
                            readOnly={readOnly}
                        />
                        <section className="overflow-hidden rounded-xl border">
                            <header className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                                <div>
                                    <h2 className="font-semibold">
                                        Necesidades particulares
                                    </h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Propiedades con el contexto de cada
                                        relación.
                                    </p>
                                </div>
                                {!readOnly && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        aria-expanded={adding}
                                        onClick={() => setAdding(!adding)}
                                    >
                                        <Plus />
                                        Documentar
                                    </Button>
                                )}
                            </header>
                            {particular.length ? (
                                particular.map(renderFact)
                            ) : (
                                <p className="p-5 text-sm leading-6 text-muted-foreground">
                                    Todavía no hay necesidades particulares
                                    documentadas. Se incorporan con evidencia
                                    del cliente.
                                </p>
                            )}
                            {adding && !readOnly && (
                                <form
                                    className="space-y-4 border-t bg-muted/30 p-5"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        form.post(
                                            `/customers/${customer.id}/facts`,
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => {
                                                    form.reset();
                                                    setAdding(false);
                                                },
                                            },
                                        );
                                    }}
                                >
                                    <h3 className="font-medium">
                                        Documentar una necesidad particular
                                    </h3>
                                    <Errors errors={form.errors} />
                                    <label className="block space-y-2 text-sm">
                                        <span>Propiedad</span>
                                        <input
                                            autoFocus
                                            required
                                            maxLength={120}
                                            className={crmControl + ' w-full'}
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Ej. Requisito de empaque"
                                        />
                                    </label>
                                    <label className="block space-y-2 text-sm">
                                        <span>Contexto</span>
                                        <input
                                            required
                                            maxLength={120}
                                            className={crmControl + ' w-full'}
                                            value={form.data.scope}
                                            onChange={(e) =>
                                                form.setData(
                                                    'scope',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </label>
                                    <label className="block space-y-2 text-sm">
                                        <span>Valor confirmado</span>
                                        <textarea
                                            required
                                            rows={3}
                                            maxLength={3000}
                                            className={
                                                crmControl +
                                                ' h-auto w-full py-2'
                                            }
                                            value={valueText}
                                            onChange={(e) =>
                                                form.setData(
                                                    'value_json',
                                                    JSON.stringify(
                                                        e.target.value,
                                                    ),
                                                )
                                            }
                                        />
                                    </label>
                                    <EvidencePicker
                                        id="particular"
                                        events={events}
                                        selected={form.data.evidence}
                                        onChange={(ids) =>
                                            form.setData('evidence', ids)
                                        }
                                    />
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            disabled={
                                                form.processing ||
                                                !form.data.evidence.length ||
                                                form.data.evidence.length > 20
                                            }
                                        >
                                            Guardar con historial
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={form.processing}
                                            onClick={() => setAdding(false)}
                                        >
                                            Cancelar
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </section>
                        {conflicts.length > 0 && (
                            <section className="overflow-hidden rounded-xl border">
                                <h2 className="border-b px-5 py-4 font-semibold">
                                    Hipótesis en conflicto · {conflicts.length}
                                </h2>
                                <p className="border-b px-5 py-3 text-sm text-muted-foreground">
                                    Estas hipótesis no reemplazan las
                                    correcciones de comercio. Revisa la
                                    evidencia antes de cambiar una
                                    clasificación.
                                </p>
                                {conflicts.map(renderFact)}
                            </section>
                        )}
                        <details className="rounded-xl border">
                            <summary className="cursor-pointer px-5 py-4 text-sm font-medium">
                                <History className="mr-2 inline size-4" />
                                Historial de clasificaciones y propiedades ·{' '}
                                {history.length}
                            </summary>
                            <div className="border-t">
                                {history.length ? (
                                    history.map(renderFact)
                                ) : (
                                    <p className="p-5 text-sm text-muted-foreground">
                                        Aún no hay propiedades sustituidas.
                                    </p>
                                )}
                            </div>
                        </details>
                        {facts.length === 200 && (
                            <p className="text-xs text-muted-foreground">
                                Se muestran las últimas 200 propiedades
                                registradas, incluido su historial.
                            </p>
                        )}
                    </div>
                    <aside className="space-y-6">
                        <section className="space-y-3">
                            <h2 className="text-sm font-semibold">
                                Contexto de la relación
                            </h2>
                            <dl className="space-y-4 text-sm">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Última actividad registrada
                                    </dt>
                                    <dd className="mt-1">
                                        {indicators.last_activity
                                            ? date(indicators.last_activity)
                                            : 'Sin actividad registrada'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Eventos observados
                                    </dt>
                                    <dd className="mt-1 tabular-nums">
                                        {indicators.events}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Oportunidades
                                    </dt>
                                    <dd className="mt-1">
                                        <Link
                                            className={crmLink}
                                            href={customerProfileUrl(
                                                customer.id,
                                                directoryQuery,
                                                'opportunities',
                                            )}
                                        >
                                            {cases.length} registradas
                                        </Link>
                                    </dd>
                                </div>
                            </dl>
                        </section>
                        <section className="space-y-3 border-t pt-5">
                            <h2 className="flex items-center gap-2 text-sm font-semibold">
                                <BrainCircuit className="size-4" />
                                Interpretación del perfil
                            </h2>
                            <p className="text-sm leading-6 text-muted-foreground">
                                {aiReady
                                    ? 'La IA propone hipótesis basadas en eventos. Comercio confirma o corrige con evidencia.'
                                    : 'La interpretación automática espera configuración de IA. Puedes revisar y confirmar el perfil manualmente.'}
                            </p>
                            {!readOnly && (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    disabled={!aiReady || analyzing}
                                    onClick={() => {
                                        setAnalyzing(true);
                                        router.post(
                                            `/customers/${customer.id}/analyze`,
                                            {},
                                            {
                                                preserveScroll: true,
                                                onFinish: () =>
                                                    setAnalyzing(false),
                                            },
                                        );
                                    }}
                                >
                                    {analyzing
                                        ? 'Solicitando…'
                                        : 'Actualizar interpretación'}
                                </Button>
                            )}
                            {runs.length > 0 && (
                                <details className="text-sm">
                                    <summary className="cursor-pointer text-muted-foreground">
                                        Ver procesamiento
                                    </summary>
                                    <ul className="mt-3 space-y-3">
                                        {runs.map((run) => (
                                            <li
                                                key={run.id}
                                                className="space-y-1"
                                            >
                                                <p>
                                                    Hasta evento {run.cutoff}:{' '}
                                                    {(
                                                        {
                                                            complete:
                                                                'Completado',
                                                            pending:
                                                                'Pendiente',
                                                            running:
                                                                'En proceso',
                                                            failed: 'Fallido',
                                                            budget_exhausted:
                                                                'Presupuesto agotado',
                                                        } as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[run.status] ?? run.status}
                                                </p>
                                                {run.error && (
                                                    <p className="text-xs break-words text-muted-foreground">
                                                        {run.error}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                            )}
                        </section>
                        <p className="border-t pt-5 text-xs leading-5 text-muted-foreground">
                            El importe de una compra, los pagos y el potencial
                            son conceptos distintos. Una clasificación no
                            autoriza condiciones comerciales.
                        </p>
                    </aside>
                </div>
            )}
            {activeTab === 'activity' && (
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
                    <section className="min-w-0 overflow-hidden rounded-xl border">
                        <header className="border-b px-5 py-4">
                            <h2 className="font-semibold">
                                Actividad y evidencia
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {events.length === 200
                                    ? 'Últimos 200 eventos registrados.'
                                    : `${events.length} eventos registrados.`}{' '}
                                Fechas de Ciudad de México.
                            </p>
                        </header>
                        {selectedEvent && (
                            <EvidenceEvent event={selectedEvent} selected />
                        )}
                        {selectedIsOlder && (
                            <p className="border-b px-4 py-3 text-xs text-muted-foreground">
                                La evidencia seleccionada es anterior a los
                                eventos recientes.
                            </p>
                        )}
                        {events
                            .filter((event) => event.id !== selectedEvent?.id)
                            .map((event) => (
                                <EvidenceEvent key={event.id} event={event} />
                            ))}
                        {!events.length && !selectedEvent && (
                            <p className="p-6 text-sm text-muted-foreground">
                                Los mensajes, solicitudes y resultados
                                aparecerán aquí cuando se registren.
                            </p>
                        )}
                    </section>
                    <aside className="space-y-3">
                        <h2 className="text-sm font-semibold">
                            Navegación registrada
                        </h2>
                        {indicators.digital_activity.length ? (
                            indicators.digital_activity.map((activity) => (
                                <p
                                    key={activity.type}
                                    className="flex justify-between gap-3 text-sm"
                                >
                                    <span>{eventLabel(activity.type)}</span>
                                    <span className="tabular-nums">
                                        {activity.total}
                                    </span>
                                </p>
                            ))
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Sin navegación registrada.
                            </p>
                        )}
                        <p className="border-t pt-4 text-xs leading-5 text-muted-foreground">
                            Estas interacciones dependen del consentimiento del
                            usuario. No demuestran lectura, intención de compra
                            ni pago.
                        </p>
                    </aside>
                </div>
            )}
            {activeTab === 'opportunities' && (
                <section className="overflow-hidden rounded-xl border">
                    <header className="border-b px-5 py-4">
                        <h2 className="font-semibold">
                            Oportunidades del cliente
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            La aceptación, formalización, pago y cierre se
                            registran por separado.
                        </p>
                    </header>
                    <div className="divide-y">
                        {cases.map((item) => (
                            <article
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-3 p-5"
                            >
                                <div className="min-w-0">
                                    <h3 className="text-sm font-medium break-words">
                                        {item.title}
                                    </h3>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {statuses[item.status] ?? item.status}
                                    </p>
                                </div>
                                {item.public_id && !readOnly && (
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={
                                                '/my/quotes/' + item.public_id
                                            }
                                        >
                                            Abrir oportunidad
                                            <ArrowUpRight />
                                        </Link>
                                    </Button>
                                )}
                                {!item.public_id && (
                                    <span className="text-xs text-muted-foreground">
                                        Sin cotización publicada
                                    </span>
                                )}
                            </article>
                        ))}
                        {!cases.length && (
                            <p className="p-6 text-sm text-muted-foreground">
                                Este cliente aún no tiene oportunidades
                                registradas.
                            </p>
                        )}
                    </div>
                </section>
            )}
        </CrmPage>
    );
}
