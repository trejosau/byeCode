import { useForm } from '@inertiajs/react';
import { Download, FlaskConical } from 'lucide-react';
import {
    WorkspacePage,
    Field,
    FormErrors,
    EmptyState,
} from '@/components/byecode/workspace-page';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import { date } from '@/lib/byecode';

type Metrics = {
    n: number;
    positive: number;
    brier: number;
    auc: number | null;
    ece: number;
    reliability: {
        from: number;
        to: number;
        n: number;
        predicted: number;
        observed: number;
    }[];
};
type Run = {
    id: number;
    status: string;
    data_kind: string;
    created_at: string;
    report: {
        target: string;
        eligible_rows: number;
        censored_rows: number;
        dataset_rows: number;
        reason?: string;
        split_counts?: Record<string, number>;
        metrics?: Metrics;
        baseline?: Metrics;
        gates: Record<string, boolean>;
        limitations: string[];
    };
};
const names: Record<string, string> = {
    insufficient_data: 'Datos insuficientes',
    not_validated: 'No superó los criterios',
    experimental_ready: 'Estimaciones experimentales habilitadas',
};
const gates: Record<string, string> = {
    brier_beats_baseline: 'Brier menor que la tasa base',
    auc_at_least_065: 'AUC de al menos 0.65',
    ece_at_most_015: 'Error de calibración de hasta 0.15',
    real_history: 'Historial declarado como real',
};
const splits: Record<string, string> = {
    train: 'Entrenamiento',
    calibration: 'Calibración',
    test: 'Prueba',
    purged: 'Purgados',
};
export default function Prediction({ runs }: { runs: Run[] }) {
    const history = useForm({});
    const form = useForm<{
        file: File | null;
        data_kind: string;
        authorized: boolean;
    }>({ file: null, data_kind: 'synthetic', authorized: false });
    return (
        <WorkspacePage
            title="Laboratorio predictivo"
            description="Aceptación a 14 días, con historial y pruebas. Entrenamiento local sin consumir OpenAI."
            actions={
                <Button variant="outline" asChild>
                    <a href="/prediction/template">
                        <Download />
                        Plantilla CSV
                    </a>
                </Button>
            }
        >
            <Card className="shadow-none">
                <CardContent className="flex flex-wrap items-center justify-between gap-4 py-5">
                    <div>
                        <h2 className="font-semibold">
                            Historial del aplicativo
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Casos del equipo completo, actividad y propiedades
                            conocidas en cada fecha. Sin llamadas a OpenAI.
                        </p>
                    </div>
                    <Button
                        disabled={history.processing}
                        onClick={() => history.post('/prediction/history')}
                    >
                        Evaluar historial comercial
                    </Button>
                </CardContent>
            </Card>
            <Alert>
                <FlaskConical className="size-4" />
                <AlertTitle>
                    Una predicción necesita resultados observados
                </AlertTitle>
                <AlertDescription>
                    Mínimo 200 casos maduros. Regresión logística regularizada,
                    calibración separada y prueba temporal con clientes
                    aislados. Los seis casos del kit no permiten validar un
                    predictor.
                </AlertDescription>
            </Alert>
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <section className="space-y-5">
                    {runs.length ? (
                        runs.map((run) => (
                            <Card key={run.id} className="shadow-none">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Evaluación predictiva #{run.id}
                                    </CardTitle>
                                    <Badge className="w-fit" variant="outline">
                                        {names[run.status]}
                                    </Badge>
                                    <p className="text-xs text-muted-foreground">
                                        {date(run.created_at)} ·{' '}
                                        {run.data_kind === 'real'
                                            ? 'Historial declarado real'
                                            : 'Prueba sintética'}
                                    </p>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <p className="text-sm">
                                        {run.report.target}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {run.report.eligible_rows} casos
                                        elegibles de {run.report.dataset_rows} ·{' '}
                                        {run.report.censored_rows} censurados
                                    </p>
                                    {run.report.reason && (
                                        <Alert>
                                            <AlertDescription>
                                                {run.report.reason}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                    {run.report.split_counts && (
                                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                            {Object.entries(
                                                run.report.split_counts,
                                            ).map(([key, n]) => (
                                                <div
                                                    key={key}
                                                    className="border-t pt-3"
                                                >
                                                    <p className="text-xs text-muted-foreground">
                                                        {splits[key]}
                                                    </p>
                                                    <p className="mt-1 text-lg font-semibold">
                                                        {n}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    {run.report.metrics && (
                                        <>
                                            <div className="grid grid-cols-2 gap-4 rounded-lg bg-muted/50 p-4">
                                                <div>
                                                    <p className="text-xs text-muted-foreground">
                                                        Brier · menor es mejor
                                                    </p>
                                                    <p className="mt-1 font-semibold">
                                                        {run.report.metrics.brier.toFixed(
                                                            4,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Referencia:{' '}
                                                        {run.report.baseline?.brier.toFixed(
                                                            4,
                                                        )}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted-foreground">
                                                        AUC · separación
                                                    </p>
                                                    <p className="mt-1 font-semibold">
                                                        {run.report.metrics.auc?.toFixed(
                                                            3,
                                                        ) ?? 'No calculable'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Error de calibración:{' '}
                                                        {run.report.metrics.ece.toFixed(
                                                            3,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Rango
                                                        </TableHead>
                                                        <TableHead>
                                                            Casos
                                                        </TableHead>
                                                        <TableHead>
                                                            Estimación
                                                        </TableHead>
                                                        <TableHead>
                                                            Observado
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {run.report.metrics.reliability.map(
                                                        (b) => (
                                                            <TableRow
                                                                key={b.from}
                                                            >
                                                                <TableCell>
                                                                    {b.from *
                                                                        100}
                                                                    –
                                                                    {b.to * 100}
                                                                    %
                                                                </TableCell>
                                                                <TableCell>
                                                                    {b.n}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {(
                                                                        b.predicted *
                                                                        100
                                                                    ).toFixed(
                                                                        1,
                                                                    )}
                                                                    %
                                                                </TableCell>
                                                                <TableCell>
                                                                    {(
                                                                        b.observed *
                                                                        100
                                                                    ).toFixed(
                                                                        1,
                                                                    )}
                                                                    %
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </>
                                    )}
                                    <div className="space-y-2">
                                        {Object.entries(run.report.gates).map(
                                            ([key, ok]) => (
                                                <div
                                                    key={key}
                                                    className="flex justify-between gap-4 text-sm"
                                                >
                                                    <span>{gates[key]}</span>
                                                    <Badge
                                                        variant={
                                                            ok
                                                                ? 'outline'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {ok
                                                            ? 'Cumple'
                                                            : 'No cumple'}
                                                    </Badge>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                    <ul className="list-inside list-disc space-y-2 text-xs leading-relaxed text-muted-foreground">
                                        {run.report.limitations.map((l) => (
                                            <li key={l}>{l}</li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        <EmptyState
                            title="Sin predictor evaluado"
                            description="Sube historial autorizado con instantáneas anteriores al resultado. Hasta entonces no se muestran probabilidades en los casos."
                        />
                    )}
                </section>
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Entrenar y evaluar
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/prediction/train', {
                                    forceFormData: true,
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <FormErrors errors={form.errors} />
                            <Field label="Historial CSV" htmlFor="history-file">
                                <Input
                                    id="history-file"
                                    type="file"
                                    accept=".csv"
                                    required
                                    onChange={(e) =>
                                        form.setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Procedencia" htmlFor="data-kind">
                                <Select
                                    value={form.data.data_kind}
                                    onValueChange={(v) =>
                                        form.setData('data_kind', v)
                                    }
                                >
                                    <SelectTrigger id="data-kind">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="synthetic">
                                            Datos sintéticos
                                        </SelectItem>
                                        <SelectItem value="real">
                                            Historial real autorizado
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <div className="flex items-start gap-2">
                                <Checkbox
                                    id="training-authorized"
                                    checked={form.data.authorized}
                                    onCheckedChange={(v) =>
                                        form.setData('authorized', v === true)
                                    }
                                />
                                <Label
                                    htmlFor="training-authorized"
                                    className="text-sm leading-relaxed"
                                >
                                    Confirmo la procedencia y que las variables
                                    solo incluyen información conocida al
                                    evaluar cada caso.
                                </Label>
                            </div>
                            <Button
                                disabled={
                                    form.processing ||
                                    !form.data.file ||
                                    !form.data.authorized
                                }
                                className="w-full"
                            >
                                {form.processing
                                    ? 'Entrenando…'
                                    : 'Ejecutar evaluación local'}
                            </Button>
                        </form>
                        <div className="mt-5 space-y-3 border-t pt-5 text-xs leading-relaxed text-muted-foreground">
                            <p>
                                Una fila por caso. Fechas UTC con Z. Clientes
                                seudónimos; no se necesitan nombres ni mensajes.
                            </p>
                            <p>
                                outcome: accepted, rejected, cancelled u open.
                                closed_at vacío para abiertos. observed_until
                                indica hasta cuándo conoces el resultado.
                            </p>
                            <p>
                                Un entrenamiento nuevo reemplaza la elegibilidad
                                del anterior. Los datos sintéticos nunca
                                habilitan estimaciones reales. Estas pruebas no
                                sustituyen validación prospectiva.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </WorkspacePage>
    );
}
Prediction.layout = {
    breadcrumbs: [
        { title: 'Inteligencia', href: '/intelligence' },
        { title: 'Laboratorio predictivo', href: '/prediction' },
    ],
};
