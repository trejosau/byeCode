import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

export function ThemeSwitch() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const dark = resolvedAppearance === 'dark';
    return (
        <Button
            variant="ghost"
            size="icon"
            role="switch"
            aria-label="Tema oscuro"
            aria-checked={dark}
            onClick={() => updateAppearance(dark ? 'light' : 'dark')}
        >
            {dark ? <Moon aria-hidden /> : <Sun aria-hidden />}
        </Button>
    );
}
