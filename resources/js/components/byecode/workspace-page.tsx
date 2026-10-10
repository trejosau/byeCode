import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { CheckCircle2, Inbox } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

export function WorkspacePage({
    title,
    description,
    actions,
    children,
}: {
    title: string;
    description: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    const success = usePage().props.success;
    return (
        <>
            <Head title={title} />
            <main className="workspace-page flex min-w-0 flex-1 flex-col gap-7 p-5 md:p-8 lg:p-10">
                <div className="flex flex-wrap items-start justify-between gap-4 border-b pb-6">
                    <div className="max-w-2xl space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                            {title}
                        </h1>
                        <p className="text-sm leading-relaxed text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    {actions && (
                        <div className="flex flex-wrap gap-2">{actions}</div>
                    )}
                </div>
                {typeof success === 'string' && (
                    <Alert role="status">
                        <CheckCircle2 className="size-4" />
                        <AlertDescription>{success}</AlertDescription>
                    </Alert>
                )}
                {children}
            </main>
        </>
    );
}
export function EmptyState({
    title,
    description,
    href,
    label,
}: {
    title: string;
    description: string;
    href?: string;
    label?: string;
}) {
    return (
        <div className="flex min-h-56 flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-8 text-center">
            <Inbox className="mb-1 size-8 text-muted-foreground" />
            <h2 className="font-semibold">{title}</h2>
            <p className="max-w-md text-sm leading-relaxed text-muted-foreground">
                {description}
            </p>
            {href && (
                <Button asChild className="mt-2">
                    <Link href={href}>{label}</Link>
                </Button>
            )}
        </div>
    );
}
export function Field({
    label,
    htmlFor,
    error,
    children,
    hint,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    children: ReactNode;
    hint?: string;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && (
                <p className="text-xs leading-relaxed text-muted-foreground">
                    {hint}
                </p>
            )}
            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
export function FormErrors({ errors }: { errors: Record<string, string> }) {
    const items = Object.values(errors);
    return items.length ? (
        <Alert variant="destructive" role="alert">
            <AlertDescription>
                <ul className="list-inside list-disc">
                    {[...new Set(items)].map((e) => (
                        <li key={e}>{e}</li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    ) : null;
}
