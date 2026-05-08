import { useForm } from '@inertiajs/react';
import { AlertTriangle, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Spinner } from '@/Components/ui/spinner';

function safeRoutePath(name, fallbackPath) {
    try {
        if (route().has(name)) {
            return new URL(route(name), window.location.origin).pathname;
        }
    } catch {
        return fallbackPath;
    }

    return fallbackPath;
}

export default function DeleteUserForm() {
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

    const closeDialog = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    const deleteUser = (event) => {
        event.preventDefault();

        destroy(safeRoutePath('mi-cuenta.destroy', '/mi-cuenta'), {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <div className="flex flex-col gap-5">
            <Alert variant="destructive">
                <AlertTriangle aria-hidden="true" />
                <AlertTitle>Accion permanente</AlertTitle>
                <AlertDescription>
                    Al eliminar tu cuenta se cerrara la sesion y se perderan los datos asociados.
                </AlertDescription>
            </Alert>

            <div>
                <Button
                    type="button"
                    variant="destructive"
                    onClick={() => setConfirmingUserDeletion(true)}
                >
                    <Trash2 data-icon="inline-start" aria-hidden="true" />
                    Eliminar cuenta
                </Button>
            </div>

            <Dialog open={confirmingUserDeletion} onOpenChange={(open) => (open ? setConfirmingUserDeletion(true) : closeDialog())}>
                <DialogContent data-testid="profile-delete-dialog">
                    <DialogHeader>
                        <DialogTitle>Confirma la eliminacion de tu cuenta</DialogTitle>
                        <DialogDescription>
                            Esta accion no se puede deshacer. Escribe tu contrasena para confirmar que deseas eliminar tu cuenta.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={deleteUser}>
                        <FieldGroup>
                            <Field data-invalid={Boolean(errors.password)}>
                                <FieldLabel htmlFor="delete-account-password">Contrasena</FieldLabel>
                                <Input
                                    id="delete-account-password"
                                    ref={passwordInput}
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    autoComplete="current-password"
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={errors.password ? 'delete-account-password-error' : undefined}
                                    data-testid="profile-delete-password-input"
                                />
                                {errors.password ? (
                                    <FieldDescription id="delete-account-password-error" role="alert" className="text-destructive">
                                        {errors.password}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <DialogFooter className="gap-2 sm:gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        <X data-icon="inline-start" aria-hidden="true" />
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button type="submit" variant="destructive" disabled={processing}>
                                    {processing ? <Spinner data-icon="inline-start" /> : <Trash2 data-icon="inline-start" aria-hidden="true" />}
                                    Eliminar definitivamente
                                </Button>
                            </DialogFooter>
                        </FieldGroup>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
