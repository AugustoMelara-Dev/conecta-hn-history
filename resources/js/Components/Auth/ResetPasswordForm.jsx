import { Link, useForm } from "@inertiajs/react"

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
                        Crea una contraseña nueva para recuperar el acceso a Vito Business OS.
                    </p>
                </div>

                <Field>
                    <FieldLabel htmlFor="email">Correo</FieldLabel>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="username"
                        required
                        value={data.email}
                        onChange={(event) => setData("email", event.target.value)}
                        className={cn(
                            inputClassName,
                            errors.email && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.email ? (
                        <FieldDescription className="text-rose-600">{errors.email}</FieldDescription>
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
                        className={cn(
                            inputClassName,
                            errors.password && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.password ? (
                        <FieldDescription className="text-rose-600">{errors.password}</FieldDescription>
                    ) : (
                        <FieldDescription>Debe tener al menos 8 caracteres.</FieldDescription>
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
                        className={cn(
                            inputClassName,
                            errors.password_confirmation && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.password_confirmation ? (
                        <FieldDescription className="text-rose-600">
                            {errors.password_confirmation}
                        </FieldDescription>
                    ) : null}
                </Field>

                <Field>
                    <Button
                        type="submit"
                        disabled={processing}
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
