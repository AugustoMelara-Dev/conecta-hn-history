import { Link, useForm } from "@inertiajs/react"

import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Button } from "@/Components/ui/button"
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card"
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
        <Card className={cn("overflow-hidden shadow-sm", className)} {...props}>
            <CardHeader className="gap-2 border-b bg-muted/30 text-center">
                <CardTitle>Cambia tu contrasena</CardTitle>
                <CardDescription>
                    Crea una contrasena nueva para recuperar el acceso a Vito Business OS.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-5 sm:p-6">
                <form onSubmit={submit}>
                    <FieldGroup>
                        {errors.token ? (
                            <Alert variant="destructive" aria-live="assertive" data-testid="reset-password-token-error">
                                <AlertTitle>El enlace no se pudo validar</AlertTitle>
                                <AlertDescription>
                                    {errors.token} Solicita un enlace nuevo para proteger tu cuenta.
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <Field data-invalid={Boolean(errors.email)}>
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
                                className={cn(errors.email && "border-destructive/50 focus-visible:ring-destructive/20")}
                            />
                            {errors.email ? (
                                <FieldDescription id="reset-password-email-error" role="alert" className="text-destructive">
                                    {errors.email}
                                </FieldDescription>
                            ) : null}
                        </Field>

                        <Field data-invalid={Boolean(errors.password)}>
                            <FieldLabel htmlFor="password">Contrasena</FieldLabel>
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
                                className={cn(errors.password && "border-destructive/50 focus-visible:ring-destructive/20")}
                            />
                            {errors.password ? (
                                <FieldDescription id="reset-password-password-error" role="alert" className="text-destructive">
                                    {errors.password}
                                </FieldDescription>
                            ) : (
                                <FieldDescription id="reset-password-password-hint">
                                    Debe tener al menos 8 caracteres.
                                </FieldDescription>
                            )}
                        </Field>

                        <Field data-invalid={Boolean(errors.password_confirmation)}>
                            <FieldLabel htmlFor="password_confirmation">Confirmar contrasena</FieldLabel>
                            <Input
                                id="password_confirmation"
                                type="password"
                                autoComplete="new-password"
                                required
                                value={data.password_confirmation}
                                onChange={(event) => setData("password_confirmation", event.target.value)}
                                aria-invalid={Boolean(errors.password_confirmation)}
                                aria-describedby={errors.password_confirmation ? "reset-password-confirmation-error" : undefined}
                                data-testid="reset-password-confirmation-input"
                                className={cn(errors.password_confirmation && "border-destructive/50 focus-visible:ring-destructive/20")}
                            />
                            {errors.password_confirmation ? (
                                <FieldDescription id="reset-password-confirmation-error" role="alert" className="text-destructive">
                                    {errors.password_confirmation}
                                </FieldDescription>
                            ) : null}
                        </Field>

                        <Field>
                            <Button type="submit" disabled={processing} data-testid="reset-password-submit" className="w-full">
                                {processing ? <Spinner data-icon="inline-start" /> : null}
                                {processing ? "Actualizando..." : "Actualizar contrasena"}
                            </Button>
                        </Field>

                        <FieldSeparator>Volver al acceso</FieldSeparator>

                        <Field>
                            <Button variant="outline" type="button" className="w-full" asChild>
                                <Link href={route("login")}>Iniciar sesion</Link>
                            </Button>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    )
}
