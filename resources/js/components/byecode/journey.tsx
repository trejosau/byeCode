import { Head, Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import AppWordmark from '@/components/app-wordmark';
import { Button } from '@/components/ui/button';
import type { User } from '@/types/auth';

export function Journey({
    title,
    description,
    children,
}: PropsWithChildren<{ title: string; description?: string }>) {
    const { auth, success } = usePage<{
        auth: { user: User | null };
        success?: string;
    }>().props;
    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={title} />
            <header className="border-b">
                <nav
                    className="mx-auto flex max-w-7xl flex-wrap items-center gap-5 px-5 py-4"
                    aria-label="Principal"
                >
                    <Link href="/" className="mr-auto">
                        <AppWordmark className="text-3xl font-normal" />
                    </Link>
                    <Link href="/catalog">Blanks</Link>
                    {auth.user ? (
                        <>
                            <Link href="/dashboard">Mi espacio</Link>
                            {auth.user.role === 'commerce' && (
                                <>
                                    <Link href="/requests">Solicitudes</Link>
                                    <Link href="/customers">CRM</Link>
                                    <Link href="/products">Catálogo</Link>
                                </>
                            )}
                            <Link href="/settings/profile">
                                {auth.user.name}
                            </Link>
                            <Link href="/logout" method="post" as="button">
                                Salir
                            </Link>
                        </>
                    ) : (
                        <>
                            <Link href="/login">Entrar</Link>
                            <Button asChild>
                                <Link href="/register">Crear cuenta</Link>
                            </Button>
                        </>
                    )}
                </nav>
            </header>
            <main className="mx-auto max-w-7xl space-y-7 px-5 py-10">
                <div className="max-w-3xl">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-3 leading-7 text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {success && (
                    <p role="status" className="rounded-lg border bg-muted p-4">
                        {success}
                    </p>
                )}
                {children}
            </main>
        </div>
    );
}
export function Errors({ errors }: { errors: Record<string, string> }) {
    return Object.keys(errors).length ? (
        <div
            role="alert"
            className="rounded-lg border border-destructive p-4 text-destructive"
        >
            {Object.entries(errors).map(([key, text]) => (
                <p key={key}>{text}</p>
            ))}
        </div>
    ) : null;
}
export const inputClass =
    'w-full rounded-md border bg-background px-3 py-2 text-sm';
export type Variant = {
    id: number;
    style_id: number;
    sku: string;
    name: string;
    fit: string;
    color: string;
    size: string;
    gsm: number | null;
    composition: string | null;
    moq: number | null;
    stock: number | null;
    details?: Record<string, string>;
    published?: boolean;
};
export type Style = {
    id: number;
    name: string;
    category: string;
    published: boolean;
    details: {
        description?: string;
        fabric?: string;
        construction?: string;
        care?: string;
        fit_guide?: string;
        size_guide?: string;
        photos: string[];
    };
};
