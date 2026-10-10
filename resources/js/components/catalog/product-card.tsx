import { Link } from '@inertiajs/react';
import { useState } from 'react';
import type { Style, Variant } from '@/components/byecode/journey';
import { ColorSwatch } from './color-swatch';
export function ProductCard({
    style,
    variants,
    search,
    onConfigure,
}: {
    style: Style;
    variants: Variant[];
    search: string;
    onConfigure: () => void;
}) {
    const [color, setColor] = useState(variants[0]?.color);
    const product = variants.find((v) => v.color === color) ?? variants[0];
    if (!product) return null;
    const url = `/catalog/${style.id}${search || `?color=${encodeURIComponent(product.color)}`}`;
    return (
        <article className="min-w-0 space-y-3">
            <Link
                href={url}
                preserveState
                onClick={onConfigure}
                aria-label={`Configurar ${style.name} · ${product.color}`}
                className="block aspect-[4/5] overflow-hidden rounded-xl bg-[#e7e7e4] dark:bg-[#676762]"
            >
                {product.details?.images?.[0] && (
                    <img
                        src={product.details.images[0].path}
                        alt={`${style.name} · ${product.color}`}
                        width={product.details.images[0].width}
                        height={product.details.images[0].height}
                        loading="lazy"
                        className="size-full object-contain p-4 transition-transform duration-200 hover:scale-105 motion-reduce:transform-none"
                    />
                )}
            </Link>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <h2 className="font-medium">{style.name}</h2>
                <span className="text-sm text-muted-foreground">
                    {product.gsm} g/m²
                </span>
            </div>
            <div className="flex flex-wrap gap-1">
                {[...new Set(variants.map((v) => v.color))].map((name) => (
                    <ColorSwatch
                        key={name}
                        name={name}
                        hex={
                            variants.find((v) => v.color === name)?.details
                                ?.color_code
                        }
                        selected={product.color === name}
                        onSelect={() => setColor(name)}
                    />
                ))}
            </div>
            <Link
                href={url}
                preserveState
                onClick={onConfigure}
                className="inline-flex min-h-10 items-center text-sm font-medium underline underline-offset-4"
            >
                Configurar <span className="sr-only">{style.name}</span>
            </Link>
        </article>
    );
}
