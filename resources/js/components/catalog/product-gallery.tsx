import { useState } from 'react';
import type { CatalogImage } from '@/components/byecode/journey';
const labels: Record<string, string> = {
    front: 'Frente',
    back: 'Espalda',
    'three-quarter': 'Tres cuartos',
    detail: 'Detalle',
};
export function ProductGallery({
    images,
    name,
}: {
    images: CatalogImage[];
    name: string;
}) {
    const [index, setIndex] = useState(0);
    const selected = images[index] ?? images[0];
    return (
        <div className="space-y-3">
            <div className="flex aspect-square items-center justify-center rounded-xl bg-[#e7e7e4] dark:bg-[#676762]">
                {selected ? (
                    <img
                        src={selected.path}
                        alt={selected.alt}
                        width={selected.width}
                        height={selected.height}
                        className="size-full object-contain p-5"
                    />
                ) : (
                    <p className="text-black">{name}</p>
                )}
            </div>
            <div className="flex gap-2">
                {images.map((image, i) => (
                    <button
                        key={image.path}
                        type="button"
                        onClick={() => setIndex(i)}
                        aria-pressed={index === i}
                        className={`flex-1 rounded-md border p-2 text-xs ${index === i ? 'border-foreground' : ''}`}
                    >
                        <img
                            src={image.path}
                            alt=""
                            className="mx-auto h-16 object-contain"
                        />
                        {labels[image.view] || image.view}
                    </button>
                ))}
            </div>
        </div>
    );
}
