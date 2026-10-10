import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { Printer, Send, CheckCircle2, Shirt } from 'lucide-react';
import AppWordmark from '@/components/app-wordmark';
import { Field, FormErrors } from '@/components/byecode/workspace-page';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import { date, money, statuses } from '@/lib/byecode';
import type { Quote } from '@/types/byecode';

export default function Portal({
    token,
    case: c,
    company,
    quote,
}: {
    token: string;
    case: { external_id: string; title: string; status: string };
    company: string | null;
    quote: Quote;
}) {
    const sent = useRef(false);
    const form = useForm({ message: '', event_id: crypto.randomUUID() });
    const success = usePage().props.success;
    useEffect(() => {
        const record = () => {
            if (sent.current || document.visibilityState !== 'visible') return;
            sent.current = true;
            router.post(
                '/portal/' + token + '/views',
                { event_id: crypto.randomUUID(), section: 'summary' },
                { preserveScroll: true, preserveState: true },
            );
        };
        record();
        document.addEventListener('visibilitychange', record);
        return () => document.removeEventListener('visibilitychange', record);
    }, [token]);
    return (
        <>
            <Head title={'Cotización ' + c.external_id} />
            <div className="min-h-screen bg-muted/30">
                <header className="border-b bg-background px-5 py-5">
                    <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-3">
                            <Shirt className="size-6" />
                            <span className="text-2xl font-normal tracking-tight">
                                <AppWordmark /> / BLANKS
                            </span>
                        </div>
                        <Badge variant="outline">
                            Portal privado · {company ?? 'Cotización'}
                        </Badge>
                    </div>
                </header>
                <main className="mx-auto max-w-6xl space-y-7 px-5 py-8 md:py-12">
                    {typeof success === 'string' && (
                        <Alert role="status">
                            <CheckCircle2 className="size-4" />
                            <AlertDescription>{success}</AlertDescription>
                        </Alert>
                    )}
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                                {c.title}
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {c.external_id} · Versión {quote.version} ·{' '}
                                {statuses[c.status]}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            className="print:hidden"
                            onClick={() => window.print()}
                        >
                            <Printer />
                            Imprimir cotización
                        </Button>
                    </div>
                    {quote.expired && (
                        <Alert>
                            <AlertDescription>
                                La vigencia de esta propuesta terminó. El equipo
                                debe revalidar precios y condiciones antes de
                                formalizar.
                            </AlertDescription>
                        </Alert>
                    )}
                    <div className="grid items-start gap-7 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div className="space-y-6">
                            <div className="overflow-hidden rounded-xl border bg-card">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Producto</TableHead>
                                            <TableHead className="text-right">
                                                Cantidad
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Precio MXN
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Importe
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {quote.lines.map((l) => (
                                            <TableRow key={l.line_id}>
                                                <TableCell>
                                                    <p className="font-semibold">
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
                            </div>
                            <Card className="shadow-none">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Condiciones de la propuesta
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                        {quote.terms ??
                                            'Consulta las condiciones con el equipo comercial.'}
                                    </p>
                                    <p className="mt-5 text-xs text-muted-foreground">
                                        Vigencia: {date(quote.valid_until)} ·
                                        Ciudad de México. La consulta y los
                                        mensajes no confirman existencias,
                                        reservas ni pedidos.
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="shadow-none print:hidden">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Consulta o solicita un cambio
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        className="space-y-4"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            form.post(
                                                '/portal/' +
                                                    token +
                                                    '/messages',
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () => {
                                                        form.reset('message');
                                                        form.setData(
                                                            'event_id',
                                                            crypto.randomUUID(),
                                                        );
                                                    },
                                                },
                                            );
                                        }}
                                    >
                                        <FormErrors errors={form.errors} />
                                        <Field
                                            label="Tu mensaje"
                                            htmlFor="buyer-message"
                                        >
                                            <Textarea
                                                id="buyer-message"
                                                required
                                                minLength={2}
                                                maxLength={8000}
                                                value={form.data.message}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'message',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Cuéntanos qué necesitas revisar: cantidades, tallas, condiciones…"
                                            />
                                        </Field>
                                        <Button disabled={form.processing}>
                                            <Send />
                                            {form.processing
                                                ? 'Registrando…'
                                                : 'Enviar al equipo'}
                                        </Button>
                                        <p className="text-xs text-muted-foreground">
                                            Si expresas una aceptación, el
                                            equipo verificará su alcance y las
                                            condiciones antes de formalizar.
                                        </p>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                        <Card className="shadow-none lg:sticky lg:top-6">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Resumen de la propuesta
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {[
                                    {
                                        label: 'Subtotal',
                                        value: quote.subtotal_cents,
                                    },
                                    {
                                        label: 'Impuestos',
                                        value: quote.tax_cents,
                                    },
                                    {
                                        label: 'Envío y cargos',
                                        value: quote.shipping_cents,
                                    },
                                    {
                                        label: 'Descuento adicional',
                                        value: -quote.discount_cents,
                                    },
                                ].map((x) => (
                                    <div
                                        key={x.label}
                                        className="flex justify-between gap-3 text-sm"
                                    >
                                        <span className="text-muted-foreground">
                                            {x.label}
                                        </span>
                                        <span>{money(x.value)}</span>
                                    </div>
                                ))}
                                <div className="border-t pt-5">
                                    <p className="text-xs text-muted-foreground">
                                        Total · MXN
                                    </p>
                                    <p className="mt-2 text-3xl font-semibold tracking-tight">
                                        {money(quote.total_cents)}
                                    </p>
                                </div>
                                <Badge variant="outline">
                                    {quote.expired
                                        ? 'Revalidación necesaria'
                                        : 'Propuesta vigente'}
                                </Badge>
                                <p className="text-xs leading-relaxed text-muted-foreground">
                                    Este enlace permite consultar la propuesta y
                                    registrar mensajes. La formalización
                                    requiere confirmación del equipo comercial.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                    <footer className="border-t pt-5 text-xs leading-relaxed text-muted-foreground">
                        Acceso privado por enlace. Se registra la apertura
                        visible de la propuesta y los mensajes que envíes para
                        dar seguimiento al caso.
                    </footer>
                </main>
            </div>
        </>
    );
}
