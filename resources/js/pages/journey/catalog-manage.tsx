import { useForm } from '@inertiajs/react';
import {
    Journey,
    Errors,
    inputClass,
    type Style,
    type Variant,
    type CatalogImage,
} from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
export default function CatalogManage({
    styles,
    variants,
    conflicts,
}: {
    styles: Style[];
    variants: Variant[];
    conflicts: { sku: string }[];
}) {
    const style = useForm({
        id: null as number | null,
        name: '',
        category: 'Playera',
        published: false,
        details: {
            description: '',
            fabric: '',
            construction: '',
            care: '',
            fit_guide: '',
            size_guide: '',
            photos: [] as string[],
        },
    });
    const variant = useForm({
        id: null as number | null,
        style_id: '',
        sku: '',
        fit: '',
        color: '',
        size: '',
        composition: '',
        gsm: '',
        moq: '',
        source: '',
        published: false,
        details: {
            color_code: '',
            measurements: '',
            tolerance: '',
            images: [] as CatalogImage[],
        },
    });
    return (
        <Journey
            title="Catálogo compartido"
            description="Publica fichas y combinaciones verificadas. Los precios se acuerdan en cada cotización."
        >
            {!!conflicts.length && (
                <p role="alert" className="rounded border p-4">
                    SKU heredados repetidos:{' '}
                    {conflicts.map((c) => c.sku).join(', ')}. Corrígelos antes
                    de publicar.
                </p>
            )}
            <div className="grid gap-8 lg:grid-cols-2">
                <form
                    className="space-y-4 rounded-xl border p-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        style.post('/products/styles');
                    }}
                >
                    <h2 className="text-xl font-semibold">Ficha del blank</h2>
                    <select
                        className={inputClass}
                        value={style.data.id ?? ''}
                        onChange={(e) => {
                            const s = styles.find(
                                (s) => s.id === Number(e.target.value),
                            );
                            if (s)
                                style.setData({
                                    id: s.id,
                                    name: s.name,
                                    category: s.category,
                                    published: s.published,
                                    details: {
                                        description: '',
                                        fabric: '',
                                        construction: '',
                                        care: '',
                                        fit_guide: '',
                                        size_guide: '',
                                        ...s.details,
                                    },
                                });
                            else style.reset();
                        }}
                    >
                        <option value="">Nueva ficha</option>
                        {styles.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                    <Errors errors={style.errors} />
                    {(['name', 'category'] as const).map((key) => (
                        <label key={key} className="block">
                            {key === 'name' ? 'Nombre' : 'Tipo de prenda'}
                            <input
                                required
                                className={inputClass}
                                value={style.data[key]}
                                onChange={(e) =>
                                    style.setData(key, e.target.value)
                                }
                            />
                        </label>
                    ))}
                    {Object.entries({
                        description: 'Descripción',
                        fabric: 'Tejido',
                        construction: 'Construcción y acabados',
                        care: 'Cuidados',
                        fit_guide: 'Tipos de fit y explicación',
                        size_guide: 'Guía de tallas, medidas y tolerancias',
                    }).map(([key, label]) => (
                        <label className="block" key={key}>
                            {label}
                            <textarea
                                rows={3}
                                className={inputClass}
                                value={style.data.details[key as 'description']}
                                onChange={(e) =>
                                    style.setData('details', {
                                        ...style.data.details,
                                        [key]: e.target.value,
                                    })
                                }
                            />
                        </label>
                    ))}
                    <label className="block">
                        Fotografías (una URL HTTPS por línea)
                        <textarea
                            className={inputClass}
                            value={style.data.details.photos.join('\n')}
                            onChange={(e) =>
                                style.setData('details', {
                                    ...style.data.details,
                                    photos: e.target.value
                                        .split('\n')
                                        .filter(Boolean),
                                })
                            }
                        />
                    </label>
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={style.data.published}
                            onChange={(e) =>
                                style.setData('published', e.target.checked)
                            }
                        />
                        Publicar ficha
                    </label>
                    <Button disabled={style.processing}>Guardar ficha</Button>
                </form>
                <form
                    className="space-y-4 rounded-xl border p-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        variant.post('/products/variants');
                    }}
                >
                    <h2 className="text-xl font-semibold">Variante por SKU</h2>
                    <select
                        className={inputClass}
                        value={variant.data.id ?? ''}
                        onChange={(e) => {
                            const v = variants.find(
                                (v) => v.id === Number(e.target.value),
                            );
                            if (v)
                                variant.setData({
                                    ...variant.data,
                                    id: v.id,
                                    style_id: String(v.style_id ?? ''),
                                    sku: v.sku,
                                    source: v.source ?? '',
                                    fit: v.fit ?? '',
                                    color: v.color ?? '',
                                    size: v.size ?? '',
                                    composition: v.composition ?? '',
                                    gsm: String(v.gsm ?? ''),
                                    moq: String(v.moq ?? ''),
                                    published: v.published ?? false,
                                    details: {
                                        color_code: '',
                                        measurements: '',
                                        tolerance: '',
                                        images: [],
                                        ...v.details,
                                    },
                                });
                            else variant.reset();
                        }}
                    >
                        <option value="">Nueva variante</option>
                        {variants.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.name} · {v.color} · {v.size}
                            </option>
                        ))}
                    </select>
                    <Errors errors={variant.errors} />
                    <label className="block">
                        Ficha
                        <select
                            required
                            className={inputClass}
                            value={variant.data.style_id}
                            onChange={(e) =>
                                variant.setData('style_id', e.target.value)
                            }
                        >
                            <option value="">Selecciona un blank</option>
                            {styles.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {Object.entries({
                            sku: 'SKU',
                            fit: 'Fit',
                            color: 'Color',
                            size: 'Talla',
                            composition: 'Composición',
                            gsm: 'Gramaje GSM',
                            moq: 'Cantidad mínima',
                            source: 'Fuente de verificación',
                        }).map(([key, label]) => (
                            <label key={key}>
                                {label}
                                <input
                                    className={inputClass}
                                    value={variant.data[key as 'sku']}
                                    onChange={(e) =>
                                        variant.setData(
                                            key as 'sku',
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                        ))}
                    </div>
                    {Object.entries({
                        color_code: 'Código de color',
                        measurements: 'Medidas de esta talla',
                        tolerance: 'Tolerancia',
                    }).map(([key, label]) => (
                        <label className="block" key={key}>
                            {label}
                            <input
                                className={inputClass}
                                value={
                                    variant.data.details[key as 'color_code']
                                }
                                onChange={(e) =>
                                    variant.setData('details', {
                                        ...variant.data.details,
                                        [key]: e.target.value,
                                    })
                                }
                            />
                        </label>
                    ))}
                    <fieldset className="space-y-3">
                        <legend>Imágenes de esta variante</legend>
                        {(
                            [
                                'front',
                                'back',
                                'three-quarter',
                                'detail',
                            ] as const
                        ).map((view) => (
                            <label key={view} className="block text-sm">
                                {
                                    {
                                        front: 'Frente',
                                        back: 'Espalda',
                                        'three-quarter': 'Tres cuartos',
                                        detail: 'Detalle',
                                    }[view]
                                }
                                <input
                                    className={inputClass}
                                    placeholder="URL HTTPS o /images/catalog/v1/…"
                                    value={
                                        variant.data.details.images.find(
                                            (image) => image.view === view,
                                        )?.path ?? ''
                                    }
                                    onChange={(e) => {
                                        const previous =
                                            variant.data.details.images.find(
                                                (image) => image.view === view,
                                            );
                                        const images =
                                            variant.data.details.images.filter(
                                                (image) => image.view !== view,
                                            );
                                        if (e.target.value)
                                            images.push({
                                                view,
                                                path: e.target.value,
                                                alt: `${variant.data.color} · ${view}`,
                                                width: previous?.width ?? 1254,
                                                height:
                                                    previous?.height ?? 1254,
                                            });
                                        variant.setData('details', {
                                            ...variant.data.details,
                                            images,
                                        });
                                    }}
                                />
                            </label>
                        ))}
                    </fieldset>
                    <label className="flex gap-2">
                        <input
                            type="checkbox"
                            checked={variant.data.published}
                            onChange={(e) =>
                                variant.setData('published', e.target.checked)
                            }
                        />
                        Publicar variante
                    </label>
                    <Button disabled={variant.processing}>
                        Guardar variante
                    </Button>
                </form>
            </div>
        </Journey>
    );
}
