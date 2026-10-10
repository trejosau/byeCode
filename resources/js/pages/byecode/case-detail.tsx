import { Link, router } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    RefreshCw,
    ExternalLink,
    FilePlus2,
    LockKeyhole,
    History,
} from 'lucide-react';
import { WorkspacePage, EmptyState } from '@/components/byecode/workspace-page';
import { EventForm } from '@/components/byecode/event-form';
import { RecommendationPanel } from '@/components/byecode/recommendation-panel';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import {
    actions,
    date,
    eventNames,
    money,
    statuses,
    stages,
} from '@/lib/byecode';
import type {
    CaseEvent,
    DataSet,
    Customer,
    Evaluation,
    Quote,
    Recommendation,
} from '@/types/byecode';

type Props = {
    case: {
        id: number;
        external_id: string;
        title: string;
        status: string;
        stage: string;
        amount_cents: number | null;
        revision: number;
    };
    set: DataSet;
    snapshot: {
        evaluation_at: string;
        state: { contact_allowed: boolean; contact_not_before: string | null };
        events: CaseEvent[];
        metrics: {
            views: number;
            messages: number;
            hours_since_last_activity: number | null;
            hours_since_last_contact: number | null;
        };
        integrity_blocked: boolean;
    };
    rules: { checks: Record<string, { blocked: boolean; reason: string }> };
    recommendations: Recommendation[];
    evaluations: Evaluation[];
    quotes: Quote[];
    customer: Customer | null;
    portalUrl: string | null;
    prediction: {
        available: boolean;
        reason?: string;
        probability?: number;
        label?: string;
        validation?: {
            run_id: number;
            evaluated_at: string;
            test_cases: number | null;
            brier: number | null;
            auc: number | null;
            ece: number | null;
        };
    };
};
export default function CaseDetail({
    case: c,
    set,
    snapshot,
    rules,
    recommendations,
    evaluations,
    quotes,
    customer,
    portalUrl,
    prediction,
}: Props) {
    const pending = evaluations.some(
        (e) => e.status === 'pending' || e.status === 'running',
    );
    useEffect(() => {
        const timer = setInterval(
            () =>
                document.visibilityState === 'visible' &&
                router.reload({
                    only: [
                        'case',
                        'snapshot',
                        'rules',
                        'recommendations',
                        'evaluations',
                        'prediction',
                    ],
                }),
            10000,
        );
        return () => clearInterval(timer);
    }, []);
    const current = recommendations.find((r) => r.is_current);
    const quote = quotes[0];
    return (
        <WorkspacePage
            title={c.title}
            description={
                c.external_id +
                ' · ' +
                (customer?.company ?? set.name) +
                ' · ' +
                stages[c.stage]
            }
            actions={
                <>
                    <Button
                        variant="outline"
                        disabled={pending}
                        onClick={() =>
                            router.post(
                                '/cases/' + c.id + '/evaluations',
                                { retry: true },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <RefreshCw className={pending ? 'animate-spin' : ''} />
                        {pending ? 'Evaluando…' : 'Evaluar contexto'}
                    </Button>
                    {set.mode === 'live' && c.status === 'open' && (
                        <Button asChild>
                            <Link href={'/cases/' + c.id + '/quote'}>
                                <FilePlus2 />
                                Nueva versión
                            </Link>
                        </Button>
                    )}
                </>
            }
        >
            {set.mode === 'kit' && (
                <Alert>
                    <AlertTitle>Escenario sintético · reloj fijo</AlertTitle>
                    <AlertDescription>
                        Evaluación: {snapshot.evaluation_at}. Importar la
                        actualización agrega conocimiento al mismo instante de
                        negocio.
                    </AlertDescription>
                </Alert>
            )}
            {snapshot.integrity_blocked && (
                <Alert variant="destructive">
                    <LockKeyhole className="size-4" />
                    <AlertTitle>Historial en cuarentena</AlertTitle>
                    <AlertDescription>
                        Hay datos incompletos o conflictivos. Revisa los errores
                        de importación y carga un conjunto corregido antes de
                        evaluar.
                    </AlertDescription>
                </Alert>
            )}
            <div className="grid items-start gap-7 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="min-w-0 xl:col-start-1 xl:row-start-1">
                    <div className="grid grid-cols-2 gap-5 rounded-xl border p-5 sm:grid-cols-4">
                        {[
                            { label: 'Importe', value: money(c.amount_cents) },
                            { label: 'Estado', value: statuses[c.status] },
                            {
                                label: 'Consultas',
                                value: snapshot.metrics.views,
                            },
                            {
                                label: 'Mensajes',
                                value: snapshot.metrics.messages,
                            },
                        ].map((x) => (
                            <div key={x.label}>
                                <p className="text-xs text-muted-foreground">
                                    {x.label}
                                </p>
                                <p className="mt-2 text-lg font-semibold">
                                    {x.value}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
                <aside className="min-w-0 space-y-5 xl:sticky xl:top-6 xl:col-start-2 xl:row-span-2 xl:row-start-1">
                    {current ? (
                        <RecommendationPanel recommendation={current} />
                    ) : (
                        <>
                            <EmptyState
                                title={
                                    pending
                                        ? 'Evaluación en curso'
                                        : 'Sin decisión vigente'
                                }
                                description={
                                    evaluations[0]?.error_message ??
                                    'Los eventos del negocio solicitan una evaluación automática. Revisa el estado del procesamiento o solicita una reevaluación.'
                                }
                            />
                        </>
                    )}
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Probabilidad de cierre · 14 días
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {prediction.available &&
                            prediction.probability !== undefined ? (
                                <>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {(prediction.probability * 100).toFixed(
                                            0,
                                        )}{' '}
                                        %
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {prediction.label}
                                    </p>
                                    {prediction.validation && (
                                        <p className="text-xs leading-relaxed text-muted-foreground">
                                            Validación #
                                            {prediction.validation.run_id} ·{' '}
                                            {prediction.validation.test_cases ??
                                                '—'}{' '}
                                            casos de prueba ·{' '}
                                            {date(
                                                prediction.validation
                                                    .evaluated_at,
                                            )}
                                            <br />
                                            Brier:{' '}
                                            {prediction.validation.brier?.toFixed(
                                                3,
                                            ) ?? '—'}{' '}
                                            · AUC:{' '}
                                            {prediction.validation.auc?.toFixed(
                                                3,
                                            ) ?? '—'}{' '}
                                            · Error de calibración:{' '}
                                            {prediction.validation.ece?.toFixed(
                                                3,
                                            ) ?? '—'}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">
                                        Porcentaje no disponible
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {prediction.reason}
                                    </p>
                                </div>
                            )}
                            <Link
                                href="/prediction"
                                className="text-sm underline underline-offset-4"
                            >
                                Ver evaluación del modelo
                            </Link>
                        </CardContent>
                    </Card>
                    {evaluations.length > 0 && (
                        <Card className="shadow-none">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Estado de evaluación
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {evaluations.slice(0, 3).map((e) => (
                                    <div key={e.id} className="text-xs">
                                        <p className="font-medium">
                                            #{e.id} ·{' '}
                                            {statuses[e.status] ?? e.status}
                                        </p>
                                        <p className="mt-1 text-muted-foreground">
                                            {e.model ?? statuses[e.provider]} ·{' '}
                                            {date(e.created_at)}
                                            {e.duration_ms !== null
                                                ? ' · ' + e.duration_ms + ' ms'
                                                : ''}
                                        </p>
                                        {e.error_message && (
                                            <p className="mt-2 leading-relaxed text-muted-foreground">
                                                {e.error_message}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </aside>
                <div className="min-w-0 space-y-7 xl:col-start-1 xl:row-start-2">
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <LockKeyhole className="size-4" />
                                Restricciones y reglas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {Object.entries(rules.checks).map(([id, r]) => (
                                    <div key={id}>
                                        <Badge
                                            variant={
                                                r.blocked
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {id} ·{' '}
                                            {r.blocked
                                                ? 'Atención'
                                                : 'Comprobada'}
                                        </Badge>
                                        <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                                            {r.reason}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-5 border-t pt-4 text-xs text-muted-foreground">
                                Último contacto:{' '}
                                {snapshot.metrics.hours_since_last_contact ===
                                null
                                    ? 'sin registro'
                                    : snapshot.metrics
                                          .hours_since_last_contact +
                                      ' horas transcurridas'}{' '}
                                · Sin actividad:{' '}
                                {snapshot.metrics.hours_since_last_activity ??
                                    '—'}{' '}
                                h
                            </div>
                        </CardContent>
                    </Card>
                    {quote && (
                        <section className="space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="text-lg font-semibold">
                                    Cotización · versión {quote.version}
                                </h2>
                                <Badge variant="outline">
                                    Vigente hasta {date(quote.valid_until)}
                                </Badge>
                            </div>
                            <div className="overflow-hidden rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Producto</TableHead>
                                            <TableHead className="text-right">
                                                Unidades
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Precio
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Subtotal
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {quote.lines.map((l) => (
                                            <TableRow key={l.line_id}>
                                                <TableCell>
                                                    <p className="font-medium">
                                                        {l.name}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {l.sku} ·{' '}
                                                        {[
                                                            l.fit,
                                                            l.gsm
                                                                ? l.gsm + ' GSM'
                                                                : null,
                                                            l.color,
                                                            l.size,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                    {l.below_moq && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="mt-2"
                                                        >
                                                            Revisar mínimo de
                                                            pedido
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {l.quantity}
                                                </TableCell>
                                                <TableCell className="text-right whitespace-nowrap">
                                                    {money(l.unit_price_cents)}
                                                </TableCell>
                                                <TableCell className="text-right whitespace-nowrap">
                                                    {money(l.total_cents)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="flex justify-between border-t p-4 font-semibold">
                                    <span>Total MXN</span>
                                    <span>{money(quote.total_cents)}</span>
                                </div>
                            </div>
                            <p className="text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground">
                                {quote.terms ??
                                    'Sin condiciones adicionales registradas.'}
                            </p>
                            {set.mode === 'live' && (
                                <div className="space-y-3 rounded-xl border p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-medium">
                                                Portal privado del comprador
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Enlace permanente. El comprador
                                                debe entrar con su propia
                                                cuenta.
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    '/cases/' +
                                                        c.id +
                                                        '/portal-link',
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <ExternalLink />
                                            Preparar enlace de cuenta
                                        </Button>
                                    </div>
                                    {portalUrl && (
                                        <div className="flex flex-wrap gap-3">
                                            <a
                                                href={portalUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-xs break-all underline underline-offset-4"
                                            >
                                                {portalUrl}
                                            </a>
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                onClick={() =>
                                                    navigator.clipboard.writeText(
                                                        portalUrl,
                                                    )
                                                }
                                            >
                                                Copiar enlace
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                            {quotes.length > 1 && (
                                <Collapsible>
                                    <CollapsibleTrigger asChild>
                                        <Button variant="ghost">
                                            <History />
                                            Consultar versiones anteriores
                                        </Button>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="space-y-3 pt-3">
                                        {quotes.slice(1).map((q) => (
                                            <div
                                                key={q.id}
                                                className="rounded-lg border p-4"
                                            >
                                                <p className="text-sm font-medium">
                                                    Versión {q.version} ·{' '}
                                                    {money(q.total_cents)}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {q.lines
                                                        .map(
                                                            (l) =>
                                                                l.quantity +
                                                                ' × ' +
                                                                l.sku,
                                                        )
                                                        .join(' · ')}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Autorización:{' '}
                                                    {q.authorization_reference}
                                                </p>
                                            </div>
                                        ))}
                                    </CollapsibleContent>
                                </Collapsible>
                            )}
                        </section>
                    )}
                    {set.mode === 'live' && (
                        <EventForm
                            caseId={c.id}
                            revision={c.revision}
                            contactAllowed={snapshot.state.contact_allowed}
                            contactNotBefore={snapshot.state.contact_not_before}
                        />
                    )}
                    <section className="space-y-4">
                        <h2 className="text-lg font-semibold">
                            Historial de evidencia
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Orden de incorporación. Cada evento conserva su
                            fecha real en UTC.
                        </p>
                        <div className="divide-y rounded-xl border">
                            {snapshot.events.map((e) => (
                                <article
                                    key={e.event_id}
                                    id={'event-' + e.event_id}
                                    className="scroll-mt-6 p-5"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-semibold">
                                            {eventNames[e.event_type] ??
                                                e.event_type}
                                        </p>
                                        <Badge
                                            variant="outline"
                                            className="text-[11px]"
                                        >
                                            {e.event_id}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {e.occurred_at} · {date(e.occurred_at)}{' '}
                                        CDMX
                                    </p>
                                    {e.event_type === 'message.received' ? (
                                        <p className="mt-3 text-sm leading-relaxed whitespace-pre-wrap">
                                            {String(e.data.message)}
                                        </p>
                                    ) : (
                                        <dl className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs">
                                            {Object.entries(e.data).map(
                                                ([k, v]) => (
                                                    <div key={k}>
                                                        <dt className="text-muted-foreground">
                                                            {k}
                                                        </dt>
                                                        <dd className="mt-1 break-words">
                                                            {typeof v ===
                                                            'object'
                                                                ? JSON.stringify(
                                                                      v,
                                                                  )
                                                                : typeof v ===
                                                                    'string'
                                                                  ? v
                                                                  : JSON.stringify(
                                                                        v,
                                                                    )}
                                                        </dd>
                                                    </div>
                                                ),
                                            )}
                                        </dl>
                                    )}
                                </article>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
            {recommendations.filter((r) => !r.is_current).length > 0 && (
                <section className="space-y-4 border-t pt-6">
                    <h2 className="text-lg font-semibold">
                        Decisiones anteriores
                    </h2>
                    <div className="grid gap-4 lg:grid-cols-2">
                        {recommendations
                            .filter((r) => !r.is_current)
                            .map((r) => (
                                <Card key={r.id} className="shadow-none">
                                    <CardContent>
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">
                                                {actions[r.result.action]}
                                            </p>
                                            <Badge variant="outline">
                                                Histórica · no vigente
                                            </Badge>
                                        </div>
                                        <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                                            {r.result.reason}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            Respaldo:{' '}
                                            {r.result.evidence_event_ids.join(
                                                ', ',
                                            )}
                                        </p>
                                        <p className="mt-2 text-xs text-muted-foreground">
                                            {date(r.created_at)}
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                    </div>
                </section>
            )}
        </WorkspacePage>
    );
}
CaseDetail.layout = {
    breadcrumbs: [
        { title: 'Cotizaciones', href: '/cases' },
        { title: 'Detalle', href: '/cases' },
    ],
};
