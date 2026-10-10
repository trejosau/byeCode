import { Link, router, useForm } from '@inertiajs/react';
import { Download, FlaskConical, Upload, Play, RefreshCw } from 'lucide-react';
import {
    WorkspacePage,
    Field,
    FormErrors,
    EmptyState,
} from '@/components/byecode/workspace-page';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardContent,
    CardDescription,
} from '@/components/ui/card';
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
import type { DataSet } from '@/types/byecode';
type Batch = {
    id: number;
    data_set_id: number;
    name: string;
    accepted: number;
    duplicates: number;
    errors: { line: number; case_id: string | null; message: string }[];
    created_at: string;
};

export default function Imports({
    sets,
    batches,
}: {
    sets: DataSet[];
    batches: Batch[];
}) {
    const kits = sets.filter((s) => s.mode === 'kit');
    const form = useForm<{ data_set_id: string; file: File | null }>({
        data_set_id: String(kits[0]?.id ?? ''),
        file: null,
    });
    const load = useForm({});
    return (
        <WorkspacePage
            title="Simulador e importaciones"
            description="Reproduce decisiones con datos sintéticos y un reloj controlado. Cada ejecución conserva su propio historial."
            actions={
                <Button
                    disabled={load.processing}
                    onClick={() => load.post('/imports/kit')}
                >
                    <FlaskConical />
                    {load.processing ? 'Cargando…' : 'Nueva ejecución del kit'}
                </Button>
            }
        >
            <Alert>
                <AlertTitle>
                    Dos momentos de conocimiento, un mismo reloj
                </AlertTitle>
                <AlertDescription>
                    1. Carga el kit inicial y evalúa. 2. Abre P06 para ver la
                    pausa R4. 3. Incorpora la actualización y vuelve a evaluar:
                    la nueva pregunta cambia el contexto. El reloj oficial
                    permanece en 9 de octubre de 2026, 16:00 UTC.
                </AlertDescription>
            </Alert>
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <section className="space-y-4">
                    {kits.length ? (
                        kits.map((s) => (
                            <Card key={s.id} className="shadow-none">
                                <CardHeader>
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <CardTitle className="text-base">
                                            {s.name}
                                        </CardTitle>
                                        <Badge
                                            variant={
                                                s.integrity_blocked
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {s.integrity_blocked
                                                ? 'Historial en revisión'
                                                : 'Kit sintético'}
                                        </Badge>
                                    </div>
                                    <CardDescription>
                                        Conjunto #{s.id} · {s.evaluation_at}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                '/imports/' +
                                                    s.id +
                                                    '/evaluate',
                                            )
                                        }
                                    >
                                        <Play />
                                        Evaluar casos
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                '/imports/' + s.id + '/update',
                                            )
                                        }
                                    >
                                        <RefreshCw />
                                        Incorporar P06-e3
                                    </Button>
                                    <Button size="sm" variant="outline" asChild>
                                        <a
                                            href={
                                                '/exports/' +
                                                s.id +
                                                '/recommendations'
                                            }
                                        >
                                            <Download />
                                            Exportar JSON
                                        </a>
                                    </Button>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href="/cases?mode=kit">
                                            Ver bandeja
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        <EmptyState
                            title="Comienza una ejecución reproducible"
                            description="El kit incluye seis casos. Su carga es gratuita; las evaluaciones usan las reglas y, si está configurada, la IA con presupuesto limitado."
                        />
                    )}
                </section>
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Importar eventos JSONL
                        </CardTitle>
                        <CardDescription>
                            Perfil oficial. Máximo 2 MB y 2,000 eventos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/imports', {
                                    forceFormData: true,
                                    preserveScroll: true,
                                    onSuccess: () => form.reset('file'),
                                });
                            }}
                        >
                            <FormErrors errors={form.errors} />
                            <Field
                                label="Conjunto de pruebas"
                                htmlFor="data-set"
                            >
                                <Select
                                    value={form.data.data_set_id}
                                    onValueChange={(v) =>
                                        form.setData('data_set_id', v)
                                    }
                                >
                                    <SelectTrigger id="data-set">
                                        <SelectValue placeholder="Primero crea una ejecución" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {kits.map((s) => (
                                            <SelectItem
                                                key={s.id}
                                                value={String(s.id)}
                                            >
                                                {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="Archivo" htmlFor="jsonl">
                                <Input
                                    id="jsonl"
                                    type="file"
                                    accept=".jsonl,.ndjson,.txt"
                                    required
                                    onChange={(e) =>
                                        form.setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </Field>
                            <Button
                                disabled={
                                    form.processing ||
                                    !form.data.file ||
                                    !form.data.data_set_id
                                }
                                className="w-full"
                            >
                                <Upload />
                                {form.processing
                                    ? 'Procesando…'
                                    : 'Procesar archivo'}
                            </Button>
                        </form>
                        <p className="mt-4 text-xs leading-relaxed text-muted-foreground">
                            Recargar el mismo archivo no duplica eventos. Un ID
                            con otro contenido bloquea el caso y conserva el
                            original.
                        </p>
                    </CardContent>
                </Card>
            </div>
            <section className="space-y-4">
                <h2 className="text-lg font-semibold">
                    Registro de importaciones
                </h2>
                {batches.length ? (
                    <div className="overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Lote</TableHead>
                                    <TableHead>Aceptados</TableHead>
                                    <TableHead>Duplicados</TableHead>
                                    <TableHead>Errores</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {batches.map((b) => (
                                    <TableRow key={b.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {b.name}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Conjunto #{b.data_set_id} ·{' '}
                                                {date(b.created_at)}
                                            </p>
                                        </TableCell>
                                        <TableCell>{b.accepted}</TableCell>
                                        <TableCell>{b.duplicates}</TableCell>
                                        <TableCell>
                                            {b.errors.length === 0 ? (
                                                <span className="text-muted-foreground">
                                                    Sin errores
                                                </span>
                                            ) : (
                                                <ul className="max-w-lg space-y-2 text-xs text-destructive">
                                                    {b.errors.map((e, i) => (
                                                        <li key={i}>
                                                            Línea {e.line}
                                                            {e.case_id
                                                                ? ' · ' +
                                                                  e.case_id
                                                                : ''}
                                                            : {e.message}
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Todavía no se han importado archivos.
                    </p>
                )}
            </section>
        </WorkspacePage>
    );
}
Imports.layout = {
    breadcrumbs: [{ title: 'Simulador e importaciones', href: '/imports' }],
};
