import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, ChevronDown } from 'lucide-react';
import { Field, FormErrors } from '@/components/byecode/workspace-page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectItem,
} from '@/components/ui/select';

export function EventForm({
    caseId,
    revision,
    contactAllowed,
    contactNotBefore,
}: {
    caseId: number;
    revision: number;
    contactAllowed: boolean;
    contactNotBefore: string | null;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        expected_revision: revision,
        type: 'message.received',
        message: '',
        contact_allowed: contactAllowed,
        contact_not_before: contactNotBefore ?? '',
        action: 'contact',
        execution_reference: '',
        status: 'accepted',
        confirmed: false,
        topic: '',
        note: '',
        source: '',
        verified_until: '',
    });
    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="rounded-xl border"
        >
            <CollapsibleTrigger asChild>
                <Button
                    variant="ghost"
                    className="h-auto w-full justify-between p-5"
                >
                    <span className="flex items-center gap-2">
                        <Plus className="size-4" />
                        Registrar información del caso
                    </span>
                    <ChevronDown className="size-4" />
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <form
                    className="space-y-5 border-t p-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((d) => ({
                            ...d,
                            expected_revision: revision,
                        }));
                        form.post('/cases/' + caseId + '/events', {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset('message', 'note', 'confirmed');
                                setOpen(false);
                            },
                        });
                    }}
                >
                    <FormErrors errors={form.errors} />
                    <Field label="Tipo de registro" htmlFor="event-type">
                        <Select
                            value={form.data.type}
                            onValueChange={(v) => form.setData('type', v)}
                        >
                            <SelectTrigger id="event-type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="message.received">
                                    Mensaje del comprador
                                </SelectItem>
                                <SelectItem value="constraint.updated">
                                    Permiso y fecha de contacto
                                </SelectItem>
                                <SelectItem value="action.executed">
                                    Acción que ya se realizó
                                </SelectItem>
                                <SelectItem value="observation.recorded">
                                    Validación operativa
                                </SelectItem>
                                <SelectItem value="case.closed">
                                    Cerrar el caso definitivamente
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    {form.data.type === 'message.received' && (
                        <Field label="Mensaje literal" htmlFor="message">
                            <Textarea
                                id="message"
                                required
                                value={form.data.message}
                                onChange={(e) =>
                                    form.setData('message', e.target.value)
                                }
                                placeholder="Conserva las condiciones y negaciones del mensaje original."
                            />
                        </Field>
                    )}
                    {form.data.type === 'constraint.updated' && (
                        <>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="contact-allowed"
                                    checked={form.data.contact_allowed}
                                    onCheckedChange={(v) =>
                                        form.setData(
                                            'contact_allowed',
                                            v === true,
                                        )
                                    }
                                />
                                <Label htmlFor="contact-allowed">
                                    Contacto permitido
                                </Label>
                            </div>
                            <Field
                                label="No contactar antes de (UTC)"
                                htmlFor="contact-date"
                                hint="Usa fecha ISO UTC con Z. Dejar vacío elimina la fecha en operación comercial."
                            >
                                <Input
                                    id="contact-date"
                                    value={form.data.contact_not_before}
                                    onChange={(e) =>
                                        form.setData(
                                            'contact_not_before',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="2026-10-12T15:00:00Z"
                                />
                            </Field>
                        </>
                    )}
                    {form.data.type === 'action.executed' && (
                        <>
                            <Field label="Acción realizada" htmlFor="executed">
                                <Select
                                    value={form.data.action}
                                    onValueChange={(v) =>
                                        form.setData('action', v)
                                    }
                                >
                                    <SelectTrigger id="executed">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="contact">
                                            Seguimiento
                                        </SelectItem>
                                        <SelectItem value="request_information">
                                            Solicitud de información
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field
                                label="Evidencia de ejecución"
                                htmlFor="execution"
                                hint="Referencia del contacto que ya ocurrió. Registrar no envía nada."
                            >
                                <Input
                                    id="execution"
                                    required
                                    value={form.data.execution_reference}
                                    onChange={(e) =>
                                        form.setData(
                                            'execution_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </>
                    )}
                    {form.data.type === 'case.closed' && (
                        <>
                            <Field
                                label="Resultado confirmado"
                                htmlFor="closed-status"
                            >
                                <Select
                                    value={form.data.status}
                                    onValueChange={(v) =>
                                        form.setData('status', v)
                                    }
                                >
                                    <SelectTrigger id="closed-status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="accepted">
                                            Aceptada
                                        </SelectItem>
                                        <SelectItem value="rejected">
                                            Rechazada
                                        </SelectItem>
                                        <SelectItem value="cancelled">
                                            Cancelada
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <p className="text-sm text-muted-foreground">
                                Una aceptación parcial o condicionada debe
                                registrarse como mensaje y revisión. El cierre
                                es terminal.
                            </p>
                        </>
                    )}
                    {form.data.type === 'observation.recorded' && (
                        <>
                            <Field label="Tema verificado" htmlFor="topic">
                                <Input
                                    id="topic"
                                    required
                                    value={form.data.topic}
                                    onChange={(e) =>
                                        form.setData('topic', e.target.value)
                                    }
                                    placeholder="Capacidad, inventario, condiciones…"
                                />
                            </Field>
                            <Field
                                label="Resultado de la validación"
                                htmlFor="note"
                            >
                                <Textarea
                                    id="note"
                                    required
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                label="Fuente / responsable"
                                htmlFor="event-source"
                            >
                                <Input
                                    id="event-source"
                                    required
                                    value={form.data.source}
                                    onChange={(e) =>
                                        form.setData('source', e.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                label="Válida hasta (UTC)"
                                htmlFor="verified-until"
                            >
                                <Input
                                    id="verified-until"
                                    required
                                    value={form.data.verified_until}
                                    onChange={(e) =>
                                        form.setData(
                                            'verified_until',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="2026-10-12T15:00:00Z"
                                />
                            </Field>
                        </>
                    )}
                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="event-confirmed"
                            checked={form.data.confirmed}
                            onCheckedChange={(v) =>
                                form.setData('confirmed', v === true)
                            }
                        />
                        <Label
                            htmlFor="event-confirmed"
                            className="text-sm leading-relaxed"
                        >
                            Confirmo que este registro representa información
                            real y autorizada.
                        </Label>
                    </div>
                    <Button disabled={form.processing || !form.data.confirmed}>
                        Guardar evento
                    </Button>
                </form>
            </CollapsibleContent>
        </Collapsible>
    );
}
