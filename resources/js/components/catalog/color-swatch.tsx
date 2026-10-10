export function ColorSwatch({
    name,
    hex,
    selected,
    onSelect,
}: {
    name: string;
    hex?: string;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            aria-label={name}
            aria-pressed={selected}
            title={name}
            onClick={onSelect}
            className={`inline-flex min-h-10 items-center gap-2 rounded-md border px-2 text-xs focus-visible:outline-2 focus-visible:outline-offset-2 ${selected ? 'border-foreground' : 'border-transparent'}`}
        >
            <span
                className="size-5 rounded-full border border-black/25"
                style={{ backgroundColor: hex || '#888888' }}
            />
            <span>
                {name}
                {selected ? ' ✓' : ''}
            </span>
        </button>
    );
}
