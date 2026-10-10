import { useState } from 'react';
import type { Style, Variant } from '@/components/byecode/journey';
import { inputClass } from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
import { track } from '@/hooks/use-activity';
import { ColorSwatch } from './color-swatch';
import { ProductGallery } from './product-gallery';
import { SizeQuantityInput } from './size-quantity-input';
export type SelectionLine = { product_id: number; quantity: number };
export function ProductConfigurator({
    style,
    variants,
    busy,
    add,
}: {
    style: Style;
    variants: Variant[];
    busy: boolean;
    add: (lines: SelectionLine[]) => void;
}) {
    const [color, setColor] = useState(() => {
        const requested =
            typeof window === 'undefined'
                ? null
                : new URLSearchParams(window.location.search).get('color');
        return (
            variants.find((v) => v.color === requested)?.color ??
            variants[0]?.color ??
            ''
        );
    });
    const [material, setMaterial] = useState(
        `${variants[0]?.composition}|${variants[0]?.gsm}|${variants[0]?.fit}`,
    );
    const [quantities, setQuantities] = useState<Record<number, number>>({});
    const combinations = [
        ...new Set(variants.map((v) => `${v.composition}|${v.gsm}|${v.fit}`)),
    ];
    const current = variants.filter(
        (v) =>
            v.color === color &&
            `${v.composition}|${v.gsm}|${v.fit}` === material,
    );
    const product = current[0];
    const lines = current
        .filter((v) => quantities[v.id] > 0)
        .map((v) => ({ product_id: v.id, quantity: quantities[v.id] }));
    return (
        <div className="grid items-start gap-8 lg:grid-cols-2">
            <ProductGallery
                key={`${color}-${material}`}
                images={product?.details?.images ?? []}
                name={style.name}
            />
            <div className="space-y-7">
                <div>
                    <p className="text-sm text-muted-foreground">
                        {product?.fit}
                    </p>
                    <h2 className="mt-2 text-2xl font-semibold">
                        {style.name}
                    </h2>
                    <p className="mt-3 text-muted-foreground">
                        {style.details.description}
                    </p>
                </div>
                {combinations.length > 1 ? (
                    <label className="block">
                        Material y gramaje
                        <select
                            className={inputClass}
                            value={material}
                            onChange={(e) => {
                                setMaterial(e.target.value);
                                setQuantities({});
                            }}
                        >
                            {combinations.map((m) => (
                                <option key={m} value={m}>
                                    {m.split('|').join(' · ')}
                                </option>
                            ))}
                        </select>
                    </label>
                ) : (
                    <p>
                        {product?.composition} · {product?.gsm} g/m²
                    </p>
                )}
                <fieldset>
                    <legend className="mb-2 font-medium">
                        Color · {color}
                    </legend>
                    <div className="flex flex-wrap gap-2">
                        {[...new Set(variants.map((v) => v.color))].map(
                            (name) => (
                                <ColorSwatch
                                    key={name}
                                    name={name}
                                    hex={
                                        variants.find((v) => v.color === name)
                                            ?.details?.color_code
                                    }
                                    selected={name === color}
                                    onSelect={() => {
                                        setColor(name);
                                        setQuantities({});
                                    }}
                                />
                            ),
                        )}
                    </div>
                </fieldset>
                {!product ? (
                    <p role="status">
                        Esta combinación no forma parte del modelo.
                    </p>
                ) : (
                    <fieldset>
                        <legend className="mb-3 font-medium">
                            Cantidades por talla
                        </legend>
                        <div className="grid grid-cols-3 gap-3">
                            {current.map((v) => (
                                <SizeQuantityInput
                                    key={v.id}
                                    size={v.size}
                                    value={quantities[v.id] ?? 0}
                                    onChange={(quantity) =>
                                        setQuantities({
                                            ...quantities,
                                            [v.id]: quantity,
                                        })
                                    }
                                    onBlur={() =>
                                        track('catalog.quantity_changed', {
                                            product_id: v.id,
                                            quantity: quantities[v.id] ?? 0,
                                        })
                                    }
                                />
                            ))}
                        </div>
                        {product.moq && (
                            <p className="mt-3 text-sm text-muted-foreground">
                                Mínimo comercial: {product.moq} prendas por
                                variante. Puedes solicitar una excepción al
                                cotizar.
                            </p>
                        )}
                    </fieldset>
                )}
                <Button
                    className="w-full"
                    disabled={busy || !lines.length}
                    onClick={() => {
                        add(lines);
                        setQuantities({});
                    }}
                >
                    Agregar a mi selección
                </Button>
                <div>
                    {Object.entries({
                        'Material y construcción': [
                            style.details.fabric,
                            style.details.construction,
                        ]
                            .filter(Boolean)
                            .join('\n'),
                        'Medidas y corte': [
                            style.details.fit_guide,
                            style.details.size_guide,
                            product?.details?.measurements,
                            product?.details?.tolerance,
                        ]
                            .filter(Boolean)
                            .join('\n'),
                        Cuidados: style.details.care,
                    }).map(([title, value]) => (
                        <details
                            key={title}
                            className="border-t py-4"
                            onToggle={(e) => {
                                if (e.currentTarget.open)
                                    track('ui.section_expanded', {
                                        section: title,
                                    });
                            }}
                        >
                            <summary className="cursor-pointer font-medium">
                                {title}
                            </summary>
                            <p className="mt-3 text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                {value ||
                                    'Consulta con comercio para confirmar esta información.'}
                            </p>
                        </details>
                    ))}
                </div>
            </div>
        </div>
    );
}
