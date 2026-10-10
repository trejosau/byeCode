import { Link } from '@inertiajs/react';
import { ArrowUpRight, Clock3, LockKeyhole } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import { EmptyState } from '@/components/byecode/workspace-page';
import { actions, money, stages, statuses, timings } from '@/lib/byecode';
import type { CaseRow } from '@/types/byecode';

export function CaseTable({ cases }: { cases: CaseRow[] }) {
    if (!cases.length)
        return (
            <EmptyState
                title="Tu bandeja está lista"
                description="Crea una cotización con tus productos o recorre los casos importados."
                href="/imports"
                label="Explorar el kit de prueba"
            />
        );
    return (
        <>
            <div className="divide-y rounded-xl border md:hidden">
                {cases.map((c) => (
                    <article key={c.id} className="space-y-4 p-4">
                        <div>
                            <Link
                                href={'/cases/' + c.id}
                                className="font-semibold underline-offset-4 hover:underline"
                            >
                                {c.title}
                            </Link>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {c.external_id} ·{' '}
                                {c.customer ?? 'Sin cliente vinculado'}
                            </p>
                            {c.mode === 'kit' && (
                                <Badge variant="outline" className="mt-2">
                                    Importación
                                </Badge>
                            )}
                        </div>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <Badge variant="secondary">
                                    {statuses[c.status] ?? c.status}
                                </Badge>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {stages[c.stage] ?? c.stage}
                                </p>
                            </div>
                            <p className="text-sm font-medium tabular-nums">
                                {money(c.amount_cents)} MXN
                            </p>
                        </div>
                        {c.integrity_blocked && (
                            <p className="flex items-center gap-1 text-xs text-destructive">
                                <LockKeyhole className="size-3" />
                                Historial en revisión
                            </p>
                        )}
                        <div className="border-t pt-3">
                            <p className="mb-2 text-xs text-muted-foreground">
                                Decisión actual
                            </p>
                            <CaseDecision c={c} />
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={'/cases/' + c.id}>
                                Abrir caso <ArrowUpRight className="size-4" />
                            </Link>
                        </Button>
                    </article>
                ))}
            </div>
            <div className="hidden overflow-hidden rounded-xl border md:block">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/45">
                            <TableHead>Cotización / cliente</TableHead>
                            <TableHead className="text-right">
                                Importe MXN
                            </TableHead>
                            <TableHead>Estado comercial</TableHead>
                            <TableHead>Decisión actual</TableHead>
                            <TableHead>
                                <span className="sr-only">Ver caso</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {cases.map((c) => (
                            <TableRow key={c.id}>
                                <TableCell className="min-w-48">
                                    <Link
                                        href={'/cases/' + c.id}
                                        className="font-semibold underline-offset-4 hover:underline"
                                    >
                                        {c.title}
                                    </Link>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {c.external_id} ·{' '}
                                        {c.customer ?? 'Sin cliente vinculado'}
                                    </div>
                                    {c.mode === 'kit' && (
                                        <Badge
                                            variant="outline"
                                            className="mt-2 text-[11px]"
                                        >
                                            Importación
                                        </Badge>
                                    )}
                                </TableCell>
                                <TableCell className="text-right font-medium whitespace-nowrap tabular-nums">
                                    {money(c.amount_cents)}
                                </TableCell>
                                <TableCell className="min-w-40">
                                    <Badge variant="secondary">
                                        {statuses[c.status] ?? c.status}
                                    </Badge>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {stages[c.stage] ?? c.stage}
                                    </p>
                                    {c.integrity_blocked && (
                                        <p className="mt-2 flex items-center gap-1 text-xs text-destructive">
                                            <LockKeyhole className="size-3" />
                                            Historial en revisión
                                        </p>
                                    )}
                                </TableCell>
                                <TableCell className="max-w-80 min-w-56">
                                    <CaseDecision c={c} />
                                </TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="icon" asChild>
                                        <Link
                                            href={'/cases/' + c.id}
                                            aria-label={
                                                'Abrir ' + c.external_id
                                            }
                                        >
                                            <ArrowUpRight className="size-4" />
                                        </Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

function CaseDecision({ c }: { c: CaseRow }) {
    return (
        <>
            {c.recommendation ? (
                <>
                    <div className="font-medium">
                        {actions[c.recommendation.action]}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {timings[c.recommendation.timing]}
                    </p>
                    <p className="mt-2 text-xs">
                        {statuses[c.publication_status ?? ''] ??
                            c.publication_status}{' '}
                        · {statuses[c.source ?? ''] ?? c.source}
                    </p>
                </>
            ) : (
                <>
                    <div className="flex items-center gap-2 text-sm">
                        <Clock3 className="size-4" />
                        {statuses[c.evaluation_status ?? ''] ?? 'Sin evaluar'}
                    </div>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {c.error_message ??
                            'La recomendación aparecerá al evaluar el contexto.'}
                    </p>
                </>
            )}
        </>
    );
}
