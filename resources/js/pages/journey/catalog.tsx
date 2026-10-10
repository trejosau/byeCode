import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Journey,
    inputClass,
    type Style,
    type Variant,
} from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
import { track, useActivity } from '@/hooks/use-activity';

export default function Catalog({
    styles,
    variants,
    selection,
}: {
    styles: Style[];
    variants: Variant[];
    selection: { product_id: number; quantity: number }[];
}) {
    const [query, setQuery] = useState('');
    const [fit, setFit] = useState('');
    const [color, setColor] = useState('');
    const [size, setSize] = useState('');
    const [quantities, setQuantities] = useState<Record<number, number>>(
        Object.fromEntries(selection.map((l) => [l.product_id, l.quantity])),
    );
    const activity = useActivity();
    const lines = Object.entries(quantities)
        .filter(([, q]) => q > 0)
        .map(([id, quantity]) => ({ product_id: Number(id), quantity }));
    const visibleVariants = variants.filter(
        (v) =>
            (!fit || v.fit === fit) &&
            (!color || v.color === color) &&
            (!size || v.size === size),
    );
    return (
        <Journey
            title="Blanks para tu siguiente proyecto"
            description="Explora materiales, cortes y combinaciones. Elige tus cantidades y recibe una cotización preparada por nuestro equipo."
        >
            <div className="flex flex-wrap items-center gap-3 rounded-lg border p-4">
                <p className="mr-auto text-sm text-muted-foreground">
                    Puedes permitir analítica de navegación para mejorar tu
                    experiencia. No se registran contraseñas ni teclas.
                </p>
                <Button
                    variant={activity.consent ? 'default' : 'outline'}
                    onClick={() => activity.choose(true)}
                >
                    Permitir
                </Button>
                <Button variant="ghost" onClick={() => activity.choose(false)}>
                    Desactivar
                </Button>
            </div>
            <div className="grid gap-3 md:grid-cols-4">
                <label>
                    Buscar
                    <input
                        className={inputClass}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onBlur={() => track('catalog.search', { query })}
                        placeholder="Playera, hoodie…"
                    />
                </label>
                {[
                    ['Fit', fit, setFit, 'fit'],
                    ['Color', color, setColor, 'color'],
                    ['Talla', size, setSize, 'size'],
                ].map(([label, value, setter, key]) => (
                    <label key={String(label)}>
                        {String(label)}
                        <select
                            className={inputClass}
                            value={String(value)}
                            onChange={(e) => {
                                (setter as (v: string) => void)(e.target.value);
                                track('catalog.filter', {
                                    filter:
                                        String(label) + ':' + e.target.value,
                                });
                            }}
                        >
                            <option value="">Todos</option>
                            {[
                                ...new Set(
                                    variants.map((v) =>
                                        String(
                                            v[key as 'fit' | 'color' | 'size'],
                                        ),
                                    ),
                                ),
                            ]
                                .sort()
                                .map((v) => (
                                    <option key={v}>{v}</option>
                                ))}
                        </select>
                    </label>
                ))}
            </div>
            {!styles.length && (
                <div className="rounded-xl border p-10 text-center text-muted-foreground">
                    El equipo está preparando el catálogo. Las fichas aparecerán
                    aquí cuando se publiquen.
                </div>
            )}
            <div className="space-y-8">
                {styles
                    .filter((s) =>
                        s.name.toLowerCase().includes(query.toLowerCase()),
                    )
                    .map((style) => {
                        const items = visibleVariants.filter(
                            (v) => v.style_id === style.id,
                        );
                        if (!items.length) return null;
                        return (
                            <article
                                key={style.id}
                                data-section={'product-' + style.id}
                                className="overflow-hidden rounded-xl border"
                            >
                                <div className="grid gap-6 p-6 md:grid-cols-[1fr_2fr]">
                                    <div>
                                        {style.details.photos[0] ? (
                                            <img
                                                src={style.details.photos[0]}
                                                alt={style.name}
                                                className="aspect-square w-full rounded-lg object-cover"
                                            />
                                        ) : (
                                            <div className="flex aspect-square items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                Fotografía pendiente
                                            </div>
                                        )}
                                        {style.details.photos.length > 1 && (
                                            <div className="mt-3 flex gap-2 overflow-auto">
                                                {style.details.photos
                                                    .slice(1)
                                                    .map((url) => (
                                                        <img
                                                            key={url}
                                                            src={url}
                                                            alt={style.name}
                                                            className="h-24 w-24 rounded object-cover"
                                                        />
                                                    ))}
                                            </div>
                                        )}
                                    </div>
                                    <div className="space-y-4">
                                        <p className="text-sm text-muted-foreground">
                                            {style.category}
                                        </p>
                                        <h2 className="text-2xl font-semibold">
                                            {style.name}
                                        </h2>
                                        <p className="leading-7 whitespace-pre-wrap">
                                            {style.details.description}
                                        </p>
                                        {Object.entries({
                                            'Tejido y construcción': [
                                                style.details.fabric,
                                                style.details.construction,
                                            ]
                                                .filter(Boolean)
                                                .join('\n'),
                                            'Guía de fits':
                                                style.details.fit_guide,
                                            'Guía de tallas y medidas':
                                                style.details.size_guide,
                                            Cuidados: style.details.care,
                                        }).map(([label, value]) => (
                                            <details
                                                key={label}
                                                className="border-t py-3"
                                                onToggle={(e) => {
                                                    if (e.currentTarget.open)
                                                        track(
                                                            'ui.section_expanded',
                                                            { section: label },
                                                        );
                                                }}
                                            >
                                                <summary className="cursor-pointer font-medium">
                                                    {label}
                                                </summary>
                                                <p className="mt-3 whitespace-pre-wrap text-muted-foreground">
                                                    {value ||
                                                        'Consulta con comercio para confirmar esta información.'}
                                                </p>
                                            </details>
                                        ))}
                                    </div>
                                </div>
                                <div className="overflow-auto border-t">
                                    <table className="w-full text-left text-sm">
                                        <thead className="bg-muted">
                                            <tr>
                                                {[
                                                    'SKU / Fit',
                                                    'Color',
                                                    'Talla / medidas',
                                                    'Material',
                                                    'Disponibilidad',
                                                    'Cantidad',
                                                ].map((s) => (
                                                    <th className="p-4" key={s}>
                                                        {s}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {items.map((v) => (
                                                <tr
                                                    className="border-t"
                                                    key={v.id}
                                                >
                                                    <td className="p-4">
                                                        {v.sku}
                                                        <br />
                                                        <strong>{v.fit}</strong>
                                                    </td>
                                                    <td className="p-4">
                                                        {v.color}
                                                        <br />
                                                        {v.details?.color_code}
                                                    </td>
                                                    <td className="p-4">
                                                        {v.size}
                                                        <p>
                                                            {
                                                                v.details
                                                                    ?.measurements
                                                            }
                                                        </p>
                                                        <p>
                                                            {
                                                                v.details
                                                                    ?.tolerance
                                                            }
                                                        </p>
                                                    </td>
                                                    <td className="p-4">
                                                        {v.gsm
                                                            ? v.gsm + ' GSM'
                                                            : 'GSM por confirmar'}
                                                        <br />
                                                        {v.composition ||
                                                            'Composición por confirmar'}
                                                    </td>
                                                    <td className="p-4">
                                                        {v.details
                                                            ?.availability ||
                                                            'A confirmar'}
                                                        <br />
                                                        {v.moq
                                                            ? 'Mínimo: ' + v.moq
                                                            : 'Mínimo por confirmar'}
                                                    </td>
                                                    <td className="p-4">
                                                        <input
                                                            aria-label={
                                                                'Cantidad ' +
                                                                v.sku
                                                            }
                                                            className={
                                                                inputClass +
                                                                ' min-w-24'
                                                            }
                                                            type="number"
                                                            min="0"
                                                            max="1000000"
                                                            value={
                                                                quantities[
                                                                    v.id
                                                                ] || ''
                                                            }
                                                            onChange={(e) =>
                                                                setQuantities({
                                                                    ...quantities,
                                                                    [v.id]: Math.max(
                                                                        0,
                                                                        Math.floor(
                                                                            Number(
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            ),
                                                                        ),
                                                                    ),
                                                                })
                                                            }
                                                            onBlur={() =>
                                                                track(
                                                                    'catalog.quantity_changed',
                                                                    {
                                                                        product_id:
                                                                            v.id,
                                                                        quantity:
                                                                            quantities[
                                                                                v
                                                                                    .id
                                                                            ] ||
                                                                            0,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        );
                    })}
            </div>
            <div className="sticky bottom-3 flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-background p-5 shadow-sm">
                <div>
                    <strong>
                        {lines.reduce((sum, l) => sum + l.quantity, 0)} unidades
                        seleccionadas
                    </strong>
                    <p className="text-sm text-muted-foreground">
                        {lines.length} variantes · El precio se determina al
                        cotizar.
                    </p>
                </div>
                <Button
                    disabled={!lines.length || lines.length > 30}
                    onClick={() => router.post('/selection', { lines })}
                >
                    Solicitar cotización
                </Button>
            </div>
        </Journey>
    );
}
