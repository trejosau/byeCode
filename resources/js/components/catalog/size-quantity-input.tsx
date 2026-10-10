import { inputClass } from '@/components/byecode/journey';
export function SizeQuantityInput({
    size,
    value,
    onChange,
    onBlur,
    disabled = false,
}: {
    size: string;
    value: number;
    onChange: (value: number) => void;
    onBlur?: () => void;
    disabled?: boolean;
}) {
    return (
        <label className="grid gap-2 text-sm">
            {size}
            <input
                className={inputClass}
                aria-label={`Cantidad talla ${size}`}
                type="number"
                min="0"
                max="1000000"
                step="1"
                inputMode="numeric"
                disabled={disabled}
                value={value || ''}
                onChange={(e) =>
                    onChange(
                        Math.max(
                            0,
                            Math.min(
                                1000000,
                                Math.floor(Number(e.target.value)),
                            ),
                        ),
                    )
                }
                onBlur={onBlur}
            />
        </label>
    );
}
