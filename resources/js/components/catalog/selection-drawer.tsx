import { ShoppingBag } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
    DialogClose,
} from '@/components/ui/dialog';
import type { Variant } from '@/components/byecode/journey';
import type { SelectionLine } from './product-configurator';
import { SizeQuantityInput } from './size-quantity-input';
export function SelectionDrawer({
    lines,
    variants,
    busy,
    error,
    update,
    submit,
}: {
    lines: SelectionLine[];
    variants: Variant[];
    busy: boolean;
    error: string;
    update: (lines: SelectionLine[]) => void;
    submit: () => void;
}) {
    const groups = new Map<
        string,
        { product: Variant; lines: SelectionLine[] }
    >();
    for (const line of lines) {
        const product = variants.find((v) => v.id === line.product_id);
        if (!product) continue;
        const key = `${product.style_id}|${product.fit}|${product.composition}|${product.gsm}|${product.color}`;
        const group = groups.get(key) ?? { product, lines: [] };
        group.lines.push(line);
        groups.set(key, group);
    }
    return (
        <Dialog>
            <div className="fixed inset-x-0 bottom-0 z-30 border-t bg-background px-5 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-3">
                    <p className="text-sm">
                        <ShoppingBag className="mr-2 inline size-4" />
                        {
                            [...groups.values()].filter((group) =>
                                group.lines.some((line) => line.quantity > 0),
                            ).length
                        }{' '}
                        configuraciones ·{' '}
                        {lines.reduce((sum, l) => sum + l.quantity, 0)} prendas
                    </p>
                    <DialogTrigger asChild>
                        <Button>Ver selección</Button>
                    </DialogTrigger>
                </div>
            </div>
            <DialogContent className="inset-x-0 top-auto bottom-0 mx-auto max-h-[85dvh] w-full max-w-none translate-x-0 translate-y-0 overflow-y-auto rounded-b-none p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:max-w-3xl">
                <DialogTitle>Mi selección</DialogTitle>
                <DialogDescription>
                    Distribuye cantidades por talla y solicita tu cotización.
                </DialogDescription>
                {!lines.length && (
                    <p className="py-8 text-muted-foreground">
                        Agrega una prenda del catálogo para comenzar.
                    </p>
                )}
                {lines
                    .filter(
                        (line) =>
                            !variants.some((v) => v.id === line.product_id),
                    )
                    .map((line) => (
                        <div
                            key={line.product_id}
                            className="flex items-center justify-between gap-3 border-t py-4"
                        >
                            <p>
                                Una prenda de tu selección ya no está publicada.
                            </p>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    update(
                                        lines.filter(
                                            (l) =>
                                                l.product_id !==
                                                line.product_id,
                                        ),
                                    )
                                }
                            >
                                Retirar prenda
                            </Button>
                        </div>
                    ))}
                {[...groups.entries()].map(
                    ([key, { product, lines: group }]) => (
                        <section key={key} className="flex gap-4 border-t py-4">
                            {product.details?.images?.[0] && (
                                <img
                                    src={product.details.images[0].path}
                                    alt={`${product.name} · ${product.color}`}
                                    className="h-24 w-20 rounded-md bg-[#e7e7e4] object-contain"
                                />
                            )}
                            <div className="min-w-0 flex-1">
                                <h3 className="font-medium">{product.name}</h3>
                                <p className="text-sm text-muted-foreground">
                                    {product.gsm} g/m² · {product.color}
                                </p>
                                <div className="mt-3 grid grid-cols-3 gap-3">
                                    {group.map((line) => (
                                        <SizeQuantityInput
                                            key={line.product_id}
                                            size={
                                                variants.find(
                                                    (v) =>
                                                        v.id ===
                                                        line.product_id,
                                                )?.size ?? ''
                                            }
                                            value={line.quantity}
                                            onChange={(quantity) =>
                                                update(
                                                    lines.map((l) =>
                                                        l.product_id ===
                                                        line.product_id
                                                            ? { ...l, quantity }
                                                            : l,
                                                    ),
                                                )
                                            }
                                        />
                                    ))}
                                </div>
                                <Button
                                    variant="ghost"
                                    disabled={busy}
                                    onClick={() =>
                                        update(
                                            lines.filter(
                                                (l) =>
                                                    !group.some(
                                                        (g) =>
                                                            g.product_id ===
                                                            l.product_id,
                                                    ),
                                            ),
                                        )
                                    }
                                >
                                    Eliminar configuración
                                </Button>
                            </div>
                        </section>
                    ),
                )}
                {error && (
                    <p role="alert" className="text-destructive">
                        {error}
                    </p>
                )}
                <p role="status" className="text-sm text-muted-foreground">
                    {busy
                        ? 'Guardando selección…'
                        : 'La selección se conserva al navegar.'}
                </p>
                <div className="flex flex-wrap justify-between gap-3">
                    <DialogClose asChild>
                        <Button variant="outline">Seguir configurando</Button>
                    </DialogClose>
                    <Button
                        disabled={
                            busy ||
                            !lines.some((l) => l.quantity > 0) ||
                            !!error
                        }
                        onClick={submit}
                    >
                        Solicitar cotización
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
