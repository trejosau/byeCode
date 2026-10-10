import { Link } from '@inertiajs/react';
import { BrainCircuit, ShieldCheck, Wallet, ArrowRight } from 'lucide-react';
import { WorkspacePage } from '@/components/byecode/workspace-page';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import { date, money, statuses } from '@/lib/byecode';
import type { Budget, Evaluation } from '@/types/byecode';
type Usage = {
    evaluation_id: number;
    status: string;
    input_tokens: number | null;
    output_tokens: number | null;
    reserved_micromxn: number;
    charged_micromxn: number | null;
};

export default function Intelligence({
    budget,
    model,
    aiReady,
    evaluations,
    usage,
}: {
    budget: Budget;
    model: string;
    aiReady: boolean;
    evaluations: Evaluation[];
    usage: Usage[];
}) {
    return (
        <WorkspacePage
            title="Inteligencia y consumo"
            description="Interpretaciones verificables, límites de gasto y trazabilidad de cada evaluación."
        >
            <div className="grid gap-5 lg:grid-cols-2">
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <BrainCircuit className="size-4" />
                            Motor de decisiones
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <span className="text-lg font-semibold">
                                {model}
                            </span>
                            <Badge variant="outline">
                                {aiReady
                                    ? 'Clave configurada'
                                    : 'Falta API key'}
                            </Badge>
                        </div>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            GPT interpreta mensajes; Laravel verifica evidencia,
                            restricciones y vigencia antes de publicar. Las
                            decisiones de contacto requieren revisión humana.
                        </p>
                        {!aiReady && (
                            <Alert>
                                <AlertTitle>
                                    Un paso para activar OpenAI
                                </AlertTitle>
                                <AlertDescription>
                                    Agrega <code>OPENAI_API_KEY</code> al
                                    archivo .env y reinicia{' '}
                                    <code>composer run dev</code>. El modelo
                                    económico y los límites ya están
                                    configurados. La clave nunca pasa al
                                    navegador.
                                </AlertDescription>
                            </Alert>
                        )}
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Modelo seleccionado por costo y soporte de salidas
                            estructuradas. La calidad semántica real debe
                            medirse con la batería de evaluación; las pruebas
                            simuladas no acreditan precisión del modelo.
                        </p>
                    </CardContent>
                </Card>
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Wallet className="size-4" />
                            Presupuesto preventivo
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {[
                            {
                                title: 'Últimas 24 horas',
                                used: budget.daily_micromxn,
                                limit: budget.daily_limit_mxn,
                            },
                            {
                                title: 'Últimos 7 días',
                                used: budget.weekly_micromxn,
                                limit: budget.weekly_limit_mxn,
                            },
                        ].map((x) => (
                            <div key={x.title}>
                                <div className="mb-2 flex justify-between gap-3 text-sm">
                                    <span>{x.title}</span>
                                    <span className="font-medium">
                                        {money(x.used / 10000)} /{' '}
                                        {money(x.limit * 100)}
                                    </span>
                                </div>
                                <div
                                    role="progressbar"
                                    aria-label={x.title}
                                    aria-valuenow={
                                        Math.round(x.used / 10000) / 100
                                    }
                                    aria-valuemin={0}
                                    aria-valuemax={x.limit}
                                    className="h-2 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        className="h-full bg-foreground"
                                        style={{
                                            width:
                                                Math.min(
                                                    100,
                                                    x.used / (x.limit * 10000),
                                                ) + '%',
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {budget.daily_calls} llamadas en 24 h ·{' '}
                            {budget.uncertain_calls} con reserva pendiente o
                            costo incierto.
                        </p>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Límites compartidos por esta instalación. Se usa 25
                            MXN/USD y margen de 25% para reservar, no como tipo
                            de cambio bancario. Del presupuesto declarado de
                            $200 MXN, $40 quedan fuera del límite semanal como
                            margen.
                        </p>
                    </CardContent>
                </Card>
            </div>
            <Alert>
                <ShieldCheck className="size-4" />
                <AlertTitle>
                    Probabilidad de cierre: requiere un historial validado
                </AlertTitle>
                <AlertDescription>
                    La confianza de la recomendación no es probabilidad de
                    compra. El kit sintético no permite entrenar ni validar un
                    predictor comercial. Consulta la evaluación predictiva y su
                    estado antes de usar porcentajes.
                    <Button
                        variant="link"
                        className="h-auto justify-start px-0"
                        asChild
                    >
                        <Link href="/prediction">
                            Abrir laboratorio predictivo{' '}
                            <ArrowRight className="size-3" />
                        </Link>
                    </Button>
                </AlertDescription>
            </Alert>
            <section className="space-y-4">
                <h2 className="text-lg font-semibold">Últimas evaluaciones</h2>
                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Evaluación</TableHead>
                                <TableHead>Resultado</TableHead>
                                <TableHead>Modelo / origen</TableHead>
                                <TableHead>Tokens entrada / salida</TableHead>
                                <TableHead className="text-right">
                                    Costo preventivo
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {evaluations.map((e) => {
                                const u = usage.find(
                                    (u) => u.evaluation_id === e.id,
                                );
                                return (
                                    <TableRow key={e.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                #{e.id}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {date(e.created_at)}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {statuses[e.status] ?? e.status}
                                            </Badge>
                                            {e.error_message && (
                                                <p className="mt-2 max-w-sm text-xs text-muted-foreground">
                                                    {e.error_message}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {e.model ?? statuses[e.provider]}
                                        </TableCell>
                                        <TableCell>
                                            {u
                                                ? (u.input_tokens ?? '—') +
                                                  ' / ' +
                                                  (u.output_tokens ?? '—')
                                                : 'Sin llamada'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {u
                                                ? money(
                                                      (u.charged_micromxn ??
                                                          u.reserved_micromxn) /
                                                          10000,
                                                  )
                                                : '$0.00'}
                                            {u && u.status !== 'confirmed' && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Reserva conservada
                                                </p>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                            {!evaluations.length && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        Todavía no hay evaluaciones. Carga el
                                        kit o crea una cotización.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </section>
        </WorkspacePage>
    );
}
Intelligence.layout = {
    breadcrumbs: [{ title: 'Inteligencia y consumo', href: '/intelligence' }],
};
