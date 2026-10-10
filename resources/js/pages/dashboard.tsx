import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <main className="p-6">
                <h1 className="text-2xl font-semibold">Dashboard</h1>
                <p className="mt-2 text-muted-foreground">
                    Application scaffold ready.
                </p>
            </main>
        </>
    );
}
