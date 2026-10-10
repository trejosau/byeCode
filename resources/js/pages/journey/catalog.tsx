import { Link, router, useRemember } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    Journey,
    inputClass,
    type Style,
    type Variant,
} from '@/components/byecode/journey';
import { ProductCard } from '@/components/catalog/product-card';
import {
    ProductConfigurator,
    type SelectionLine,
} from '@/components/catalog/product-configurator';
import { SelectionDrawer } from '@/components/catalog/selection-drawer';
import { Button } from '@/components/ui/button';
import { track, useActivity } from '@/hooks/use-activity';

export default function Catalog({
    styles,
    variants,
    selection,
    activeStyle,
}: {
    styles: Style[];
    variants: Variant[];
    selection: SelectionLine[];
    activeStyle?: number;
}) {
    useActivity();
    const [filters, setFilters] = useRemember(
        { query: '', category: '', fit: '', color: '', size: '', gsm: '' },
        'catalog-filters',
    );
    const [lines, setLines] = useState(selection);
    const [directoryScroll, setDirectoryScroll] = useRemember(
        0,
        'catalog-scroll',
    );
    const [pending, setPending] = useState(false);
    const [error, setError] = useState('');
    const latest = useRef(selection);
    const saving = useRef(false);
    const dirty = useRef(false);
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    function save() {
        if (saving.current) return;
        saving.current = true;
        dirty.current = false;
        let confirmed = false;
        router.post(
            '/selection/save',
            { lines: latest.current.filter((l) => l.quantity > 0) },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    confirmed = true;
                    setError('');
                },
                onError: (errors) => setError(Object.values(errors).join(' ')),
                onFinish: () => {
                    saving.current = false;
                    if (dirty.current) save();
                    else {
                        setPending(false);
                        if (!confirmed)
                            setError(
                                (message) =>
                                    message ||
                                    'No se confirmó el guardado. Reintenta antes de continuar.',
                            );
                    }
                },
            },
        );
    }
    function update(next: SelectionLine[]) {
        latest.current = next;
        setLines(next);
        dirty.current = true;
        setPending(true);
        setError('');
        clearTimeout(timer.current);
        timer.current = setTimeout(save, 600);
    }
    useEffect(() => () => clearTimeout(timer.current), []);
    useEffect(
        () =>
            router.on('before', (event) => {
                if (
                    (dirty.current || saving.current) &&
                    event.detail.visit.url.pathname !== '/selection/save'
                )
                    return false;
            }),
        [],
    );
    function add(incoming: SelectionLine[]) {
        const next = new Map(
            latest.current.map((l) => [l.product_id, l.quantity]),
        );
        incoming.forEach((l) =>
            next.set(l.product_id, (next.get(l.product_id) ?? 0) + l.quantity),
        );
        if (next.size > 100 || [...next.values()].some((q) => q > 1000000)) {
            setError(
                'La selección admite hasta 100 variantes y 1,000,000 prendas por variante.',
            );
            return;
        }
        update(
            [...next].map(([product_id, quantity]) => ({
                product_id,
                quantity,
            })),
        );
        clearTimeout(timer.current);
        save();
    }
    const visibleVariants = variants.filter(
        (v) =>
            (!filters.fit || v.fit === filters.fit) &&
            (!filters.color || v.color === filters.color) &&
            (!filters.size || v.size === filters.size) &&
            (!filters.gsm || String(v.gsm) === filters.gsm),
    );
    const visible = styles.filter(
        (s) =>
            s.name
                .toLocaleLowerCase()
                .includes(filters.query.toLocaleLowerCase()) &&
            (!filters.category || s.category === filters.category) &&
            visibleVariants.some((v) => v.style_id === s.id),
    );
    const style = styles.find((s) => s.id === activeStyle);
    return (
        <Journey title={style ? style.name : 'Catálogo'}>
            <div className="pb-24">
                {style ? (
                    <>
                        <Link
                            href="/catalog"
                            preserveState
                            onSuccess={() =>
                                window.scrollTo(0, directoryScroll)
                            }
                            className="mb-6 inline-flex min-h-10 items-center text-sm underline underline-offset-4"
                        >
                            ← Volver al catálogo
                        </Link>
                        <ProductConfigurator
                            key={style.id}
                            style={style}
                            variants={variants.filter(
                                (v) => v.style_id === style.id,
                            )}
                            busy={pending}
                            add={add}
                        />
                    </>
                ) : (
                    <>
                        <div className="mb-8 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
                            <label className="col-span-2 text-sm md:col-span-1">
                                Buscar
                                <input
                                    className={inputClass}
                                    placeholder="Nombre de la prenda"
                                    value={filters.query}
                                    onChange={(e) =>
                                        setFilters({
                                            ...filters,
                                            query: e.target.value,
                                        })
                                    }
                                    onBlur={() =>
                                        track('catalog.search', {
                                            query: filters.query,
                                        })
                                    }
                                />
                            </label>
                            {(
                                [
                                    'category',
                                    'fit',
                                    'color',
                                    'size',
                                    'gsm',
                                ] as const
                            ).map((key) => (
                                <label className="text-sm" key={key}>
                                    {
                                        {
                                            category: 'Categoría',
                                            fit: 'Corte',
                                            color: 'Color',
                                            size: 'Talla',
                                            gsm: 'Gramaje',
                                        }[key]
                                    }
                                    <select
                                        className={inputClass}
                                        value={filters[key]}
                                        onChange={(e) => {
                                            setFilters({
                                                ...filters,
                                                [key]: e.target.value,
                                            });
                                            track('catalog.filter', {
                                                filter:
                                                    key + ':' + e.target.value,
                                            });
                                        }}
                                    >
                                        <option value="">Todos</option>
                                        {[
                                            ...new Set(
                                                key === 'category'
                                                    ? styles.map(
                                                          (s) => s.category,
                                                      )
                                                    : variants.map((v) =>
                                                          String(v[key] ?? ''),
                                                      ),
                                            ),
                                        ]
                                            .filter(Boolean)
                                            .map((value) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {value}
                                                    {key === 'gsm'
                                                        ? ' g/m²'
                                                        : ''}
                                                </option>
                                            ))}
                                    </select>
                                </label>
                            ))}
                        </div>
                        <div className="mb-5 flex items-center justify-between">
                            <p className="text-sm text-muted-foreground">
                                {visible.length} modelos
                            </p>
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    setFilters({
                                        query: '',
                                        category: '',
                                        fit: '',
                                        color: '',
                                        size: '',
                                        gsm: '',
                                    })
                                }
                            >
                                Limpiar filtros
                            </Button>
                        </div>
                        {!visible.length && (
                            <p className="py-16 text-center">
                                No hay prendas con estos filtros.
                            </p>
                        )}
                        <div className="grid grid-cols-1 gap-x-6 gap-y-10 min-[360px]:grid-cols-2 lg:grid-cols-3">
                            {visible.map((s) => (
                                <ProductCard
                                    key={s.id}
                                    style={s}
                                    variants={visibleVariants.filter(
                                        (v) => v.style_id === s.id,
                                    )}
                                    search=""
                                    onConfigure={() =>
                                        setDirectoryScroll(window.scrollY)
                                    }
                                />
                            ))}
                        </div>
                    </>
                )}
                {error && (
                    <p role="alert" className="mt-4 text-destructive">
                        {error}
                        <Button
                            variant="outline"
                            className="ml-3"
                            disabled={pending}
                            onClick={() => {
                                setPending(true);
                                save();
                            }}
                        >
                            Reintentar guardado
                        </Button>
                    </p>
                )}
            </div>
            <SelectionDrawer
                lines={lines}
                variants={variants}
                busy={pending}
                error={error}
                update={update}
                submit={() =>
                    router.post('/selection', {
                        lines: latest.current.filter((l) => l.quantity > 0),
                    })
                }
            />
        </Journey>
    );
}
