import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    Plus,
    ShieldCheck,
    CircleHelp,
    Wallet,
} from 'lucide-react';
import { CaseTable } from '@/components/byecode/case-table';
import { WorkspacePage } from '@/components/byecode/workspace-page';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { money } from '@/lib/byecode';
import type { CaseRow, Budget } from '@/types/byecode';

export default function Dashboard({
    cases,
    budget,
    aiReady,
}: {
    cases: CaseRow[];
    budget: Budget;
    aiReady: boolean;
}) {
    const live = cases.filter((c) => c.mode === 'live');
    const open = live.filter((c) => c.status === 'open');
    const review = live.filter(
        (c) => c.publication_status === 'awaiting_review',
    );
    const wait = live.filter((c) => c.recommendation?.action === 'wait');
    return (
        <WorkspacePage
            title="Resumen comercial"
            description="Una decisión con contexto. Atiende lo que necesita revisión y conserva el espacio para esperar."
            actions={
                <>
                    <Button variant="outline" asChild>
                        <Link href="/imports">Probar con el kit</Link>
                    </Button>
                    <Button asChild>
                        <Link href="/quotes/create">
                            <Plus />
                            Nueva cotización
                        </Link>
                    </Button>
                </>
            }
        >
            {!aiReady && (
                <Alert>
                    <CircleHelp className="size-4" />
                    <AlertTitle className="line-clamp-none">
                        La interpretación de IA está lista para conectarse
                    </AlertTitle>
                    <AlertDescription>
                        La conexión con IA está pendiente. Las reglas y el
                        historial ya funcionan; los casos que necesitan
                        interpretación muestran su estado pendiente.
                        <Link
                            href="/intelligence"
                            className="mt-2 inline-flex items-center gap-1 font-medium underline underline-offset-4"
                        >
                            Ver configuración y presupuesto{' '}
                            <ArrowRight className="size-3" />
                        </Link>
                    </AlertDescription>
                </Alert>
            )}
            <div className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border xl:grid-cols-4">
                {[
                    {
                        label: 'Cartera abierta visible',
                        value: money(
                            open.reduce(
                                (sum, c) => sum + (c.amount_cents ?? 0),
                                0,
                            ),
                        ),
                        detail:
                            open.length +
                            ' cotizaciones del espacio operativo · MXN',
                    },
                    {
                        label: 'Revisiones pendientes',
                        value: review.length,
                        detail: 'Espacio operativo · requieren criterio humano',
                    },
                    {
                        label: 'En espera',
                        value: wait.length,
                        detail: 'Espacio operativo · reevaluar al cambiar la condición',
                    },
                    {
                        label: 'Consumo preventivo · 7 días',
                        value: money(budget.weekly_micromxn / 10000),
                        detail:
                            'De ' +
                            money(budget.weekly_limit_mxn * 100) +
                            ' · consumo de toda la instalación',
                    },
                ].map((x) => (
                    <div key={x.label} className="space-y-2 bg-card p-5">
                        <p className="text-xs text-muted-foreground">
                            {x.label}
                        </p>
                        <p className="text-2xl font-semibold tracking-tight break-words">
                            {x.value}
                        </p>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {x.detail}
                        </p>
                    </div>
                ))}
            </div>
            <section className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Bandeja de trabajo
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Últimas actualizaciones del espacio operativo y de
                            los kits sintéticos, identificados en cada caso. Los
                            indicadores comerciales superiores excluyen los
                            kits.
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/cases">
                            Todas las cotizaciones <ArrowRight />
                        </Link>
                    </Button>
                </div>
                <CaseTable cases={cases.slice(0, 6)} />
            </section>
            <div className="grid gap-5 xl:grid-cols-3">
                <Card className="shadow-none xl:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShieldCheck className="size-4" />
                            Reglas operativas
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-3">
                        <div>
                            <Badge variant="outline">R1 · Cierre</Badge>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Un caso cerrado permanece sin intervención.
                            </p>
                        </div>
                        <div>
                            <Badge variant="outline">R2–R3 · Permiso</Badge>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Las restricciones y fechas mandan sobre la
                                recomendación.
                            </p>
                        </div>
                        <div>
                            <Badge variant="outline">R4 · Pausa de 24 h</Badge>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Solo un mensaje posterior levanta la pausa de
                                seguimiento.
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Wallet className="size-4" />
                            IA con un límite claro
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            {budget.daily_calls} llamadas en 24 horas. El
                            presupuesto se reserva antes de consultar al modelo.
                        </p>
                        <Button variant="link" className="mt-3 px-0" asChild>
                            <Link href="/intelligence">
                                Revisar consumo y evaluaciones <ArrowRight />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </WorkspacePage>
    );
}
Dashboard.layout = { breadcrumbs: [{ title: 'Resumen', href: '/dashboard' }] };
