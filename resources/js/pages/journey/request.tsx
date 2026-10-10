import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    Journey,
    Errors,
    inputClass,
    type Variant,
} from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
export default function RequestPage({
    selection,
    variants,
    physical,
}: {
    selection: { product_id: number; quantity: number }[];
    variants: Variant[];
    physical: boolean;
}) {
    const [submissionId] = useState(() => crypto.randomUUID());
    const form = useForm({
        submission_id: submissionId,
        lines: selection,
        name: '',
        email: '',
        company: '',
        message: '',
        desired_date: '',
    });
    return (
        <Journey
            title={physical ? 'Cotización presencial' : 'Tu solicitud'}
            description={
                physical
                    ? 'Registra al comprador durante la atención presencial. Una cuenta existente conservará su acceso.'
                    : 'Confirma las cantidades. Los demás detalles los resolveremos contigo en la conversación.'
            }
        >
            <form
                className="max-w-3xl space-y-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(physical ? '/physical-request' : '/request', {
                        onSuccess: () => form.reset(),
                    });
                }}
            >
                <Errors errors={form.errors} />
                {physical && (
                    <>
                        <div className="rounded-lg border bg-muted p-4">
                            El cliente recibirá un correo con su contraseña
                            temporal. Deberá cambiarla para entrar.
                        </div>
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
                    </>
                )}
                <label className="block">
                    Empresa (opcional)
                    <input
                        className={inputClass}
                        value={form.data.company}
                        onChange={(e) =>
                            form.setData('company', e.target.value)
                        }
                    />
                </label>
                {form.data.lines.map((line, index) => {
                    const v = variants.find((v) => v.id === line.product_id);
                    return (
                        <div
                            key={line.product_id}
                            className="flex flex-wrap items-center gap-4 rounded-lg border p-4 sm:flex-nowrap"
                        >
                            {v?.details?.images?.[0] && (
                                <img
                                    src={v.details.images[0].path}
                                    alt={`${v.name} · ${v.color}`}
                                    className="h-20 w-16 rounded-md bg-[#e7e7e4] object-contain"
                                />
                            )}
                            <p className="w-full sm:mr-auto sm:w-auto sm:flex-1">
                                {v
                                    ? `${v.name} · ${v.gsm ?? ''} g/m² · ${v.color} · ${v.size}`
                                    : 'Variante retirada del catálogo'}
                                {v?.moq && line.quantity < v.moq ? (
                                    <span className="block text-sm text-muted-foreground">
                                        Solicitud de excepción al mínimo {v.moq}
                                    </span>
                                ) : null}
                            </p>
                            <input
                                aria-label="Cantidad"
                                type="number"
                                min="1"
                                className={inputClass + ' max-w-28'}
                                value={line.quantity}
                                onChange={(e) =>
                                    form.setData(
                                        'lines',
                                        form.data.lines.map((l, i) =>
                                            i === index
                                                ? {
                                                      ...l,
                                                      quantity: Number(
                                                          e.target.value,
                                                      ),
                                                  }
                                                : l,
                                        ),
                                    )
                                }
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    form.setData(
                                        'lines',
                                        form.data.lines.filter(
                                            (_, i) => i !== index,
                                        ),
                                    )
                                }
                            >
                                Quitar
                            </Button>
                        </div>
                    );
                })}
                <Link
                    href="/catalog"
                    className="inline-flex min-h-10 items-center underline underline-offset-4"
                >
                    Configurar prendas en el catálogo
                </Link>
                <label className="block">
                    Fecha deseada (opcional)
                    <input
                        type="date"
                        className={inputClass}
                        value={form.data.desired_date}
                        onChange={(e) =>
                            form.setData('desired_date', e.target.value)
                        }
                    />
                </label>
                <label className="block">
                    ¿Algo que debamos saber? (opcional)
                    <textarea
                        className={inputClass}
                        rows={4}
                        value={form.data.message}
                        onChange={(e) =>
                            form.setData('message', e.target.value)
                        }
                    />
                </label>
                <Button disabled={form.processing || !form.data.lines.length}>
                    Enviar solicitud
                </Button>
            </form>
        </Journey>
    );
}
