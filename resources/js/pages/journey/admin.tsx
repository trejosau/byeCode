import { Link, useForm } from '@inertiajs/react';
import { Journey, Errors, inputClass } from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
export default function Admin({
    team,
    summary,
    mail,
    budget,
    runs,
    customers,
    outcomes,
    prediction,
}: {
    team: { id: number; name: string; email: string }[];
    summary: Record<string, number>;
    mail: { status: string; total: number }[];
    budget: { daily_micromxn: number; daily_limit_mxn: number };
    runs: {
        id: number;
        customer_id: number;
        status: string;
        error: string | null;
    }[];
    customers: { id: number; company: string; contact_name: string }[];
    outcomes: { kind: string; total: number; amount_cents: number | null }[];
    prediction: {
        id: number;
        status: string;
        report: { reason?: string; eligible_rows: number };
    } | null;
}) {
    const form = useForm({ name: '', email: '' });
    return (
        <Journey
            title="Administración"
            description="Lectura del negocio y alta del equipo de comercio."
        >
            <div className="grid gap-4 sm:grid-cols-4">
                {Object.entries({
                    customers: 'Clientes CRM',
                    requests: 'Solicitudes',
                    quotes: 'Versiones publicadas',
                    open: 'Oportunidades abiertas',
                }).map(([key, label]) => (
                    <div key={key} className="rounded-xl border p-5">
                        <p className="text-sm text-muted-foreground">{label}</p>
                        <p className="mt-2 text-3xl font-semibold">
                            {summary[key]}
                        </p>
                    </div>
                ))}
            </div>
            <section className="grid gap-8 rounded-xl border p-6 md:grid-cols-2">
                <div className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Clientes e historial
                    </h2>
                    {customers.length ? (
                        customers.map((customer) => (
                            <p key={customer.id}>
                                <Link
                                    className="underline"
                                    href={'/customers/' + customer.id}
                                >
                                    {customer.company} · {customer.contact_name}
                                </Link>
                            </p>
                        ))
                    ) : (
                        <p>
                            El CRM se inicia con la primera solicitud de cada
                            cliente.
                        </p>
                    )}
                </div>
                <div className="space-y-3">
                    <h2 className="text-xl font-semibold">
                        Resultados registrados
                    </h2>
                    {outcomes.length ? (
                        outcomes.map((outcome) => (
                            <p key={outcome.kind}>
                                {
                                    (
                                        {
                                            formalized: 'Formalizaciones',
                                            payment: 'Pagos',
                                            rejected: 'Rechazos',
                                            cancelled: 'Cancelaciones',
                                        } as Record<string, string>
                                    )[outcome.kind]
                                }
                                : {outcome.total}
                                {outcome.amount_cents !== null
                                    ? ' · $' +
                                      (
                                          outcome.amount_cents / 100
                                      ).toLocaleString('es-MX') +
                                      ' MXN'
                                    : ''}
                            </p>
                        ))
                    ) : (
                        <p>Sin resultados confirmados.</p>
                    )}
                    <h3 className="font-medium">Validación predictiva</h3>
                    {prediction ? (
                        <p>
                            {prediction.report.eligible_rows} casos elegibles.{' '}
                            {prediction.report.reason ??
                                (prediction.status === 'experimental_ready'
                                    ? 'Superó los criterios experimentales.'
                                    : 'No habilita probabilidades.')}
                        </p>
                    ) : (
                        <p>
                            Aún no hay una evaluación del historial comercial.
                        </p>
                    )}
                </div>
            </section>
            <div className="grid gap-8 md:grid-cols-2">
                <section className="space-y-4 rounded-xl border p-6">
                    <h2 className="text-xl font-semibold">Equipo comercial</h2>
                    {team.map((u) => (
                        <p key={u.id}>
                            {u.name} · {u.email}
                        </p>
                    ))}
                    <form
                        className="space-y-4 border-t pt-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/admin/team', {
                                onSuccess: () => form.reset(),
                            });
                        }}
                    >
                        <Errors errors={form.errors} />
                        <label className="block">
                            Nombre
                            <input
                                required
                                className={inputClass}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </label>
                        <label className="block">
                            Correo
                            <input
                                required
                                type="email"
                                className={inputClass}
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                        </label>
                        <Button disabled={form.processing}>
                            Crear cuenta de comercio
                        </Button>
                    </form>
                </section>
                <section className="space-y-4 rounded-xl border p-6">
                    <h2 className="text-xl font-semibold">
                        Operación y análisis
                    </h2>
                    <p>
                        Consumo preventivo hoy: $
                        {(budget.daily_micromxn / 1000000).toFixed(2)} de $
                        {budget.daily_limit_mxn} MXN.
                    </p>
                    <h3 className="font-medium">Correo</h3>
                    {mail.length ? (
                        mail.map((m) => (
                            <p key={m.status}>
                                {m.status}: {m.total}
                            </p>
                        ))
                    ) : (
                        <p>Sin envíos registrados.</p>
                    )}
                    <h3 className="font-medium">Perfiles procesados</h3>
                    {runs.map((r) => (
                        <p key={r.id}>
                            <Link
                                className="underline"
                                href={'/customers/' + r.customer_id}
                            >
                                Cliente {r.customer_id}
                            </Link>{' '}
                            · {r.status} {r.error}
                        </p>
                    ))}
                </section>
            </div>
        </Journey>
    );
}
