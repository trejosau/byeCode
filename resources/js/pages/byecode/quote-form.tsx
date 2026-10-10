import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2, Save } from 'lucide-react';
import {
    WorkspacePage,
    Field,
    FormErrors,
} from '@/components/byecode/workspace-page';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import { money } from '@/lib/byecode';
import type { Customer, Product, Quote } from '@/types/byecode';

type Line = {
    product_id: string;
    quantity: string;
    line_total: string;
};
export default function QuoteForm({
    case: current,
    quote,
    customers,
    products,
    requestLines = [],
    requestMessage,
    draft,
}: {
    case: {
        id: number;
        public_id: string;
        title: string;
        revision: number;
        customer_id: number;
    } | null;
    quote: Quote | null;
    customers: Customer[];
    products: Product[];
    draft?: { payload: string; revision: number } | null;
    requestLines?: { product_id: number; quantity: number }[];
    requestMessage?: string | null;
}) {
    const blank: Line = {
        product_id: '',
        quantity: '1',
        line_total: '',
    };
    const stored = (draft ? JSON.parse(draft.payload) : {}) as Partial<{
        title: string;
        valid_until: string;
        terms: string;
        authorization_reference: string;
        tax: string;
        shipping: string;
        discount: string;
        lines: (Line & { unit_price?: string })[];
    }>;
    const form = useForm({
        draft_revision: draft?.revision ?? 0,
        title: stored.title ?? current?.title ?? '',
        customer_id: String(current?.customer_id ?? ''),
        expected_revision: current?.revision ?? 0,
        valid_until:
            stored.valid_until ??
            quote?.valid_until.slice(0, 10) ??
            new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10),
        terms: stored.terms ?? quote?.terms ?? '',
        authorization_reference: stored.authorization_reference ?? '',
        tax: stored.tax ?? String((quote?.tax_cents ?? 0) / 100),
        shipping: stored.shipping ?? String((quote?.shipping_cents ?? 0) / 100),
        discount: stored.discount ?? String((quote?.discount_cents ?? 0) / 100),
        lines:
            stored.lines?.map((l) => ({
                product_id: l.product_id,
                quantity: l.quantity,
                line_total:
                    l.line_total ??
                    (l.unit_price
                        ? (Number(l.unit_price) * Number(l.quantity)).toFixed(2)
                        : ''),
            })) ??
            quote?.lines.map((l) => ({
                product_id: String(l.product_id),
                quantity: String(l.quantity),
                line_total: String(l.total_cents / 100),
            })) ??
            (requestLines.length
                ? requestLines.map((l) => ({
                      ...blank,
                      product_id: String(l.product_id),
                      quantity: String(l.quantity),
                  }))
                : [{ ...blank }]),
    });
    const saveDraft = () => {
        if (current)
            form.post('/cases/' + current.id + '/draft', {
                preserveScroll: true,
                onSuccess: () =>
                    form.setData(
                        'draft_revision',
                        form.data.draft_revision + 1,
                    ),
            });
    };
    const setLine = (i: number, key: keyof Line, value: string) =>
        form.setData(
            'lines',
            form.data.lines.map((l, n) => {
                if (i !== n) return l;
                const updated = { ...l, [key]: value };
                if (key === 'product_id') updated.line_total = '';
                return updated;
            }),
        );
    const subtotal = form.data.lines.reduce(
        (sum, l) => sum + Math.round((Number(l.line_total) || 0) * 100),
        0,
    );
    const total =
        subtotal +
        Math.round(Number(form.data.tax) * 100) +
        Math.round(Number(form.data.shipping) * 100) -
        Math.round(Number(form.data.discount) * 100);
    return (
        <WorkspacePage
            title={quote ? 'Actualizar cotización' : 'Preparar cotización'}
            description="Pon el precio total de cada detalle. La suma es el total de la cotización."
        >
            {(!customers.length || !products.length) && (
                <Alert>
                    <AlertDescription>
                        Para un cliente nuevo, captura{' '}
                        <Link className="underline" href="/physical-request">
                            su solicitud presencial
                        </Link>{' '}
                        y verifica{' '}
                        <Link className="underline" href="/products">
                            una variante de producto
                        </Link>
                        .
                    </AlertDescription>
                </Alert>
            )}
            <form
                className="space-y-7"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(
                        current ? '/cases/' + current.id + '/quote' : '/quotes',
                    );
                }}
            >
                <FormErrors errors={form.errors} />
                {requestMessage && (
                    <section className="rounded-lg border bg-muted/40 p-4">
                        <h2 className="mb-2 font-medium">
                            Lo que necesita el cliente
                        </h2>
                        <p className="text-sm whitespace-pre-wrap">
                            {requestMessage}
                        </p>
                    </section>
                )}
                <details className="rounded-lg border p-4" open={!current}>
                    <summary className="cursor-pointer text-sm">
                        Datos de la cotización y vigencia
                    </summary>
                    <div className="grid gap-5 lg:grid-cols-3">
                        <Field label="Título de la oportunidad" htmlFor="title">
                            <Input
                                id="title"
                                required
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                                placeholder="Lote de blanks para…"
                            />
                        </Field>
                        <Field label="Empresa compradora" htmlFor="customer">
                            <Select
                                disabled={!!current}
                                value={form.data.customer_id}
                                onValueChange={(v) =>
                                    form.setData('customer_id', v)
                                }
                            >
                                <SelectTrigger id="customer">
                                    <SelectValue placeholder="Selecciona un cliente" />
                                </SelectTrigger>
                                <SelectContent>
                                    {customers.map((c) => (
                                        <SelectItem
                                            key={c.id}
                                            value={String(c.id)}
                                        >
                                            {c.company}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field
                            label="Vigente hasta"
                            htmlFor="valid_until"
                            hint="Final del día en Ciudad de México."
                        >
                            <Input
                                id="valid_until"
                                type="date"
                                required
                                value={form.data.valid_until}
                                onChange={(e) =>
                                    form.setData('valid_until', e.target.value)
                                }
                            />
                        </Field>
                    </div>
                </details>
                {current && form.data.lines.every((line) => line.product_id) ? (
                    <section className="space-y-4">
                        <h2 className="text-lg font-semibold">
                            Detalles de la cotización
                        </h2>
                        {form.data.lines.map((line, index) => {
                            const product = products.find(
                                (item) => String(item.id) === line.product_id,
                            );
                            return (
                                <div
                                    key={line.product_id}
                                    className="grid items-center gap-4 rounded-lg border p-5 sm:grid-cols-[1fr_240px]"
                                >
                                    <div>
                                        <h3 className="font-medium">
                                            {line.quantity} ×{' '}
                                            {product?.name ?? 'Producto'}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {product?.color} · Talla{' '}
                                            {product?.size}
                                        </p>
                                    </div>
                                    <Field
                                        label="Precio total del detalle (MXN)"
                                        htmlFor={'price-' + index}
                                    >
                                        <Input
                                            id={'price-' + index}
                                            type="number"
                                            inputMode="decimal"
                                            required
                                            min="0"
                                            max="999999999"
                                            step="0.01"
                                            placeholder="0.00"
                                            value={line.line_total}
                                            onChange={(event) =>
                                                setLine(
                                                    index,
                                                    'line_total',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                            );
                        })}
                    </section>
                ) : (
                    <section className="space-y-4">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">
                                Líneas de producto
                            </h2>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={form.data.lines.length >= 100}
                                onClick={() =>
                                    form.setData('lines', [
                                        ...form.data.lines,
                                        { ...blank },
                                    ])
                                }
                            >
                                <Plus />
                                Agregar línea
                            </Button>
                        </div>
                        {form.data.lines.map((l, i) => (
                            <Card key={i} className="shadow-none">
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-medium">
                                            Línea {i + 1}
                                        </p>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={
                                                'Eliminar línea ' + (i + 1)
                                            }
                                            disabled={
                                                form.data.lines.length === 1
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'lines',
                                                    form.data.lines.filter(
                                                        (_, n) => n !== i,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        <Field
                                            label="Prenda"
                                            htmlFor={'product-' + i}
                                        >
                                            <details className="rounded-md border p-3">
                                                <summary
                                                    id={'product-' + i}
                                                    className="cursor-pointer text-sm"
                                                >
                                                    {(() => {
                                                        const p = products.find(
                                                            (p) =>
                                                                String(p.id) ===
                                                                l.product_id,
                                                        );
                                                        return p
                                                            ? `${p.name} · ${p.color} · ${p.size} · ${p.gsm} g/m²`
                                                            : 'Elegir prenda';
                                                    })()}
                                                </summary>
                                                <div className="mt-3 grid max-h-64 gap-2 overflow-y-auto">
                                                    {products.map((p) => (
                                                        <Button
                                                            key={p.id}
                                                            type="button"
                                                            variant={
                                                                String(p.id) ===
                                                                l.product_id
                                                                    ? 'secondary'
                                                                    : 'ghost'
                                                            }
                                                            className="h-auto justify-start text-left whitespace-normal"
                                                            onClick={(
                                                                event,
                                                            ) => {
                                                                setLine(
                                                                    i,
                                                                    'product_id',
                                                                    String(
                                                                        p.id,
                                                                    ),
                                                                );
                                                                event.currentTarget
                                                                    .closest(
                                                                        'details',
                                                                    )
                                                                    ?.removeAttribute(
                                                                        'open',
                                                                    );
                                                            }}
                                                        >
                                                            {p.name} · {p.color}{' '}
                                                            · {p.size} · {p.gsm}{' '}
                                                            g/m²
                                                        </Button>
                                                    ))}
                                                </div>
                                            </details>
                                        </Field>
                                        <Field
                                            label="Cantidad"
                                            htmlFor={'qty-' + i}
                                        >
                                            <Input
                                                id={'qty-' + i}
                                                type="number"
                                                required
                                                min="1"
                                                step="1"
                                                value={l.quantity}
                                                onChange={(e) =>
                                                    setLine(
                                                        i,
                                                        'quantity',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Total de este producto (MXN)"
                                            htmlFor={'price-' + i}
                                        >
                                            <Input
                                                id={'price-' + i}
                                                type="number"
                                                required
                                                min="0"
                                                step="0.01"
                                                value={l.line_total}
                                                onChange={(e) =>
                                                    setLine(
                                                        i,
                                                        'line_total',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                    <p
                                        className="text-sm text-muted-foreground"
                                        aria-live="polite"
                                    >
                                        {l.line_total !== '' &&
                                        Number(l.quantity) > 0
                                            ? `${l.quantity} piezas · ${money(Math.round(Number(l.line_total) * 100) / Number(l.quantity))} por pieza${Math.round(Number(l.line_total) * 100) % Number(l.quantity) ? ' aprox.' : ''} · Total ${money(Math.round(Number(l.line_total) * 100))}`
                                            : 'Escribe el total para calcular el precio por pieza.'}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                )}
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="space-y-5">
                        <Field
                            label="Texto para el cliente (opcional)"
                            htmlFor="terms"
                            hint="Añade cualquier comentario que quieras incluir en la cotización."
                        >
                            <Textarea
                                id="terms"
                                value={form.data.terms}
                                onChange={(e) =>
                                    form.setData('terms', e.target.value)
                                }
                                className="min-h-32"
                            />
                        </Field>
                    </div>
                    <Card className="shadow-none">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Importes · MXN
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex justify-between text-sm">
                                <span>Subtotal</span>
                                <span>{money(subtotal)}</span>
                            </div>
                            {(Number(form.data.tax) !== 0 ||
                                Number(form.data.shipping) !== 0 ||
                                Number(form.data.discount) !== 0) && (
                                <div className="space-y-2 text-sm text-muted-foreground">
                                    <p>
                                        Ajustes conservados de la cotización
                                        anterior:
                                    </p>
                                    <p>
                                        Impuestos:{' '}
                                        {money(
                                            Math.round(
                                                Number(form.data.tax) * 100,
                                            ),
                                        )}
                                    </p>
                                    <p>
                                        Envío:{' '}
                                        {money(
                                            Math.round(
                                                Number(form.data.shipping) *
                                                    100,
                                            ),
                                        )}
                                    </p>
                                    <p>
                                        Descuento:{' '}
                                        {money(
                                            Math.round(
                                                Number(form.data.discount) *
                                                    100,
                                            ),
                                        )}
                                    </p>
                                </div>
                            )}
                            <div className="flex justify-between border-t pt-4 font-semibold">
                                <span>Total de la cotización</span>
                                <span>{money(total)}</span>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Suma de los precios totales de cada detalle.
                            </p>
                        </CardContent>
                    </Card>
                </div>
                <div className="flex flex-wrap justify-end gap-3 border-t pt-5">
                    {current && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={saveDraft}
                            disabled={form.processing}
                        >
                            Guardar borrador
                        </Button>
                    )}
                    <Button type="button" variant="outline" asChild>
                        <Link
                            href={
                                current
                                    ? '/my/quotes/' + current.public_id
                                    : '/cases'
                            }
                        >
                            Cancelar
                        </Link>
                    </Button>
                    <Button
                        disabled={
                            form.processing ||
                            !customers.length ||
                            !products.length
                        }
                    >
                        <Save />
                        {form.processing
                            ? 'Enviando…'
                            : 'Enviar cotización al cliente'}
                    </Button>
                </div>
            </form>
        </WorkspacePage>
    );
}
QuoteForm.layout = {
    breadcrumbs: [
        { title: 'Cotizaciones', href: '/cases' },
        { title: 'Cotización', href: '/quotes/create' },
    ],
};
