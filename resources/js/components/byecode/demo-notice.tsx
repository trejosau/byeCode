import { router, usePage } from '@inertiajs/react';

export function DemoNotice() {
    const page = usePage<{
        demo?: {
            available: boolean;
            active: boolean;
            account: boolean;
            scope: string;
        };

        auth: { user: { role: string } | null };

        customer?: { demo_run_id?: string | null };

        case?: { demo_run_id?: string | null };
    }>();

    const { demo, auth, customer, case: current } = page.props;

    if (!demo?.available) return null;

    const staff = auth.user?.role === 'commerce' || auth.user?.role === 'admin';

    const synthetic =
        demo.account || customer?.demo_run_id || current?.demo_run_id;

    const path = page.url.split('?')[0];

    const filterable =
        staff &&
        ['/customers', '/cases', '/requests', '/workspace', '/admin'].includes(
            path,
        );

    return (
        <aside
            aria-label="Datos de demostración"
            className="flex flex-wrap items-center justify-between gap-3 border-b bg-muted px-5 py-3 text-sm"
        >
            <p className="max-w-4xl">
                <strong>
                    {synthetic
                        ? 'Datos de demostración. '
                        : 'Entorno con datos de demostración. '}
                </strong>
                {synthetic
                    ? 'Puedes continuar las solicitudes y cotizaciones. El historial es ficticio; los correos y el análisis de IA están deshabilitados para estas cuentas.'
                    : demo.active
                      ? 'Los nuevos registros de cliente se crean para pruebas. El historial de prueba está identificado y no entrena el predictor.'
                      : 'El historial de prueba está identificado y no entrena el predictor.'}
            </p>
            {filterable && (
                <label className="flex items-center gap-2">
                    Mostrar
                    <select
                        className="rounded-md border bg-background px-3 py-2"
                        value={demo.scope}
                        onChange={(event) => {
                            const url = new URL(window.location.href);

                            url.searchParams.set('origin', event.target.value);

                            url.searchParams.delete('page');

                            url.searchParams.delete('preview');

                            router.get(
                                url.pathname + url.search,
                                {},
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <option value="all">Todos los datos</option>
                        <option value="demo">Solo demostración</option>
                        <option value="real">Sin demostración</option>
                    </select>
                </label>
            )}
        </aside>
    );
}
