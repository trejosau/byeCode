import { useForm, Link } from '@inertiajs/react';
import { Journey, Errors, inputClass } from '@/components/byecode/journey';
import { Button } from '@/components/ui/button';
export default function InitialPassword({ expired }: { expired: boolean }) {
    const form = useForm({ password: '', password_confirmation: '' });
    return (
        <Journey
            title="Elige tu contraseña"
            description="Tu acceso inicial es temporal. Crea una contraseña de al menos 12 caracteres con letras y números."
        >
            {expired ? (
                <p>
                    El acceso temporal venció.{' '}
                    <Link className="underline" href="/forgot-password">
                        Recuperar contraseña
                    </Link>
                </p>
            ) : (
                <form
                    className="max-w-lg space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post('/initial-password');
                    }}
                >
                    <Errors errors={form.errors} />
                    <label className="block">
                        Nueva contraseña
                        <input
                            className={inputClass}
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                    </label>
                    <label className="block">
                        Confirmación
                        <input
                            className={inputClass}
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setData(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                        />
                    </label>
                    <Button disabled={form.processing}>
                        Guardar contraseña
                    </Button>
                </form>
            )}
        </Journey>
    );
}
