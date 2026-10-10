import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ListChecks } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Bienvenido" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
                    <span className="text-xl font-semibold tracking-tight">
                        ByeCode<span className="text-emerald-600">.</span>
                    </span>
                    <Button variant="ghost" asChild>
                        <Link href={auth.user ? dashboard() : login()}>
                            {auth.user ? 'Abrir panel' : 'Iniciar sesión'}
                        </Link>
                    </Button>
                </header>
                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center gap-8 px-6 py-20">
                    <div className="flex size-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        <ListChecks className="size-7" aria-hidden="true" />
                    </div>
                    <div className="max-w-2xl space-y-5">
                        <p className="text-sm font-medium tracking-widest text-muted-foreground uppercase">
                            Decisiones empresariales con IA
                        </p>
                        <h1 className="text-4xl leading-tight font-semibold tracking-tight sm:text-6xl">
                            Cada cotización,
                            <br />
                            con un siguiente paso.
                        </h1>
                        <p className="max-w-xl text-lg leading-relaxed text-muted-foreground">
                            Un espacio para dar seguimiento a tus oportunidades
                            y tomar decisiones con evidencia. Cotizaciones,
                            historial y recomendaciones sujetas a tus reglas.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <Button size="lg" asChild>
                            <Link href={auth.user ? dashboard() : register()}>
                                {auth.user
                                    ? 'Ir a mi panel'
                                    : 'Crear mi cuenta'}
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </Button>
                        <span className="text-sm text-muted-foreground">
                            Acceso privado · Importes en MXN
                        </span>
                    </div>
                </main>
                <footer className="mx-auto w-full max-w-5xl border-t px-6 py-6 text-sm text-muted-foreground">
                    ByeCode · Proyecto de decisiones empresariales con IA
                </footer>
            </div>
        </>
    );
}
