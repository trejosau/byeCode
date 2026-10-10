import { useForm } from '@inertiajs/react';
import { ShieldCheck, Clock3, ChevronDown } from 'lucide-react';
import { Field, FormErrors } from '@/components/byecode/workspace-page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import {
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import {
    actions,
    date,
    levels,
    timings,
    statuses,
    signalNames,
} from '@/lib/byecode';
import type { Recommendation } from '@/types/byecode';

export function RecommendationPanel({
    recommendation,
}: {
    recommendation: Recommendation;
}) {
    const r = recommendation.result;
    const ranked = recommendation.alternatives.filter((a) => a.rank != null);
    const review = useForm({ decision: 'approved', reason: '' });
    const feedback = useForm({ verdict: 'useful', reason: '' });
    return (
        <Card className="shadow-none">
            <CardHeader className="gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <ShieldCheck className="size-4" />
                        Siguiente decisión
                    </CardTitle>
                    <Badge variant="outline">
                        {statuses[recommendation.source] ??
                            recommendation.source}
                    </Badge>
                </div>
                <h2 className="text-2xl font-semibold tracking-tight">
                    {actions[r.action]}
                </h2>
                <Badge
                    variant={
                        recommendation.publication_status === 'awaiting_review'
                            ? 'secondary'
                            : 'outline'
                    }
                    className="w-fit"
                >
                    {statuses[recommendation.publication_status]}
                </Badge>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="flex items-start gap-2 text-sm">
                    <Clock3 className="mt-0.5 size-4 shrink-0" />
                    <div>
                        {timings[r.timing]}
                        {r.scheduled_for && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {date(r.scheduled_for)} · Ciudad de México
                            </p>
                        )}
                    </div>
                </div>
                <p className="text-sm leading-relaxed">{r.reason}</p>
                {recommendation.change_summary && (
                    <section className="space-y-2 rounded-md bg-muted p-3 text-xs">
                        <h3 className="font-semibold">
                            Qué cambió desde la decisión anterior
                        </h3>
                        <p>
                            {recommendation.change_summary.previous_action ===
                            r.action
                                ? 'Se mantiene la acción: ' +
                                  actions[r.action] +
                                  '.'
                                : actions[
                                      recommendation.change_summary
                                          .previous_action
                                  ] +
                                  ' → ' +
                                  actions[r.action] +
                                  '.'}
                        </p>
                        <p>
                            {recommendation.change_summary.new_event_ids.length}{' '}
                            eventos nuevos del caso ·{' '}
                            {recommendation.change_summary.new_activity_count}{' '}
                            registros nuevos en el contexto de actividad.
                        </p>
                        <p className="text-muted-foreground">
                            El historial conserva la decisión anterior y su
                            evidencia.
                        </p>
                    </section>
                )}
                <dl className="grid grid-cols-2 gap-4 border-y py-4">
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Confianza en la decisión
                        </dt>
                        <dd className="mt-1 font-medium">
                            {levels[r.confidence]}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Riesgo
                        </dt>
                        <dd className="mt-1 font-medium">
                            {levels[r.risk_level]}
                        </dd>
                    </div>
                </dl>
                <p className="text-xs leading-relaxed text-muted-foreground">
                    {r.confidence_reason}
                </p>
                {recommendation.signals.length > 0 && (
                    <section className="space-y-3">
                        <h3 className="text-sm font-semibold">
                            Interpretación y evidencia
                        </h3>
                        {recommendation.signals.map((s, i) => (
                            <div key={i} className="space-y-1">
                                <Badge variant="outline">
                                    {signalNames[s.kind] ?? s.kind}
                                </Badge>
                                <blockquote className="text-sm leading-relaxed">
                                    “{s.quote}”
                                </blockquote>
                                <a
                                    href={'#event-' + s.event_id}
                                    className="text-xs underline underline-offset-4"
                                >
                                    {s.event_id}
                                </a>
                            </div>
                        ))}
                    </section>
                )}
                <section>
                    <h3 className="mb-2 text-sm font-semibold">
                        Eventos de respaldo
                    </h3>
                    <div className="flex flex-wrap gap-2">
                        {r.evidence_event_ids.map((id) => (
                            <Badge key={id} variant="secondary" asChild>
                                <a href={'#event-' + id}>{id}</a>
                            </Badge>
                        ))}
                    </div>
                </section>
                {r.missing_information.length > 0 && (
                    <section>
                        <h3 className="mb-2 text-sm font-semibold">
                            Información pendiente
                        </h3>
                        <ul className="list-inside list-disc space-y-2 text-sm text-muted-foreground">
                            {r.missing_information.map((x) => (
                                <li key={x}>{x}</li>
                            ))}
                        </ul>
                    </section>
                )}
                {ranked.length > 0 && (
                    <section className="space-y-3 border-t pt-4">
                        <h3 className="text-sm font-semibold">
                            Prioridad de acciones
                        </h3>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {ranked[0].ranking_source === 'model_validated'
                                ? 'Orden propuesto por IA y validado con las reglas y la evidencia del caso.'
                                : 'Acción sustentada por las reglas del caso.'}{' '}
                            La posición indica prioridad, no probabilidad de
                            éxito.
                        </p>
                        <ol className="space-y-3">
                            {ranked.map((alternative) => (
                                <li
                                    key={alternative.action}
                                    className="rounded-md border p-3 text-sm"
                                >
                                    <div className="flex items-start justify-between gap-2 font-medium">
                                        <span>
                                            {alternative.rank}.{' '}
                                            {actions[alternative.action]}
                                        </span>
                                        {alternative.selected && (
                                            <Badge variant="secondary">
                                                Sugerida
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                                        {alternative.reason}
                                    </p>
                                    {alternative.timing && (
                                        <p className="mt-2 text-xs">
                                            {timings[alternative.timing]}
                                            {alternative.scheduled_for
                                                ? ' · ' +
                                                  date(
                                                      alternative.scheduled_for,
                                                  )
                                                : ''}
                                        </p>
                                    )}
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {alternative.evidence_event_ids?.map(
                                            (id) => (
                                                <a
                                                    key={id}
                                                    href={'#event-' + id}
                                                    className="text-xs underline underline-offset-4"
                                                >
                                                    {id}
                                                </a>
                                            ),
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ol>
                        <p className="text-xs text-muted-foreground">
                            Probabilidad de éxito por acción: sin estimación
                            validada. La estimación del caso se muestra por
                            separado.
                        </p>
                    </section>
                )}
                <Collapsible>
                    <CollapsibleTrigger asChild>
                        <Button
                            variant="ghost"
                            className="w-full justify-between px-0 text-sm"
                        >
                            {ranked.length
                                ? 'Acciones excluidas y motivos'
                                : 'Alternativas y restricciones'}
                            <ChevronDown className="size-4" />
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="space-y-3 pt-2">
                        {recommendation.alternatives
                            .filter((a) => !ranked.length || a.rank == null)
                            .map((a) => (
                                <div
                                    className="flex justify-between gap-3 text-xs"
                                    key={a.action}
                                >
                                    <span>{actions[a.action]}</span>
                                    <span className="text-right text-muted-foreground">
                                        {a.selected
                                            ? 'Seleccionada'
                                            : a.allowed
                                              ? 'Admisible, no seleccionada'
                                              : (a.reason ??
                                                'Excluida: ' +
                                                    a.excluded_by.join(', '))}
                                    </span>
                                </div>
                            ))}
                    </CollapsibleContent>
                </Collapsible>
                {recommendation.is_current &&
                    recommendation.publication_status === 'awaiting_review' && (
                        <form
                            className="space-y-3 border-t pt-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                const submitter = (e.nativeEvent as SubmitEvent)
                                    .submitter as HTMLButtonElement | null;
                                review.transform((data) => ({
                                    ...data,
                                    decision:
                                        submitter?.value === 'rejected'
                                            ? 'rejected'
                                            : 'approved',
                                }));
                                review.post(
                                    '/recommendations/' +
                                        recommendation.id +
                                        '/review',
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <h3 className="text-sm font-semibold">
                                Tu revisión
                            </h3>
                            <FormErrors errors={review.errors} />
                            <Field
                                label="Motivo y verificaciones realizadas"
                                htmlFor={'review-' + recommendation.id}
                            >
                                <Textarea
                                    id={'review-' + recommendation.id}
                                    required
                                    minLength={5}
                                    value={review.data.reason}
                                    onChange={(e) =>
                                        review.setData('reason', e.target.value)
                                    }
                                    placeholder="Explica por qué esta decisión es válida o debe rechazarse."
                                />
                            </Field>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    disabled={review.processing}
                                    name="decision"
                                    value="approved"
                                >
                                    Aprobar decisión
                                </Button>
                                <Button
                                    variant="outline"
                                    disabled={review.processing}
                                    name="decision"
                                    value="rejected"
                                >
                                    Rechazar
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Aprobar no envía un mensaje ni registra una
                                acción realizada.
                            </p>
                        </form>
                    )}
                {recommendation.review && (
                    <div className="border-t pt-4">
                        <p className="text-sm font-medium">
                            {recommendation.review.decision === 'approved'
                                ? 'Revisión aprobada'
                                : 'Revisión rechazada'}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {recommendation.review.reason}
                        </p>
                    </div>
                )}
                <Collapsible>
                    <CollapsibleTrigger asChild>
                        <Button
                            variant="ghost"
                            className="w-full justify-between px-0"
                        >
                            Registrar utilidad
                            <ChevronDown className="size-4" />
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <form
                            className="space-y-3 pt-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                const submitter = (e.nativeEvent as SubmitEvent)
                                    .submitter as HTMLButtonElement | null;
                                feedback.transform((data) => ({
                                    ...data,
                                    verdict:
                                        submitter?.value === 'not_useful'
                                            ? 'not_useful'
                                            : 'useful',
                                }));
                                feedback.post(
                                    '/recommendations/' +
                                        recommendation.id +
                                        '/feedback',
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            feedback.reset('reason'),
                                    },
                                );
                            }}
                        >
                            <FormErrors errors={feedback.errors} />
                            <Field
                                label="Qué ayudó o qué corregirías"
                                htmlFor={'feedback-' + recommendation.id}
                            >
                                <Textarea
                                    id={'feedback-' + recommendation.id}
                                    required
                                    minLength={5}
                                    value={feedback.data.reason}
                                    onChange={(e) =>
                                        feedback.setData(
                                            'reason',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="flex gap-2">
                                <Button
                                    size="sm"
                                    disabled={feedback.processing}
                                    name="verdict"
                                    value="useful"
                                >
                                    Fue útil
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={feedback.processing}
                                    name="verdict"
                                    value="not_useful"
                                >
                                    No fue útil
                                </Button>
                            </div>
                        </form>
                    </CollapsibleContent>
                </Collapsible>
                <p className="text-[11px] leading-relaxed text-muted-foreground">
                    Vigencia máxima: {date(recommendation.valid_until)}. Un
                    cambio del caso la invalida antes. Horas mostradas en Ciudad
                    de México.
                </p>
            </CardContent>
        </Card>
    );
}
