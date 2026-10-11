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

    return (
        <section
            id="analysis"
            className="max-h-[70dvh] scroll-mt-6 space-y-5 overflow-y-auto overscroll-y-contain rounded-xl border p-5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:max-h-[calc(100dvh-3rem)]"
            aria-labelledby="ranking-title"
            tabIndex={0}
        >
            <div className="space-y-1">
                <h2 id="ranking-title" className="text-xl font-semibold">
                    Ranking de acciones a tomar
                </h2>
                <p className="text-sm text-muted-foreground">
                    Acciones directas recomendadas y escenarios de probabilidad comercial.
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
                <ol className="space-y-4">
                    {ranked.map((item) => (
                        <li
                            key={item.action}
                            className="space-y-3 rounded-lg border p-4 shadow-xs"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <h3 className="text-base font-semibold">
                                    {item.rank}.{' '}
                                    {item.plan?.title ??
                                        actions[item.action]}
                                </h3>
                                {item.rank === 1 ? (
                                    <span className="rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 px-2 py-0.5 text-xs font-medium shrink-0">
                                        Mejor escenario
                                    </span>
                                ) : item.action === 'wait' || item.action === 'no_action' ? (
                                    <span className="rounded bg-amber-500/10 text-amber-700 dark:text-amber-400 px-2 py-0.5 text-xs font-medium shrink-0">
                                        Inacción
                                    </span>
                                ) : (
                                    <span className="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground shrink-0">
                                        Alternativa {item.rank}
                                    </span>
                                )}
                            </div>
                            <div className="rounded-md bg-muted/60 p-3 text-sm">
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Qué puede pasar · Escenario y probabilidad
                                </h4>
                                <p className="mt-1 leading-relaxed">
                                    {item.plan?.reason ?? item.reason}
                                </p>
                            </div>
                            {item.plan && (
                                <div className="space-y-3 pt-1 text-sm">
                                    {item.plan.steps.length > 0 && (
                                        <div>
                                            <h4 className="font-semibold text-xs uppercase tracking-wider text-muted-foreground">
                                                Acciones directas a tomar
                                            </h4>
                                            <ol className="mt-1.5 list-inside list-decimal space-y-1">
                                                {item.plan.steps.map(
                                                    (step, index) => (
                                                        <li key={index} className="leading-snug">
                                                            {step}
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        </div>
                                    )}
                                    {(item.plan.proposed_total_cents !==
                                        null ||
                                        item.plan
                                            .additional_delivery_days !==
                                            null) && (
                                        <div className="space-y-1 rounded-md bg-muted/80 p-3">
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
                                            <h4 className="font-semibold text-xs uppercase tracking-wider text-muted-foreground">
                                                Qué decirle · borrador para
                                                revisar
                                            </h4>
                                            <blockquote className="mt-1.5 rounded border-l-2 pl-3 py-1 bg-muted/40 text-sm whitespace-pre-wrap">
                                                {item.plan.message}
                                            </blockquote>
                                        </div>
                                    )}
                                    {item.plan.checks.length > 0 && (
                                        <div>
                                            <h4 className="font-semibold text-xs uppercase tracking-wider text-muted-foreground">
                                                Validaciones previas
                                            </h4>
                                            <ul className="mt-1.5 list-inside list-disc space-y-1 text-muted-foreground">
                                                {item.plan.checks.map(
                                                    (check, index) => (
                                                        <li key={index} className="leading-snug">
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
                                <p className="text-xs text-muted-foreground">
                                    Momento: {timings[item.timing] ?? item.timing}
                                    {item.scheduled_for
                                        ? ' · ' + date(item.scheduled_for)
                                        : ''}
                                </p>
                            )}
                        </li>
                    ))}
                </ol>
            ) : analysis ? (
                <div className="space-y-2 rounded-lg border p-4">
                    <p className="text-xs text-muted-foreground">
                        Decisión basada en reglas
                    </p>
                    <h3 className="font-medium">
                        {actions[analysis.result.action]}
                    </h3>
                    <p className="text-sm">{analysis.result.reason}</p>
                </div>
            ) : !pending && !evaluation?.error_message ? (
                <p className="text-sm text-muted-foreground">
                    El análisis se actualizará con la actividad del cliente.
                </p>
            ) : null}
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
