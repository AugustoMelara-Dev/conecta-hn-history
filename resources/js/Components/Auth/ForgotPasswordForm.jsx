import { Link, useForm } from "@inertiajs/react"

import { Alert, AlertDescription } from "@/Components/ui/alert"
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

export function ForgotPasswordForm({ status = null, className, ...props }) {
    const inputClassName =
        "h-10 border-slate-200 bg-white text-slate-950 placeholder:text-slate-400 focus-visible:ring-slate-300 focus-visible:ring-offset-0"

    const { data, setData, post, processing, errors } = useForm({
        email: "",
    })

    const submit = (event) => {
        event.preventDefault()
        post(route("password.email"))
    }

    return (
        <form onSubmit={submit} className={cn("flex flex-col gap-6", className)} {...props}>
            <FieldGroup>
                <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold text-slate-950" data-testid="forgot-password-heading">
                        Recupera tu acceso
                    </h1>
                    <p className="text-sm text-balance text-slate-500">
                        Escribe tu correo y enviaremos un enlace seguro para cambiar la contraseña.
                    </p>
                </div>

                {status ? (
                    <Alert data-testid="forgot-password-feedback">
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                ) : null}

                <Field>
                    <FieldLabel htmlFor="email">Correo</FieldLabel>
                    <Input
                        id="email"
                        type="email"
                        placeholder="nombre@negocio.hn"
                        autoComplete="email"
                        autoFocus
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
                    <Button
                        type="submit"
                        disabled={processing}
                        data-testid="forgot-password-submit"
                        className="w-full bg-slate-950 text-white hover:bg-slate-800"
                    >
                        {processing ? <Spinner className="mr-2" /> : null}
                        {processing ? "Enviando..." : "Enviar enlace"}
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
