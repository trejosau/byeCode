import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    return (
        <>
            <Head title="Laravel" />
            <main className="flex min-h-screen flex-col items-center justify-center gap-6 p-6">
                <h1 className="text-3xl font-semibold">Laravel + React</h1>
                <p className="text-muted-foreground">
                    Inertia, TypeScript, Vite and Tailwind CSS.
                </p>
                <nav className="flex gap-3" aria-label="Account">
                    <Button asChild>
                        <Link href={login()}>Log in</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={register()}>Register</Link>
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href={dashboard()}>Dashboard</Link>
                    </Button>
                </nav>
            </main>
        </>
    );
}
