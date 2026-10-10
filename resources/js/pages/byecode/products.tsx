import { useForm } from '@inertiajs/react';
import { Plus, Shirt } from 'lucide-react';
import {
    WorkspacePage,
    Field,
    FormErrors,
    EmptyState,
} from '@/components/byecode/workspace-page';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
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
import { money, date } from '@/lib/byecode';
import type { Product } from '@/types/byecode';

export default function Products({ products }: { products: Product[] }) {
    const form = useForm({
        sku: '',
        name: '',
        category: 'Playera',
        fit: '',
        gsm: '',
        composition: '',
        color: '',
        size: '',
        unit_price: '',
        moq: '',
        stock: '',
        source: '',
    });
    return (
        <WorkspacePage
            title="Catálogo de blanks"
            description="Prendas lisas, variantes y observaciones de inventario. Un valor vacío significa desconocido."
        >
            <div className="grid items-start gap-6 2xl:grid-cols-[minmax(0,1fr)_400px]">
                <section className="space-y-3">
                    {products.length ? (
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Prenda / SKU</TableHead>
                                        <TableHead>Variante</TableHead>
                                        <TableHead className="text-right">
                                            Precio base
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Stock / MOQ
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.map((p) => (
                                        <TableRow key={p.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2 font-semibold">
                                                    <Shirt className="size-4 text-muted-foreground" />
                                                    {p.name}
                                                </div>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {p.sku}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {p.category}
                                                </Badge>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    {[
                                                        p.fit,
                                                        p.gsm
                                                            ? p.gsm + ' GSM'
                                                            : null,
                                                        p.color,
                                                        p.size,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ') ||
                                                        'Especificación pendiente'}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-right whitespace-nowrap">
                                                {p.unit_price_cents === null
                                                    ? 'Por confirmar'
                                                    : money(p.unit_price_cents)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <p>
                                                    {p.stock ?? 'Sin verificar'}{' '}
                                                    / {p.moq ?? 'Sin definir'}
                                                </p>
                                                <p className="mt-1 text-[11px] text-muted-foreground">
                                                    {date(p.verified_at)}
                                                </p>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <EmptyState
                            title="Define tu catálogo real"
                            description="Agrega las prendas que puedes cotizar. Stock, mínimos y precios permanecen sin definir hasta que los registres."
                        />
                    )}
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        Registrar existencias no reserva mercancía ni confirma
                        capacidad de fabricación. Cada cotización conserva una
                        copia de sus líneas.
                    </p>
                </section>
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Agregar variante
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/products', {
                                    onSuccess: () => form.reset(),
                                });
                            }}
                        >
                            <FormErrors errors={form.errors} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="SKU" htmlFor="sku">
                                    <Input
                                        id="sku"
                                        required
                                        value={form.data.sku}
                                        onChange={(e) =>
                                            form.setData('sku', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field label="Nombre" htmlFor="name">
                                    <Input
                                        id="name"
                                        required
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                            <Field label="Categoría" htmlFor="category">
                                <Select
                                    value={form.data.category}
                                    onValueChange={(v) =>
                                        form.setData('category', v)
                                    }
                                >
                                    <SelectTrigger id="category">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[
                                            'Playera',
                                            'Hoodie',
                                            'Crewneck',
                                            'Long sleeve',
                                            'Pants',
                                            'Tank top',
                                            'Short',
                                        ].map((c) => (
                                            <SelectItem key={c} value={c}>
                                                {c}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {(
                                    [
                                        {
                                            key: 'fit',
                                            label: 'Fit / corte',
                                            type: 'text',
                                        },
                                        {
                                            key: 'gsm',
                                            label: 'Gramaje (GSM)',
                                            type: 'number',
                                        },
                                        {
                                            key: 'color',
                                            label: 'Color',
                                            type: 'text',
                                        },
                                        {
                                            key: 'size',
                                            label: 'Talla',
                                            type: 'text',
                                        },
                                        {
                                            key: 'unit_price',
                                            label: 'Precio base MXN',
                                            type: 'number',
                                        },
                                        {
                                            key: 'moq',
                                            label: 'Mínimo de pedido',
                                            type: 'number',
                                        },
                                        {
                                            key: 'stock',
                                            label: 'Existencia observada',
                                            type: 'number',
                                        },
                                    ] as const
                                ).map((f) => (
                                    <Field
                                        key={f.key}
                                        label={f.label}
                                        htmlFor={f.key}
                                    >
                                        <Input
                                            id={f.key}
                                            type={f.type}
                                            min="0"
                                            step={
                                                f.key === 'unit_price'
                                                    ? '0.01'
                                                    : '1'
                                            }
                                            value={form.data[f.key]}
                                            onChange={(e) =>
                                                form.setData(
                                                    f.key,
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Sin definir"
                                        />
                                    </Field>
                                ))}
                            </div>
                            <Field
                                label="Composición / tejido"
                                htmlFor="composition"
                            >
                                <Input
                                    id="composition"
                                    value={form.data.composition}
                                    onChange={(e) =>
                                        form.setData(
                                            'composition',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Fuente de la información"
                                htmlFor="source"
                                hint="Por ejemplo: lista de precios autorizada o reporte de almacén con fecha."
                            >
                                <Input
                                    id="source"
                                    required
                                    value={form.data.source}
                                    onChange={(e) =>
                                        form.setData('source', e.target.value)
                                    }
                                />
                            </Field>
                            <Button
                                disabled={form.processing}
                                className="w-full"
                            >
                                <Plus />
                                Guardar variante
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </WorkspacePage>
    );
}
Products.layout = {
    breadcrumbs: [{ title: 'Catálogo de blanks', href: '/products' }],
};
