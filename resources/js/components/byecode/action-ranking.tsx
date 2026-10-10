import { actions, date, money, statuses, timings } from '@/lib/byecode';
import type { Recommendation } from '@/types/byecode';

export type ActionAnalysis = Pick<
    Recommendation,
    'result' | 'publication_status' | 'alternatives' | 'source'
>;

export function ActionRanking({
    analysis,
    evaluation,
    scheduled,
    outdated = false,
}: {
    analysis: ActionAnalysis | null;
    evaluation: { status: string; error_message: string | null } | null;
    scheduled: boolean;
    outdated?: boolean;
}) {
    const pending =
        (!analysis || outdated) &&
        (scheduled ||
            evaluation?.status === 'pending' ||
            evaluation?.status === 'running');
    const ranked = (analysis?.alternatives ?? [])
        .filter(
            (item) =>
                item.allowed &&
                item.rank != null &&
                item.ranking_source === 'model_validated',
        )
        .sort((a, b) => a.rank! - b.rank!);
    const excluded = (analysis?.alternatives ?? []).filter(
        (item) => !item.allowed,
    );

    return (
        <section
            id="analysis"
            className="max-h-[70dvh] scroll-mt-6 space-y-5 overflow-y-auto overscroll-y-contain rounded-xl border p-5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:max-h-[calc(100dvh-3rem)]"
            aria-labelledby="ranking-title"
            tabIndex={0}
        >
            <div className="space-y-2">
                <h2 id="ranking-title" className="text-xl font-semibold">
                    Ranking de acciones e inacciones
                </h2>
                <p className="text-sm text-muted-foreground">
                    Se actualiza automáticamente con los mensajes, la lectura de
                    la cotización y las decisiones del cliente.
                </p>
            </div>
            {outdated && (
                <p className="rounded-lg bg-muted p-3 text-sm">
                    Último análisis disponible. Se conserva como referencia;
                    requiere actualización antes de actuar.
                </p>
            )}
            {analysis && !outdated && (
                <p className="text-sm">
                    {statuses[analysis.publication_status] ??
                        analysis.publication_status}
                </p>
            )}
            {ranked.length > 0 ? (
                <>
                    <ol className="space-y-3">
                        {ranked.map((item) => (
                            <li
                                key={item.action}
                                className="space-y-2 rounded-lg border p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <h3 className="font-medium">
                                        {item.rank}.{' '}
                                        {item.plan?.title ??
                                            actions[item.action]}
                                    </h3>
                                    {item.selected && (
                                        <span className="rounded bg-muted px-2 py-1 text-xs">
                                            Prioridad principal
                                        </span>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {item.plan?.reason ?? item.reason}
                                </p>
                                <DecisionEvidence item={item} />
                                {item.plan && (
                                    <div className="space-y-4 pt-2 text-sm">
                                        <div>
                                            <h4 className="font-medium">
                                                Qué hacer
                                            </h4>
                                            <ol className="mt-2 list-inside list-decimal space-y-1">
                                                {item.plan.steps.map(
                                                    (step, index) => (
                                                        <li key={index}>
                                                            {step}
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        </div>
                                        {(item.plan.proposed_total_cents !==
                                            null ||
                                            item.plan
                                                .additional_delivery_days !==
                                                null) && (
                                            <div className="space-y-1 rounded-md bg-muted p-3">
                                                <h4 className="font-medium">
                                                    Condiciones propuestas ·
                                                    requieren aprobación
                                                </h4>
                                                {item.plan
                                                    .proposed_total_cents !==
                                                    null && (
                                                    <p>
                                                        Total de la cotización:{' '}
                                                        {money(
                                                            item.plan
                                                                .proposed_total_cents,
                                                        )}
                                                    </p>
                                                )}
                                                {item.plan
                                                    .additional_delivery_days !==
                                                    null && (
                                                    <p>
                                                        Entrega:{' '}
                                                        {
                                                            item.plan
                                                                .additional_delivery_days
                                                        }{' '}
                                                        días adicionales al
                                                        plazo acordado, sujeto a
                                                        confirmación.
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                        {item.plan.message && (
                                            <div>
                                                <h4 className="font-medium">
                                                    Qué decirle · borrador para
                                                    revisar
                                                </h4>
                                                <blockquote className="mt-2 border-l-2 pl-3 whitespace-pre-wrap">
                                                    {item.plan.message}
                                                </blockquote>
                                            </div>
                                        )}
                                        {item.plan.checks.length > 0 && (
                                            <div>
                                                <h4 className="font-medium">
                                                    Antes de decidir
                                                </h4>
                                                <ul className="mt-2 list-inside list-disc space-y-1 text-muted-foreground">
                                                    {item.plan.checks.map(
                                                        (check, index) => (
                                                            <li key={index}>
                                                                {check}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </div>
                                        )}
                                    </div>
                                )}
                                {item.timing && (
                                    <p className="text-xs">
                                        {timings[item.timing] ?? item.timing}
                                        {item.scheduled_for
                                            ? ' · ' + date(item.scheduled_for)
                                            : ''}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                    <p className="text-xs text-muted-foreground">
                        La posición indica prioridad, no probabilidad de éxito.
                        La recomendación no ejecuta ninguna acción.
                    </p>
                </>
            ) : analysis ? (
                <div className="space-y-2 rounded-lg border p-4">
                    <p className="text-xs text-muted-foreground">
                        Decisión basada en reglas
                    </p>
                    <h3 className="font-medium">
                        {actions[analysis.result.action]}
                    </h3>
                    <p className="text-sm">{analysis.result.reason}</p>
                    <DecisionEvidence
                        item={analysis.alternatives.find(
                            (item) => item.selected,
                        )}
                    />
                    <p className="text-xs text-muted-foreground">
                        La lectura por sí sola no confirma intención de compra.
                    </p>
                </div>
            ) : !pending && !evaluation?.error_message ? (
                <p className="text-sm text-muted-foreground">
                    El análisis se actualizará con la actividad del cliente.
                </p>
            ) : null}
            {excluded.length > 0 && (
                <details className="border-t pt-4">
                    <summary className="cursor-pointer text-sm font-medium">
                        Acciones excluidas por las reglas
                    </summary>
                    <ul className="mt-3 space-y-3 text-sm">
                        {excluded.map((item) => (
                            <li key={item.action}>
                                <p className="font-medium">
                                    {actions[item.action]}
                                </p>
                                <p className="text-muted-foreground">
                                    {item.reason ?? item.excluded_by.join(', ')}
                                </p>
                            </li>
                        ))}
                    </ul>
                </details>
            )}
            {pending && (
                <p role="status" className="text-sm">
                    Actualizando el análisis con los últimos eventos…
                </p>
            )}
            {!pending && evaluation?.error_message && (
                <p role="status" className="text-sm text-muted-foreground">
                    {evaluation.error_message}
                </p>
            )}
        </section>
    );
}

function DecisionEvidence({
    item,
}: {
    item: Recommendation['alternatives'][number] | undefined;
}) {
    if (!item) return null;
    const summary = item.selected ? item.behavior_summary : null;
    return (
        <div className="space-y-3 text-sm">
            {!!item.evidence?.length && (
                <details>
                    <summary className="cursor-pointer font-medium">
                        Qué pasó · evidencia de esta decisión
                    </summary>
                    <ul className="mt-2 space-y-3">
                        {item.evidence.map((event) => (
                            <li
                                key={event.event_id}
                                className="border-l-2 pl-3"
                            >
                                <p className="break-words whitespace-pre-wrap">
                                    {event.summary}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {date(event.occurred_at)} ·{' '}
                                    {event.version
                                        ? `Versión ${event.version}`
                                        : 'Sin versión indicada'}{' '}
                                    ·{' '}
                                    {event.source === 'browser_unverified'
                                        ? 'Reportado por el navegador'
                                        : 'Registrado en la aplicación'}
                                </p>
                                <p className="text-xs break-all text-muted-foreground">
                                    Evento: {event.event_id}
                                </p>
                            </li>
                        ))}
                    </ul>
                </details>
            )}
            {summary && (
                <details>
                    <summary className="cursor-pointer font-medium">
                        Lectura y desplazamiento considerados
                    </summary>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Resumen disponible al evaluar; el motivo anterior
                        explica qué influyó en la prioridad.
                    </p>
                    {!summary.sections.length && !summary.scroll.length && (
                        <p className="mt-2">
                            Sin navegación registrada para este caso.
                        </p>
                    )}
                    <ul className="mt-2 space-y-1">
                        {summary.sections.map((section) => (
                            <li key={`${section.version}:${section.section}`}>
                                {section.label} ·{' '}
                                {section.version
                                    ? `v${section.version}`
                                    : 'versión desconocida'}
                                : {section.visible_events} eventos de
                                visibilidad;{' '}
                                {(
                                    section.active_duration_ms / 1000
                                ).toLocaleString('es-MX', {
                                    maximumFractionDigits: 1,
                                })}{' '}
                                s de permanencia activa reportada.
                            </li>
                        ))}
                        {summary.scroll.map((scroll) => (
                            <li key={scroll.version ?? 'unknown'}>
                                Desplazamiento máximo ·{' '}
                                {scroll.version
                                    ? `v${scroll.version}`
                                    : 'versión desconocida'}
                                : {scroll.max_depth}%.
                            </li>
                        ))}
                    </ul>
                    <p className="mt-2 text-xs text-muted-foreground">
                        {summary.measurement_note}
                    </p>
                </details>
            )}
        </div>
    );
}
