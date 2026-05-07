import { Link, useForm } from "@inertiajs/react"

import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Button } from "@/Components/ui/button"
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldSeparator,
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { Spinner } from "@/Components/ui/spinner"
import { cn } from "@/lib/utils"

export function ResetPasswordForm({ token, email, className, ...props }) {
    const inputClassName =
        "h-10 border-slate-200 bg-white text-slate-950 placeholder:text-slate-400 focus-visible:ring-slate-300 focus-visible:ring-offset-0"

    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: "",
        password_confirmation: "",
    })

    const submit = (event) => {
        event.preventDefault()

        post(route("password.store"), {
            onFinish: () => reset("password", "password_confirmation"),
        })
    }

    return (
        <form onSubmit={submit} className={cn("flex flex-col gap-6", className)} {...props}>
            <FieldGroup>
                <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold text-slate-950">Cambia tu contraseña</h1>
                    <p className="text-sm text-balance text-slate-500">
                        Crea una contraseña nueva para recuperar el acceso a Conecta HN.
                    </p>
                </div>

                {errors.token ? (
                    <Alert variant="destructive" aria-live="assertive" data-testid="reset-password-token-error">
                        <AlertTitle>El enlace no se pudo validar</AlertTitle>
                        <AlertDescription>
                            {errors.token} Solicita un enlace nuevo para proteger tu cuenta.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Field>
                    <FieldLabel htmlFor="email">Correo</FieldLabel>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="username"
                        required
                        value={data.email}
                        onChange={(event) => setData("email", event.target.value)}
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={errors.email ? "reset-password-email-error" : undefined}
                        data-testid="reset-password-email-input"
                        className={cn(
                            inputClassName,
                            errors.email && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.email ? (
                        <FieldDescription id="reset-password-email-error" className="text-rose-600">
                            {errors.email}
                        </FieldDescription>
                    ) : null}
                </Field>

                <Field>
                    <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        required
                        value={data.password}
                        onChange={(event) => setData("password", event.target.value)}
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? "reset-password-password-error" : "reset-password-password-hint"}
                        data-testid="reset-password-password-input"
                        className={cn(
                            inputClassName,
                            errors.password && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.password ? (
                        <FieldDescription id="reset-password-password-error" className="text-rose-600">
                            {errors.password}
                        </FieldDescription>
                    ) : (
                        <FieldDescription id="reset-password-password-hint">
                            Debe tener al menos 8 caracteres.
                        </FieldDescription>
                    )}
                </Field>

                <Field>
                    <FieldLabel htmlFor="password_confirmation">Confirmar contraseña</FieldLabel>
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        required
                        value={data.password_confirmation}
                        onChange={(event) => setData("password_confirmation", event.target.value)}
                        aria-invalid={Boolean(errors.password_confirmation)}
                        aria-describedby={
                            errors.password_confirmation ? "reset-password-confirmation-error" : undefined
                        }
                        data-testid="reset-password-confirmation-input"
                        className={cn(
                            inputClassName,
                            errors.password_confirmation && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.password_confirmation ? (
                        <FieldDescription id="reset-password-confirmation-error" className="text-rose-600">
                            {errors.password_confirmation}
                        </FieldDescription>
                    ) : null}
                </Field>

                <Field>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-testid="reset-password-submit"
                        className="w-full bg-slate-950 text-white hover:bg-slate-800"
                    >
                        {processing ? <Spinner className="mr-2" /> : null}
                        {processing ? "Actualizando..." : "Actualizar contraseña"}
                    </Button>
                </Field>

                <FieldSeparator>Volver al acceso</FieldSeparator>

                <Field>
                    <Button
                        variant="outline"
                        type="button"
                        className="w-full border-slate-200 hover:bg-slate-50"
                        asChild
                    >
                        <Link href={route("login")}>Iniciar sesión</Link>
                    </Button>
                </Field>
            </FieldGroup>
        </form>
    )
}
