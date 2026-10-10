import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    Search,
    SlidersHorizontal,
    Users,
    X,
} from 'lucide-react';
import type { CommercialDimensions } from '@/components/byecode/commercial-profile';
import {
    CrmPage,
    customerProfileUrl,
    crmControl,
    crmLink,
    ProfileValue,
    type CrmCustomer,
} from '@/components/byecode/crm';
import { Errors } from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { date, money } from '@/lib/byecode';

type Props = {
    customers: {
        data: CrmCustomer[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Record<string, string | null>;
    commercialDimensions: CommercialDimensions;
    customerPreview: CrmCustomer | null;
};
export default function Customers({
    customers,
    filters,
    commercialDimensions,
    customerPreview,
}: Props) {
    const { url } = usePage();
    const filterDimensions = Object.entries(commercialDimensions).filter(
        ([, d]) => d.type !== 'text',
    );
    const form = useForm<Record<string, string>>({
        search: filters.search ?? '',
        sort: filters.sort ?? 'name',
        ...Object.fromEntries(
            filterDimensions.map(([key]) => [key, filters[key] ?? '']),
        ),
    });
    const [expanded, setExpanded] = useState(false);
    const [preview, setPreview] = useState(customerPreview);
    const trigger = useRef<HTMLButtonElement | null>(null);
    useEffect(() => setPreview(customerPreview), [customerPreview]);
    const activeFilters = Object.entries(filters).filter(
        ([key, value]) => value && key !== 'sort',
    );
    const moreCount = activeFilters.filter(
        ([key]) =>
            !['search', 'business_type', 'operating_scale'].includes(key),
    ).length;
    const listUrl = () => {
        const query = new URLSearchParams(url.split('?')[1]);
        query.delete('preview');
        return '/customers' + (query.size ? '?' + query.toString() : '');
    };
    const openPreview = (customer: CrmCustomer, button: HTMLButtonElement) => {
        trigger.current = button;
        setPreview(customer);
        const query = new URLSearchParams(listUrl().split('?')[1]);
        query.set('preview', String(customer.id));
        router.get(
            '/customers?' + query.toString(),
            {},
            {
                only: ['customerPreview'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };
    const closePreview = () => {
        setPreview(null);
        router.get(
            listUrl(),
            {},
            {
                only: ['customerPreview'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };
    const removeFilter = (key: string) => {
        const next = { ...form.data, [key]: '' };
        form.setData(next);
        router.get('/customers', next, { preserveScroll: true });
    };
    const filterField = (key: string) => (
        <label
            key={key}
            className="flex min-w-0 flex-col gap-1.5 text-xs text-muted-foreground"
        >
            <span>{commercialDimensions[key].label}</span>
            <select
                className={crmControl + ' w-full text-foreground'}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
            >
                <option value="">Todos</option>
                {Object.entries(commercialDimensions[key].options).map(
                    ([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ),
                )}
            </select>
        </label>
    );
    const previewButton = (customer: CrmCustomer) => (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={'Vista rápida de ' + customer.company}
            onClick={(event) => openPreview(customer, event.currentTarget)}
        >
            <ArrowUpRight />
        </Button>
    );
    return (
        <CrmPage
            title="Clientes"
            description="Relaciones comerciales, necesidades y evidencia en un solo lugar."
            actions={
                <Button asChild variant="outline">
                    <Link href="/physical-request">Cotización presencial</Link>
                </Button>
            }
        >
            <form
                className="space-y-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.get('/customers', { preserveScroll: true });
                }}
            >
                <Errors errors={form.errors} />
                <div className="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(180px,1fr)_170px_190px_auto]">
                    <label className="space-y-1.5 text-xs text-muted-foreground">
                        <span>Buscar cliente</span>
                        <span className="relative mt-1.5 block">
                            <Search className="pointer-events-none absolute top-2.5 left-3 size-4" />
                            <input
                                className={
                                    crmControl + ' w-full pl-9 text-foreground'
                                }
                                maxLength={120}
                                value={form.data.search}
                                onChange={(e) =>
                                    form.setData('search', e.target.value)
                                }
                                placeholder="Empresa, contacto o correo"
                            />
                        </span>
                    </label>
                    <div className="hidden sm:contents">
                        {filterField('business_type')}
                        {filterField('operating_scale')}
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            aria-expanded={expanded}
                            aria-controls="crm-more-filters"
                            onClick={() => setExpanded(!expanded)}
                        >
                            <SlidersHorizontal />
                            Más filtros{moreCount > 0 && ` (${moreCount})`}
                        </Button>
                        <Button disabled={form.processing}>Buscar</Button>
                    </div>
                </div>
                {expanded && (
                    <div
                        id="crm-more-filters"
                        className="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        {filterDimensions.map(([key]) => (
                            <div
                                key={key}
                                className={
                                    [
                                        'business_type',
                                        'operating_scale',
                                    ].includes(key)
                                        ? 'sm:hidden'
                                        : ''
                                }
                            >
                                {filterField(key)}
                            </div>
                        ))}
                    </div>
                )}
                {activeFilters.length > 0 && (
                    <div
                        className="flex flex-wrap items-center gap-2"
                        aria-label="Filtros aplicados"
                    >
                        {activeFilters.map(([key, value]) => (
                            <Button
                                key={key}
                                type="button"
                                size="sm"
                                variant="secondary"
                                className="h-auto max-w-full py-1.5 text-left whitespace-normal"
                                onClick={() => removeFilter(key)}
                                aria-label={
                                    'Quitar filtro ' +
                                    (commercialDimensions[key]?.label ??
                                        'búsqueda')
                                }
                            >
                                <span>
                                    {key === 'search'
                                        ? 'Búsqueda'
                                        : commercialDimensions[key]?.label}
                                    :{' '}
                                    {commercialDimensions[key]?.options[
                                        value!
                                    ] ?? value}
                                </span>
                                <X className="size-3 shrink-0" />
                            </Button>
                        ))}
                        <Link
                            className={
                                crmLink + ' text-xs text-muted-foreground'
                            }
                            href="/customers"
                        >
                            Limpiar filtros
                        </Link>
                    </div>
                )}
            </form>
            <section
                className="min-w-0 overflow-hidden rounded-xl border"
                aria-label="Directorio de clientes"
                aria-busy={form.processing}
            >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b px-4 py-3">
                    <p className="text-sm font-medium tabular-nums">
                        {customers.total}{' '}
                        {customers.total === 1 ? 'cliente' : 'clientes'}
                        <span className="ml-2 font-normal text-muted-foreground">
                            en esta consulta
                        </span>
                    </p>
                    <label className="flex items-center gap-2 text-xs text-muted-foreground">
                        Ordenar
                        <select
                            className={crmControl + ' max-w-48 text-foreground'}
                            value={form.data.sort}
                            onChange={(e) => {
                                form.setData('sort', e.target.value);
                                router.get(
                                    '/customers',
                                    { ...filters, sort: e.target.value },
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <option value="name">Nombre A–Z</option>
                            <option value="activity">Actividad reciente</option>
                            <option value="paid">Mayor importe pagado</option>
                        </select>
                    </label>
                </div>
                {customers.data.length ? (
                    <>
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full text-left text-sm">
                                <caption className="sr-only">
                                    Clientes y clasificaciones comerciales. Los
                                    pagos son importes registrados en MXN.
                                </caption>
                                <thead className="border-b bg-muted/40 text-xs text-muted-foreground">
                                    <tr>
                                        {[
                                            'Cliente',
                                            'Tipo y escala',
                                            'Recurrencia',
                                            'Solicitudes',
                                            'Pagado · MXN',
                                            'Última actividad',
                                            '',
                                        ].map((label, i) => (
                                            <th
                                                key={i}
                                                scope="col"
                                                className={
                                                    'px-4 py-3 font-medium ' +
                                                    (i === 3 || i === 4
                                                        ? 'text-right'
                                                        : '')
                                                }
                                            >
                                                {label || (
                                                    <span className="sr-only">
                                                        Vista rápida
                                                    </span>
                                                )}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {customers.data.map((customer) => (
                                        <tr
                                            key={customer.id}
                                            className="align-top hover:bg-muted/40"
                                        >
                                            <td className="max-w-64 min-w-44 px-4 py-4">
                                                <Link
                                                    className={
                                                        crmLink +
                                                        ' font-medium break-words'
                                                    }
                                                    href={customerProfileUrl(
                                                        customer.id,
                                                        listUrl().split('?')[1],
                                                    )}
                                                >
                                                    {customer.company}
                                                </Link>
                                                <p className="mt-1 text-xs break-words text-muted-foreground">
                                                    {customer.contact_name ||
                                                        customer.email ||
                                                        'Sin contacto registrado'}
                                                </p>
                                            </td>
                                            <td className="max-w-56 min-w-36 space-y-1 px-4 py-4">
                                                <ProfileValue
                                                    fact={
                                                        customer
                                                            .commercialProfile
                                                            .business_type
                                                    }
                                                />
                                                <div className="text-xs text-muted-foreground">
                                                    <ProfileValue
                                                        fact={
                                                            customer
                                                                .commercialProfile
                                                                .operating_scale
                                                        }
                                                    />
                                                </div>
                                            </td>
                                            <td className="max-w-40 px-4 py-4">
                                                <ProfileValue
                                                    fact={
                                                        customer
                                                            .commercialProfile
                                                            .purchase_frequency
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-4 text-right tabular-nums">
                                                {customer.requests_count}
                                            </td>
                                            <td className="px-4 py-4 text-right whitespace-nowrap tabular-nums">
                                                {money(customer.paid_cents)}
                                            </td>
                                            <td className="min-w-32 px-4 py-4 text-xs leading-5 text-muted-foreground">
                                                {customer.last_activity
                                                    ? date(
                                                          customer.last_activity,
                                                      )
                                                    : 'Sin actividad registrada'}
                                            </td>
                                            <td className="py-2 pr-2">
                                                {previewButton(customer)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="divide-y md:hidden">
                            {customers.data.map((customer) => (
                                <article
                                    key={customer.id}
                                    className="space-y-3 p-4"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <Link
                                                className={
                                                    crmLink +
                                                    ' font-medium break-words'
                                                }
                                                href={customerProfileUrl(
                                                    customer.id,
                                                    listUrl().split('?')[1],
                                                )}
                                            >
                                                {customer.company}
                                            </Link>
                                            <p className="mt-1 text-xs break-words text-muted-foreground">
                                                {customer.contact_name ||
                                                    customer.email ||
                                                    'Sin contacto registrado'}
                                            </p>
                                        </div>
                                        {previewButton(customer)}
                                    </div>
                                    <div className="text-sm">
                                        <ProfileValue
                                            fact={
                                                customer.commercialProfile
                                                    .operating_scale
                                            }
                                        />
                                    </div>
                                    <dl className="grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Solicitudes
                                            </dt>
                                            <dd className="mt-1 tabular-nums">
                                                {customer.requests_count}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-muted-foreground">
                                                Pagado · MXN
                                            </dt>
                                            <dd className="mt-1 tabular-nums">
                                                {money(customer.paid_cents)}
                                            </dd>
                                        </div>
                                    </dl>
                                    <p className="text-xs text-muted-foreground">
                                        Última actividad:{' '}
                                        {customer.last_activity
                                            ? date(customer.last_activity)
                                            : 'sin registro'}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </>
                ) : (
                    <div className="flex min-h-60 flex-col items-center justify-center gap-3 p-6 text-center">
                        <Users className="size-7 text-muted-foreground" />
                        <h2 className="font-medium">
                            {activeFilters.length
                                ? 'No hay coincidencias'
                                : 'Tu directorio empieza con una solicitud'}
                        </h2>
                        <p className="max-w-md text-sm leading-6 text-muted-foreground">
                            {activeFilters.length
                                ? 'Prueba con otro nombre o retira un filtro. Las clasificaciones pendientes de confirmar no se incluyen en los filtros.'
                                : 'Las fichas se crean cuando un cliente envía su primera solicitud de cotización.'}
                        </p>
                        {activeFilters.length > 0 && (
                            <Button asChild variant="outline">
                                <Link href="/customers">Limpiar filtros</Link>
                            </Button>
                        )}
                    </div>
                )}
                <footer className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
                    <p className="text-xs text-muted-foreground">
                        Página {customers.current_page} de {customers.last_page}{' '}
                        · Hasta 24 clientes
                    </p>
                    <div className="flex gap-2">
                        {customers.prev_page_url ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={customers.prev_page_url}>
                                    <ChevronLeft />
                                    Anterior
                                </Link>
                            </Button>
                        ) : (
                            <Button size="sm" variant="outline" disabled>
                                <ChevronLeft />
                                Anterior
                            </Button>
                        )}
                        {customers.next_page_url ? (
                            <Button asChild size="sm" variant="outline">
                                <Link href={customers.next_page_url}>
                                    Siguiente
                                    <ChevronRight />
                                </Link>
                            </Button>
                        ) : (
                            <Button size="sm" variant="outline" disabled>
                                Siguiente
                                <ChevronRight />
                            </Button>
                        )}
                    </div>
                </footer>
            </section>
            <p className="text-xs leading-5 text-muted-foreground">
                Los filtros usan clasificaciones confirmadas y vigentes. Los
                pagos y la actividad no determinan la escala ni el potencial del
                cliente.
            </p>
            <Sheet
                open={!!preview}
                onOpenChange={(open) => {
                    if (!open) closePreview();
                }}
            >
                <SheetContent
                    className="w-full gap-0 data-[state=closed]:duration-200 data-[state=open]:duration-200 motion-reduce:animate-none sm:max-w-lg [&>button:last-child]:hidden"
                    onCloseAutoFocus={(event) => {
                        if (trigger.current) {
                            event.preventDefault();
                            trigger.current.focus();
                        }
                    }}
                >
                    <SheetHeader className="border-b p-5 pr-14">
                        <SheetTitle>Vista rápida del cliente</SheetTitle>
                        <SheetDescription>
                            Perfil vigente y resultados registrados.
                        </SheetDescription>
                    </SheetHeader>
                    <SheetClose asChild>
                        <Button
                            className="absolute top-4 right-4"
                            variant="ghost"
                            size="icon"
                            aria-label="Cerrar vista rápida"
                        >
                            <X />
                        </Button>
                    </SheetClose>
                    {preview && (
                        <>
                            <div className="min-h-0 flex-1 space-y-6 overflow-y-auto p-5">
                                <div className="space-y-1">
                                    <h2 className="text-xl font-semibold break-words">
                                        {preview.company}
                                    </h2>
                                    <p className="text-sm break-words text-muted-foreground">
                                        {preview.contact_name ||
                                            'Sin contacto registrado'}
                                    </p>
                                    <p className="text-sm break-words text-muted-foreground">
                                        {preview.email ||
                                            'Sin correo registrado'}
                                    </p>
                                </div>
                                <dl className="grid grid-cols-2 gap-x-5 gap-y-4 border-y py-5">
                                    {[
                                        ['Solicitudes', preview.requests_count],
                                        [
                                            'Última actividad',
                                            preview.last_activity
                                                ? date(preview.last_activity)
                                                : 'Sin registro',
                                        ],
                                        [
                                            'Formalizado · MXN',
                                            money(preview.formalized_cents),
                                        ],
                                        [
                                            'Pagado · MXN',
                                            money(preview.paid_cents),
                                        ],
                                    ].map(([label, value]) => (
                                        <div key={label}>
                                            <dt className="text-xs text-muted-foreground">
                                                {label}
                                            </dt>
                                            <dd className="mt-1 text-sm font-medium tabular-nums">
                                                {value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <section>
                                    <h3 className="mb-4 font-medium">
                                        Perfil comercial
                                    </h3>
                                    <dl className="space-y-4">
                                        {Object.entries(
                                            commercialDimensions,
                                        ).map(([key, definition]) => (
                                            <div key={key}>
                                                <dt className="mb-1 text-xs text-muted-foreground">
                                                    {definition.label}
                                                </dt>
                                                <dd className="text-sm leading-6">
                                                    <ProfileValue
                                                        fact={
                                                            preview
                                                                .commercialProfile[
                                                                key
                                                            ]
                                                        }
                                                    />
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                </section>
                            </div>
                            <div className="border-t p-5">
                                <Button asChild className="w-full">
                                    <Link
                                        href={customerProfileUrl(
                                            preview.id,
                                            listUrl().split('?')[1],
                                        )}
                                    >
                                        Abrir perfil y evidencia
                                        <ArrowUpRight />
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </CrmPage>
    );
}
