import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { Field, FieldDescription, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Spinner } from '@/Components/ui/spinner';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`flex flex-col gap-6 ${className}`}>
            <header>
                <h2 className="text-lg font-semibold text-foreground">
                    Eliminar cuenta
                </h2>

                <p className="mt-1 text-sm text-muted-foreground">
                    Esta acción elimina permanentemente tu cuenta y la información asociada. Descarga cualquier dato que necesites conservar antes de continuar.
                </p>
            </header>

            <Button type="button" variant="destructive" className="w-fit" onClick={confirmUserDeletion}>
                Eliminar cuenta
            </Button>

            <AlertDialog open={confirmingUserDeletion} onOpenChange={(open) => !open && closeModal()}>
                <AlertDialogContent>
                    <form onSubmit={deleteUser}>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                ¿Seguro que quieres eliminar tu cuenta?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Esta acción es permanente. Escribe tu contraseña para confirmar que quieres eliminar tu cuenta.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <Field className="mt-6" data-invalid={Boolean(errors.password)}>
                            <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                ref={passwordInput}
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                placeholder="Escribe tu contraseña…"
                                autoComplete="current-password"
                                aria-invalid={Boolean(errors.password)}
                                aria-describedby={errors.password ? 'delete-password-error' : undefined}
                            />

                            {errors.password ? (
                                <FieldDescription id="delete-password-error" role="alert" className="text-destructive">
                                    {errors.password}
                                </FieldDescription>
                            ) : null}
                        </Field>

                        <AlertDialogFooter className="mt-6">
                            <AlertDialogCancel type="button" onClick={closeModal}>
                                Cancelar
                            </AlertDialogCancel>

                            <AlertDialogAction
                                type="submit"
                                disabled={processing}
                                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            >
                                {processing ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                                Eliminar cuenta
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </form>
                </AlertDialogContent>
            </AlertDialog>
        </section>
    );
}
