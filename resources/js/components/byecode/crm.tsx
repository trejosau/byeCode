import { EvidenceFields } from '@/components/byecode/evidence-fields';
import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { CheckCircle2 } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import type { CommercialFacts } from './commercial-profile';
import { Badge } from '@/components/ui/badge';
import { date, eventNames } from '@/lib/byecode';

export type CrmCustomer = {
    id: number;
    company: string;
    contact_name: string | null;
    email: string | null;
    commercialProfile: CommercialFacts;
    requests_count: number;
    paid_cents: number;
    formalized_cents: number;
    last_activity: string | null;
};
export type ProfileEvent = {
    id: number;
    type: string;
    actor: string;
    source?: string;
    data: unknown;
    occurred_at: string;
};
export const crmControl =
    'h-9 min-w-0 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-foreground disabled:opacity-50';
export const crmLink =
    'rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-foreground';

export function customerProfileUrl(
    id: number,
    directory = '',
    tab?: string,
    event?: number,
) {
    const query = new URLSearchParams();
    if (directory) query.set('directory', directory);
    if (tab) query.set('tab', tab);
    if (event) query.set('event', String(event));
    return (
        `/customers/${id}` +
        (query.size ? '?' + query.toString() : '') +
        (event ? '#event-' + event : '')
    );
}

export function CrmPage({
    title,
    description,
    actions,
    children,
    detail = false,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
    detail?: boolean;
}) {
    const { auth, success, directoryQuery } = usePage<{
        auth: { user: { role: string } };
        success?: string;
        directoryQuery?: string;
    }>().props;
    const parent =
        auth.user.role === 'admin'
            ? { title: 'Administración', href: '/admin' }
            : {
                  title: 'Clientes',
                  href:
                      '/customers' +
                      (directoryQuery ? '?' + directoryQuery : ''),
              };
    return (
        <AppLayout
            breadcrumbs={detail ? [parent, { title, href: '#' }] : [parent]}
        >
            <Head title={detail ? title + ' · CRM' : 'CRM de clientes'} />
            <div className="min-w-0 flex-1 space-y-6 p-4 selection:bg-primary selection:text-primary-foreground md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight break-words">
                            {title}
                        </h1>
                        {description && (
                            <p className="max-w-2xl text-sm leading-6 break-words text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && (
                        <div className="flex flex-wrap items-center gap-2">
                            {actions}
                        </div>
                    )}
                </header>
                {success && (
                    <p
                        role="status"
                        className="flex items-start gap-2 rounded-lg border bg-muted p-3 text-sm"
                    >
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                        {success}
                    </p>
                )}
                {children}
            </div>
        </AppLayout>
    );
}
export function ProfileValue({
    fact,
}: {
    fact: CommercialFacts[string] | undefined;
}) {
    if (!fact?.labels.length)
        return <span className="text-muted-foreground">Por confirmar</span>;
    return (
        <span className="space-y-1">
            <span className="block break-words">{fact.labels.join(' · ')}</span>
            {fact.nature !== 'confirmed' && (
                <Badge variant="outline">Hipótesis</Badge>
            )}
        </span>
    );
}
const crmEvents: Record<string, string> = {
    ...eventNames,
    'request.submitted': 'Solicitud enviada',
    'message.sent': 'Mensaje enviado',
    'buyer.decision': 'Decisión del comprador',
    'sale.recorded': 'Resultado comercial registrado',
    'quote.withdrawn': 'Cotización retirada',
    'catalog.viewed': 'Consulta del catálogo',
    'catalog.filtered': 'Filtros del catálogo',
};
export const eventLabel = (type: string) => crmEvents[type] ?? type;
export const eventExcerpt = (data: unknown) => {
    if (typeof data === 'string') return data;
    if (data && typeof data === 'object') {
        const values = data as Record<string, unknown>;
        for (const key of ['message', 'text', 'notes', 'reason', 'reference']) {
            if (typeof values[key] === 'string') return values[key];
        }
    }
    return 'Consulta el detalle registrado de este evento.';
};
export function EvidenceEvent({
    event,
    selected = false,
}: {
    event: ProfileEvent;
    selected?: boolean;
}) {
    return (
        <article
            id={'event-' + event.id}
            className={
                'scroll-mt-6 space-y-3 border-b p-4 last:border-b-0 ' +
                (selected ? 'bg-muted' : '')
            }
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 className="text-sm font-medium">
                        {eventLabel(event.type)}
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Evento {event.id} ·{' '}
                        {(
                            {
                                commerce: 'Comercio',
                                customer: 'Cliente',
                                system: 'Sistema',
                                ai: 'IA',
                                buyer: 'Comprador',
                                seller: 'Comercio',
                            } as Record<string, string>
                        )[event.actor] ?? event.actor}
                    </p>
                </div>
                <time
                    className="text-xs text-muted-foreground"
                    dateTime={event.occurred_at}
                >
                    {date(event.occurred_at)}
                </time>
            </div>
            <p className="max-w-prose text-sm leading-6 break-words whitespace-pre-wrap">
                {eventExcerpt(event.data)}
            </p>
            {event.source === 'browser_unverified' && (
                <p className="text-xs text-muted-foreground">
                    Actividad de navegación no verificada; no demuestra
                    intención de compra.
                </p>
            )}
            <details className="text-sm">
                <summary className="cursor-pointer text-muted-foreground">
                    Detalle registrado
                </summary>
                <EvidenceFields data={event.data as Record<string, unknown>} />
            </details>
        </article>
    );
}
