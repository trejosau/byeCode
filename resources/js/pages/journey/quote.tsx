import { Link, router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    Journey,
    Errors,
    inputClass,
    type CatalogImage,
} from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
import { track, useActivity } from '@/hooks/use-activity';
import { money } from '@/lib/byecode';
import {
    ActionRanking,
    type ActionAnalysis,
} from '@/components/byecode/action-ranking';
type Line = {
    line_id: string;
    sku: string;
    name: string;
    fit: string;
    color: string;
    size: string;
    gsm?: number;
    images?: CatalogImage[];
    quantity: number;
    unit_price_cents: number;
    total_cents: number;
};
type Quote = {
    id: number;
    version: number;
    lines: Line[];
    total_cents: number;
    subtotal_cents: number;
    tax_cents: number;
    shipping_cents: number;
    discount_cents: number;
    terms: string;
    valid_until: string;
    withdrawn_at: string | null;
};
export default function QuotePage({
    case: current,
    quotes,
    messages,
    decisions,
    requestLines,
    requestMessage,
    desiredDate,
    commerce,
    readOnly,
    analysis,
    previousAnalysis,
    evaluation,
    analysisScheduled,
}: {
    case: {
        id: number;
        title: string;
        status: string;
        public_id: string;
        external_id: string;
    };
    quotes: Quote[];
    messages: {
        id: number;
        name: string;
        body: string;
        author_role: string;
        created_at: string;
        quote_version: number | null;
        attachment_name: string | null;
    }[];
    decisions: {
        id: number;
        kind: string;
        quote_version: number;
        conditions: string | null;
        lines: {
            line_id: string;
            quantity: number;
            proposed_total_cents?: number;
        }[];
    }[];
    requestLines: {
        product_id: number;
        sku: string;
        name: string;
        color: string;
        size: string;
        quantity: number;
    }[];
    requestMessage: string | null;
    desiredDate: string | null;
    commerce: boolean;
    readOnly: boolean;
    analysisScheduled: boolean;
    analysis: ActionAnalysis | null;
    previousAnalysis: ActionAnalysis | null;
    evaluation: { status: string; error_message: string | null } | null;
}) {
    const quote = quotes[0];
    useActivity(current.id, quote?.version, !commerce && !readOnly);
    const message = useForm({
        event_id: crypto.randomUUID(),
        body: '',
        quote_version: quote?.version ?? null,
        attachment: null as File | null,
    });
    const decision = useForm({
        event_id: crypto.randomUUID(),
        quote_version: quotes[0]?.version ?? 0,
        kind: 'accept',
        conditions: '',
        lines:
            quotes[0]?.lines.map((l) => ({
                line_id: l.line_id,
                quantity: l.quantity,
                proposed_line_total: (l.total_cents / 100).toFixed(2),
                price_edited: false,
            })) ?? [],
    });
    const result = useForm({
        event_id: crypto.randomUUID(),
        kind: 'formalized',
        amount: '',
        reference: '',
        occurred_at: '',
        close: false,
    });
    const withdrawal = useForm({ version: quote?.version ?? 0, reason: '' });
    if (quote && decision.data.quote_version !== quote.version) {
        decision.setData({
            ...decision.data,
            event_id: crypto.randomUUID(),
            quote_version: quote.version,
            conditions: '',
            lines: quote.lines.map((line) => ({
                line_id: line.line_id,
                quantity: line.quantity,
                proposed_line_total: (line.total_cents / 100).toFixed(2),
                price_edited: false,
            })),
        });
    }
    useEffect(() => {
        let refreshing = false;
        const refresh = () => {
            if (document.hidden || refreshing) return;
            refreshing = true;
            router.reload({
                only: [
                    'messages',
                    'decisions',
                    'quotes',
                    'case',
                    'analysis',
                    'previousAnalysis',
                    'evaluation',
                    'analysisScheduled',
                ],
                onFinish: () => {
                    refreshing = false;
                },
            });
        };
        const timer = setInterval(refresh, 10000);
        document.addEventListener('visibilitychange', refresh);
        window.addEventListener('focus', refresh);
        window.addEventListener('online', refresh);
        return () => {
            clearInterval(timer);
            document.removeEventListener('visibilitychange', refresh);
            window.removeEventListener('focus', refresh);
            window.removeEventListener('online', refresh);
        };
    }, []);
    useEffect(() => {
        if (quote && !commerce && !readOnly)
            track(
                'proposal.version_viewed',
                { version: quote.version },
                current.id,
            );
    }, [quote?.version, current.id, commerce, readOnly]);
    const valid =
        quote &&
        quote.version === quotes[0]?.version &&
        !quote.withdrawn_at &&
        new Date(quote.valid_until) > new Date() &&
        current.status === 'open';
    return (
        <Journey
            title={current.title}
            description={
                current.external_id +
                ' · Toda la negociación en un mismo lugar.'
            }
        >
            <div className="flex flex-wrap gap-3">
                {commerce && (
                    <>
                        <Button asChild>
                            <Link href={`/cases/${current.id}/quote`}>
                                {quote
                                    ? 'Actualizar cotización'
                                    : 'Preparar cotización'}
                            </Link>
                        </Button>
                        {quote && (
                            <Button asChild variant="outline">
                                <a href="#analysis">Ver análisis</a>
                            </Button>
                        )}
                    </>
                )}
            </div>
            <div
                className={
                    commerce || readOnly
                        ? 'grid items-start gap-7 lg:grid-cols-[minmax(280px,2fr)_minmax(0,3fr)]'
                        : ''
                }
            >
                {(commerce || readOnly) && (
                    <aside className="min-w-0 lg:sticky lg:top-6">
                        <ActionRanking
                            analysis={analysis ?? previousAnalysis}
                            outdated={!analysis && !!previousAnalysis}
                            evaluation={evaluation}
                            scheduled={analysisScheduled}
                        />
                    </aside>
                )}
                <div
                    className={
                        commerce || readOnly
                            ? 'min-w-0 space-y-7'
                            : 'grid items-start gap-7 lg:grid-cols-[3fr_2fr]'
                    }
                >
                    <section
                        className="min-w-0 space-y-6"
                        data-section="proposal"
                    >
                        {!quote ? (
                            <div className="rounded-xl border p-6">
                                <h2 className="text-xl font-semibold">
                                    {commerce
                                        ? 'Solicitud del cliente'
                                        : 'Tu solicitud está en revisión'}
                                </h2>
                                <p className="my-3 text-muted-foreground">
                                    {commerce
                                        ? 'Revisa los productos y prepara la cotización con el total de cada uno.'
                                        : 'El equipo de comercio está preparando tu cotización. Puedes enviar un mensaje mientras tanto.'}
                                </p>
                                {requestLines.map((l) => (
                                    <p key={l.product_id}>
                                        {l.name} · {l.color} · {l.size} ·{' '}
                                        {l.quantity} unidades
                                    </p>
                                ))}
                            </div>
                        ) : (
                            <>
                                <div className="flex flex-wrap items-center gap-4">
                                    <Button
                                        variant="ghost"
                                        onClick={() => {
                                            track(
                                                'proposal.print_clicked',
                                                { version: quote.version },
                                                current.id,
                                            );
                                            window.print();
                                        }}
                                    >
                                        Imprimir
                                    </Button>
                                </div>
                                <div
                                    className="overflow-auto rounded-xl border"
                                    data-section="products"
                                >
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted">
                                            <tr>
                                                {[
                                                    'Producto',
                                                    'Unidades',
                                                    'Precio unitario',
                                                    'Importe',
                                                ].map((h) => (
                                                    <th key={h} className="p-4">
                                                        {h}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {quote.lines.map((l) => (
                                                <tr
                                                    key={l.line_id}
                                                    className="border-t"
                                                >
                                                    <td className="p-4">
                                                        {l.images?.[0] && (
                                                            <img
                                                                src={
                                                                    l.images[0]
                                                                        .path
                                                                }
                                                                alt={
                                                                    l.images[0]
                                                                        .alt
                                                                }
                                                                className="mb-2 h-20 w-16 rounded-md bg-[#e7e7e4] object-contain"
                                                            />
                                                        )}
                                                        {l.name}
                                                        <p className="text-muted-foreground">
                                                            {l.gsm
                                                                ? `${l.gsm} g/m² · `
                                                                : ''}
                                                            {l.fit} · {l.color}{' '}
                                                            · {l.size}
                                                        </p>
                                                    </td>
                                                    <td className="p-4">
                                                        {l.quantity}
                                                    </td>
                                                    <td className="p-4">
                                                        {money(
                                                            l.total_cents /
                                                                l.quantity,
                                                        )}
                                                    </td>
                                                    <td className="p-4">
                                                        {money(l.total_cents)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <section
                                    data-section="prices"
                                    className="space-y-2 rounded-xl border p-6"
                                >
                                    <p>
                                        Subtotal: {money(quote.subtotal_cents)}
                                    </p>
                                    <p>
                                        Impuestos: {money(quote.tax_cents)} ·
                                        Envío: {money(quote.shipping_cents)} ·
                                        Descuento: {money(quote.discount_cents)}
                                    </p>
                                    <p className="text-2xl font-semibold">
                                        Total {money(quote.total_cents)}
                                    </p>
                                </section>
                                <section
                                    data-section="terms"
                                    className="rounded-xl border p-6"
                                >
                                    <h2 className="font-semibold">
                                        Condiciones y vigencia
                                    </h2>
                                    <p className="my-3 whitespace-pre-wrap">
                                        {quote.terms ||
                                            'Consulta con comercio para confirmar condiciones.'}
                                    </p>
                                    <p>
                                        Válida hasta{' '}
                                        {new Date(
                                            quote.valid_until,
                                        ).toLocaleString('es-MX')}
                                    </p>
                                    {!valid && (
                                        <p className="mt-3 font-medium">
                                            Esta cotización no admite nuevas
                                            decisiones.
                                        </p>
                                    )}
                                </section>
                                {!commerce && !readOnly && valid && (
                                    <form
                                        data-section="decision"
                                        className="space-y-4 rounded-xl border p-6"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            decision.transform((data) => ({
                                                ...data,
                                                quote_version: quote.version,
                                                lines: data.lines.map(
                                                    (line) => ({
                                                        line_id: line.line_id,
                                                        quantity: line.quantity,
                                                        ...(data.kind !==
                                                            'reject' &&
                                                        line.quantity > 0 &&
                                                        line.price_edited
                                                            ? {
                                                                  proposed_line_total:
                                                                      line.proposed_line_total,
                                                              }
                                                            : {}),
                                                    }),
                                                ),
                                            }));
                                            decision.post(
                                                `/journey/${current.id}/decision`,
                                                {
                                                    onSuccess: () =>
                                                        decision.setData(
                                                            'event_id',
                                                            crypto.randomUUID(),
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        <h2 className="text-xl font-semibold">
                                            Tu decisión
                                        </h2>
                                        <Errors errors={decision.errors} />
                                        <select
                                            aria-label="Tipo de decisión"
                                            className={inputClass}
                                            value={decision.data.kind}
                                            onChange={(e) =>
                                                decision.setData(
                                                    'kind',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="accept">
                                                Aceptar cantidades
                                            </option>
                                            <option value="reject">
                                                Rechazar propuesta
                                            </option>
                                            <option value="change">
                                                Solicitar cambios
                                            </option>
                                        </select>
                                        {decision.data.kind !== 'reject' &&
                                            quote.lines.map((line) => {
                                                const selected =
                                                    decision.data.lines.find(
                                                        (item) =>
                                                            item.line_id ===
                                                            line.line_id,
                                                    );
                                                return (
                                                    <div
                                                        key={line.line_id}
                                                        className="space-y-3 rounded-lg border p-3"
                                                    >
                                                        <p className="font-medium">
                                                            {line.name} ·{' '}
                                                            {line.color} ·{' '}
                                                            {line.size}
                                                        </p>
                                                        <div className="grid gap-3 sm:grid-cols-2">
                                                            <label className="block text-sm">
                                                                Cantidad
                                                                solicitada
                                                                <input
                                                                    aria-label={`Cantidad ${line.name} ${line.color} ${line.size}`}
                                                                    type="number"
                                                                    min="0"
                                                                    max={
                                                                        line.quantity
                                                                    }
                                                                    required
                                                                    className={
                                                                        inputClass
                                                                    }
                                                                    value={
                                                                        selected?.quantity ??
                                                                        0
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) => {
                                                                        const quantity =
                                                                            Number(
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                            );
                                                                        decision.setData(
                                                                            'lines',
                                                                            decision.data.lines.map(
                                                                                (
                                                                                    item,
                                                                                ) =>
                                                                                    item.line_id ===
                                                                                    line.line_id
                                                                                        ? {
                                                                                              ...item,
                                                                                              quantity,
                                                                                              proposed_line_total:
                                                                                                  item.price_edited
                                                                                                      ? item.proposed_line_total
                                                                                                      : (
                                                                                                            Math.round(
                                                                                                                (line.total_cents *
                                                                                                                    quantity) /
                                                                                                                    line.quantity,
                                                                                                            ) /
                                                                                                            100
                                                                                                        ).toFixed(
                                                                                                            2,
                                                                                                        ),
                                                                                          }
                                                                                        : item,
                                                                            ),
                                                                        );
                                                                    }}
                                                                />
                                                            </label>
                                                            <label className="block text-sm">
                                                                Total propuesto
                                                                por estas
                                                                unidades (MXN)
                                                                <input
                                                                    aria-label={`Total propuesto ${line.name} ${line.color} ${line.size}`}
                                                                    type="number"
                                                                    min="0.01"
                                                                    step="0.01"
                                                                    max="999999999"
                                                                    required={
                                                                        (selected?.quantity ??
                                                                            0) >
                                                                        0
                                                                    }
                                                                    disabled={
                                                                        (selected?.quantity ??
                                                                            0) ===
                                                                        0
                                                                    }
                                                                    className={
                                                                        inputClass
                                                                    }
                                                                    value={
                                                                        selected?.proposed_line_total ??
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        decision.setData(
                                                                            'lines',
                                                                            decision.data.lines.map(
                                                                                (
                                                                                    item,
                                                                                ) =>
                                                                                    item.line_id ===
                                                                                    line.line_id
                                                                                        ? {
                                                                                              ...item,
                                                                                              proposed_line_total:
                                                                                                  event
                                                                                                      .target
                                                                                                      .value,
                                                                                              price_edited: true,
                                                                                          }
                                                                                        : item,
                                                                            ),
                                                                        )
                                                                    }
                                                                />
                                                            </label>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            Cotizado para{' '}
                                                            {line.quantity}{' '}
                                                            unidades:{' '}
                                                            {money(
                                                                line.total_cents,
                                                            )}
                                                            . Cambiar el precio
                                                            envía una
                                                            contrapropuesta para
                                                            revisión de
                                                            comercio.
                                                        </p>
                                                    </div>
                                                );
                                            })}
                                        <label className="block">
                                            Comentarios o condiciones
                                            <textarea
                                                className={inputClass}
                                                value={decision.data.conditions}
                                                onChange={(e) =>
                                                    decision.setData(
                                                        'conditions',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </label>
                                        <p className="text-sm text-muted-foreground">
                                            Si cambias precios, cantidades o
                                            condiciones, comercio revisará tu
                                            propuesta antes de formalizar. Los
                                            totales por producto no incluyen
                                            impuestos ni envío. La cotización
                                            publicada conserva sus precios hasta
                                            que comercio publique otra versión.
                                        </p>
                                        <Button disabled={decision.processing}>
                                            Confirmar decisión
                                        </Button>
                                    </form>
                                )}
                            </>
                        )}
                        {commerce && valid && (
                            <form
                                className="space-y-3 rounded-xl border p-5"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    withdrawal.transform((data) => ({
                                        ...data,
                                        version: quote.version,
                                    }));
                                    withdrawal.post(
                                        `/journey/${current.id}/withdraw`,
                                        {
                                            onSuccess: () =>
                                                withdrawal.reset('reason'),
                                        },
                                    );
                                }}
                            >
                                <h2 className="font-semibold">
                                    Retirar propuesta vigente
                                </h2>
                                <Errors errors={withdrawal.errors} />
                                <label className="block">
                                    Motivo
                                    <input
                                        required
                                        minLength={5}
                                        className={inputClass}
                                        value={withdrawal.data.reason}
                                        onChange={(event) =>
                                            withdrawal.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <Button
                                    variant="outline"
                                    disabled={withdrawal.processing}
                                >
                                    Retirar cotización
                                </Button>
                            </form>
                        )}
                        {commerce && quote && (
                            <form
                                className="space-y-4 rounded-xl border p-6"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    result.post(
                                        `/journey/${current.id}/result`,
                                        {
                                            onSuccess: () =>
                                                result.setData(
                                                    'event_id',
                                                    crypto.randomUUID(),
                                                ),
                                        },
                                    );
                                }}
                            >
                                <h2 className="text-xl font-semibold">
                                    Registrar resultado confirmado
                                </h2>
                                <Errors errors={result.errors} />
                                <select
                                    aria-label="Resultado confirmado"
                                    className={inputClass}
                                    value={result.data.kind}
                                    onChange={(e) =>
                                        result.setData('kind', e.target.value)
                                    }
                                >
                                    <option value="formalized">
                                        Formalización
                                    </option>
                                    <option value="payment">
                                        Pago confirmado
                                    </option>
                                    <option value="rejected">
                                        Rechazo definitivo
                                    </option>
                                    <option value="cancelled">
                                        Cancelación
                                    </option>
                                </select>
                                <label className="block">
                                    Importe MXN
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        className={inputClass}
                                        value={result.data.amount}
                                        onChange={(e) =>
                                            result.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label className="block">
                                    Fecha y hora (Ciudad de México)
                                    <input
                                        required
                                        type="datetime-local"
                                        className={inputClass}
                                        value={result.data.occurred_at}
                                        onChange={(e) =>
                                            result.setData(
                                                'occurred_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label className="block">
                                    Referencia verificable
                                    <textarea
                                        required
                                        className={inputClass}
                                        value={result.data.reference}
                                        onChange={(e) =>
                                            result.setData(
                                                'reference',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label className="flex gap-2">
                                    <input
                                        type="checkbox"
                                        checked={result.data.close}
                                        onChange={(e) =>
                                            result.setData(
                                                'close',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Cerrar oportunidad: todos los pendientes
                                    están resueltos.
                                </label>
                                <Button disabled={result.processing}>
                                    Guardar resultado
                                </Button>
                            </form>
                        )}
                    </section>
                    <aside
                        className="space-y-5 rounded-xl border p-5"
                        data-section="conversation"
                    >
                        <h2 className="text-xl font-semibold">Conversación</h2>
                        {(requestMessage || desiredDate) && (
                            <div className="rounded-lg bg-muted p-4">
                                <h3 className="font-medium">
                                    Datos de la solicitud inicial
                                </h3>
                                {requestMessage && (
                                    <p className="mt-2 whitespace-pre-wrap">
                                        {requestMessage}
                                    </p>
                                )}
                                {desiredDate && (
                                    <p className="mt-2 text-sm">
                                        Fecha solicitada: {desiredDate}
                                    </p>
                                )}
                            </div>
                        )}
                        <div
                            className="max-h-[65vh] space-y-4 overflow-auto"
                            aria-live="polite"
                        >
                            {messages.length ? (
                                messages.map((m) => (
                                    <article
                                        className={
                                            'rounded-lg p-4 ' +
                                            (m.author_role === 'customer'
                                                ? 'bg-muted'
                                                : 'border')
                                        }
                                        key={m.id}
                                    >
                                        <p className="text-sm font-medium">
                                            {m.name} ·{' '}
                                            {m.author_role === 'customer'
                                                ? 'Cliente'
                                                : 'Comercio'}
                                        </p>
                                        <p className="my-2 break-words whitespace-pre-wrap">
                                            {m.body}
                                        </p>
                                        {m.attachment_name && (
                                            <a
                                                className="underline"
                                                href={'/attachments/' + m.id}
                                            >
                                                {m.attachment_name}
                                            </a>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(
                                                m.created_at,
                                            ).toLocaleString('es-MX')}
                                        </p>
                                    </article>
                                ))
                            ) : (
                                <p className="text-muted-foreground">
                                    Este espacio guarda las dudas y acuerdos de
                                    la solicitud.
                                </p>
                            )}
                        </div>
                        {!readOnly && current.status === 'open' && (
                            <form
                                className="space-y-3 border-t pt-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    message.transform((data) => ({
                                        ...data,
                                        quote_version: quote?.version ?? null,
                                    }));
                                    message.post(
                                        `/journey/${current.id}/messages`,
                                        {
                                            onSuccess: () => {
                                                message.reset(
                                                    'body',
                                                    'attachment',
                                                );
                                                message.setData(
                                                    'event_id',
                                                    crypto.randomUUID(),
                                                );
                                            },
                                        },
                                    );
                                }}
                            >
                                <Errors errors={message.errors} />
                                <label className="block">
                                    Mensaje
                                    <textarea
                                        required
                                        rows={4}
                                        className={inputClass}
                                        value={message.data.body}
                                        onChange={(e) =>
                                            message.setData(
                                                'body',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                                <label className="block text-sm">
                                    Adjunto (imagen o PDF, hasta 5 MB)
                                    <input
                                        className="block max-w-full"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp,application/pdf"
                                        onChange={(e) =>
                                            message.setData(
                                                'attachment',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                </label>
                                <Button disabled={message.processing}>
                                    Enviar mensaje
                                </Button>
                            </form>
                        )}
                        <div className="space-y-2 border-t pt-4">
                            <h3 className="font-medium">
                                Decisiones registradas
                            </h3>
                            {decisions.map((d) => (
                                <div
                                    key={d.id}
                                    className="space-y-1 rounded-lg border p-3 text-sm"
                                >
                                    {d.kind === 'accept'
                                        ? 'Aceptación para validar'
                                        : d.kind === 'reject'
                                          ? 'Rechazo'
                                          : 'Solicitud de cambios'}
                                    {d.conditions ? ': ' + d.conditions : ''}
                                    {d.kind !== 'reject' && (
                                        <p className="text-muted-foreground">
                                            {d.lines
                                                .map(
                                                    (line) =>
                                                        `${quotes.find((version) => version.version === d.quote_version)?.lines.find((item) => item.line_id === line.line_id)?.name ?? line.line_id}: ${line.quantity} unidades${line.proposed_total_cents != null ? ` · total propuesto ${money(line.proposed_total_cents)}` : ''}`,
                                                )
                                                .join(' · ')}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </aside>
                </div>
            </div>
        </Journey>
    );
}
