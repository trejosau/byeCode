import { cn } from '@/lib/utils';

export default function AppWordmark({ className }: { className?: string }) {
    return (
        <span className={cn('tracking-tight', className)}>
            byecode
            <span className="text-emerald-600 dark:text-emerald-400">.</span>
        </span>
    );
}
