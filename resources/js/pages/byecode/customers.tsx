import { useForm } from '@inertiajs/react';
import { Plus, Building2 } from 'lucide-react';
import {
    WorkspacePage,
    Field,
    FormErrors,
    EmptyState,
} from '@/components/byecode/workspace-page';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from '@/components/ui/table';
import type { Customer } from '@/types/byecode';

export default function Customers({ customers }: { customers: Customer[] }) {
    const form = useForm({
        company: '',
        contact_name: '',
        email: '',
        notes: '',
    });
    return (
        <WorkspacePage
            title="Clientes"
            description="La relación comercial empieza con información que puedas verificar."
        >
            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <section>
                    {customers.length ? (
                        <div className="rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Empresa</TableHead>
                                        <TableHead>Contacto</TableHead>
                                        <TableHead>Correo</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {customers.map((c) => (
                                        <TableRow key={c.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2 font-medium">
                                                    <Building2 className="size-4 text-muted-foreground" />
                                                    {c.company}
                                                </div>
                                                {c.notes && (
                                                    <p className="mt-2 max-w-sm text-xs text-muted-foreground">
                                                        {c.notes}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {c.contact_name ??
                                                    'Sin registrar'}
                                            </TableCell>
                                            <TableCell>
                                                {c.email ?? 'Sin registrar'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <EmptyState
                            title="Aún no hay clientes"
                            description="Registra la primera empresa compradora para crear una cotización a su nombre."
                        />
                    )}
                </section>
                <Card className="shadow-none">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Agregar cliente
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            className="space-y-5"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/customers', {
                                    onSuccess: () => form.reset(),
                                });
                            }}
                        >
                            <FormErrors errors={form.errors} />
                            <Field label="Empresa" htmlFor="company">
                                <Input
                                    id="company"
                                    required
                                    value={form.data.company}
                                    onChange={(e) =>
                                        form.setData('company', e.target.value)
                                    }
                                    placeholder="Nombre comercial"
                                />
                            </Field>
                            <Field
                                label="Persona de contacto"
                                htmlFor="contact"
                            >
                                <Input
                                    id="contact"
                                    value={form.data.contact_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'contact_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field label="Correo" htmlFor="email">
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Notas verificadas" htmlFor="notes">
                                <Textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                />
                            </Field>
                            <Button
                                disabled={form.processing}
                                className="w-full"
                            >
                                <Plus />
                                Guardar cliente
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </WorkspacePage>
    );
}
Customers.layout = { breadcrumbs: [{ title: 'Clientes', href: '/customers' }] };
