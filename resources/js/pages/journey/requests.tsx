import { Link } from '@inertiajs/react';
import { Journey } from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
import { money, statuses } from '@/lib/byecode';
type Case = {
    id: number;
    public_id: string;
    external_id: string;
    title: string;
    stage: string;
    status: string;
    amount_cents: number | null;
    created_at: string;
};
export default function Requests({
    cases,
    commerce,
}: {
    cases: Case[];
    commerce: boolean;
}) {
    return (
        <Journey
            title={
                commerce
                    ? 'Solicitudes del equipo'
                    : 'Mis solicitudes y cotizaciones'
            }
            description="Cada solicitud muestra su cotización más reciente, conversación y decisiones."
        >
            <Button asChild>
                <Link href={commerce ? '/physical-request' : '/catalog'}>
                    {commerce ? 'Cotización presencial' : 'Explorar blanks'}
                </Link>
            </Button>
            {!cases.length && (
                <p className="rounded-xl border p-10 text-muted-foreground">
                    Todavía no hay solicitudes. Empieza seleccionando tus
                    blanks.
                </p>
            )}
            <div className="grid gap-4 md:grid-cols-2">
                {cases.map((c) => (
                    <article
                        key={c.id}
                        className="space-y-4 rounded-xl border p-6"
                    >
                        <div className="flex justify-between gap-3">
                            <h2 className="font-semibold">{c.title}</h2>
                            <span className="text-sm text-muted-foreground">
                                {statuses[c.status] ?? c.status}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {c.external_id} ·{' '}
                            {new Date(c.created_at).toLocaleDateString('es-MX')}
                        </p>
                        <p>
                            {c.amount_cents === null
                                ? 'Pendiente de cotizar'
                                : money(c.amount_cents)}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            <Button asChild>
                                <Link href={'/my/quotes/' + c.public_id}>
                                    {commerce
                                        ? 'Revisar solicitud y conversación'
                                        : c.amount_cents === null
                                          ? 'Ver solicitud'
                                          : 'Ver cotización'}
                                </Link>
                            </Button>
                            {commerce && (
                                <>
                                    <Button variant="outline" asChild>
                                        <Link href={`/cases/${c.id}/quote`}>
                                            {c.amount_cents === null
                                                ? 'Preparar cotización'
                                                : 'Actualizar cotización'}
                                        </Link>
                                    </Button>
                                    {c.amount_cents !== null && (
                                        <Link
                                            className="self-center underline"
                                            href={`/my/quotes/${c.public_id}#analysis`}
                                        >
                                            Ver análisis
                                        </Link>
                                    )}
                                </>
                            )}
                        </div>
                    </article>
                ))}
            </div>
        </Journey>
    );
}
