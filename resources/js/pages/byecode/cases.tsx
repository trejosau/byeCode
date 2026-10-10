import { Link, useForm } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { CaseTable } from '@/components/byecode/case-table';
import { WorkspacePage } from '@/components/byecode/workspace-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';
import type { CaseRow } from '@/types/byecode';

export default function Cases({
    cases,
    filters,
}: {
    cases: CaseRow[];
    filters: { search?: string; status?: string; mode?: string };
}) {
    const form = useForm({
        search: filters.search ?? '',
        status: filters.status ?? 'all',
        mode: filters.mode ?? 'all',
    });
    return (
        <WorkspacePage
            title="Bandeja de cotizaciones"
            description="Contexto, restricciones y siguiente decisión de cada oportunidad."
            actions={
                <Button asChild>
                    <Link href="/quotes/create">
                        <Plus />
                        Nueva cotización
                    </Link>
                </Button>
            }
        >
            <form
                className="flex flex-wrap items-center gap-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.get('/cases', { preserveState: true });
                }}
            >
                <div className="relative min-w-60 flex-1">
                    <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                    <Input
                        aria-label="Buscar cotización"
                        className="pl-9"
                        placeholder="Buscar por folio o título…"
                        value={form.data.search}
                        onChange={(e) => form.setData('search', e.target.value)}
                    />
                </div>
                <Select
                    value={form.data.status}
                    onValueChange={(v) => form.setData('status', v)}
                >
                    <SelectTrigger className="w-44" aria-label="Estado">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los estados</SelectItem>
                        <SelectItem value="open">Abiertas</SelectItem>
                        <SelectItem value="accepted">Aceptadas</SelectItem>
                        <SelectItem value="rejected">Rechazadas</SelectItem>
                        <SelectItem value="cancelled">Canceladas</SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    value={form.data.mode}
                    onValueChange={(v) => form.setData('mode', v)}
                >
                    <SelectTrigger className="w-44" aria-label="Origen">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los orígenes</SelectItem>
                        <SelectItem value="live">
                            Operación comercial
                        </SelectItem>
                        <SelectItem value="kit">Kit de prueba</SelectItem>
                    </SelectContent>
                </Select>
                <Button variant="outline" disabled={form.processing}>
                    Aplicar filtros
                </Button>
            </form>
            <CaseTable cases={cases} />
            <p className="text-xs text-muted-foreground">
                {cases.length} casos visibles · Se muestran hasta 200
                coincidencias. El orden es por actualización, no por
                probabilidad de compra.
            </p>
        </WorkspacePage>
    );
}
Cases.layout = { breadcrumbs: [{ title: 'Cotizaciones', href: '/cases' }] };
