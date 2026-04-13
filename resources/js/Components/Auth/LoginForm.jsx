import { Link, useForm } from "@inertiajs/react"

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
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { cn } from "@/lib/utils"

export function LoginForm({
    canResetPassword = true,
    status = null,
    className,
    ...props
}) {
    const inputClassName =
        "border-slate-200 bg-white text-slate-950 placeholder:text-slate-400 focus-visible:ring-slate-300 focus-visible:ring-offset-0"

    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false,
    })

    const submit = (event) => {
        event.preventDefault()

        post(route("login"), {
            onFinish: () => reset("password"),
        })
    }

    return (
        <div className={cn("flex flex-col gap-6", className)} {...props}>
            <Card className="border-slate-200 bg-white text-slate-950 shadow-sm">
                <CardHeader>
                    <CardTitle className="text-2xl font-semibold text-slate-950">
                        Inicia sesion en tu cuenta
                    </CardTitle>
                    <CardDescription className="text-slate-500">
                        Accede con tu correo y contrasena para administrar tu negocio.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} data-testid="login-form">
                        <FieldGroup>
                            {status ? (
                                <div aria-live="polite" className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    {status}
                                </div>
                            ) : null}

                            <Field>
                                <FieldLabel htmlFor="email">Correo</FieldLabel>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    placeholder="nombre@negocio.hn"
                                    autoComplete="email"
                                    spellCheck={false}
                                    required
                                    autoFocus
                                    value={data.email}
                                    onChange={(event) => setData("email", event.target.value)}
                                    data-testid="login-email-input"
                                    className={cn(
                                        inputClassName,
                                        errors.email && "border-rose-300 focus-visible:ring-rose-200"
                                    )}
                                />
                                {errors.email ? (
                                    <FieldDescription className="text-rose-600">
                                        {errors.email}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <div className="flex items-center">
                                    <FieldLabel htmlFor="password">Contrasena</FieldLabel>
                                    {canResetPassword ? (
                                        <Link
                                            href={route("password.request")}
                                            className="ml-auto inline-block text-sm underline-offset-4 hover:underline"
                                        >
                                            Olvidaste tu contrasena?
                                        </Link>
                                    ) : null}
                                </div>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                    value={data.password}
                                    onChange={(event) => setData("password", event.target.value)}
                                    data-testid="login-password-input"
                                    className={cn(
                                        inputClassName,
                                        errors.password && "border-rose-300 focus-visible:ring-rose-200"
                                    )}
                                />
                                {errors.password ? (
                                    <FieldDescription className="text-rose-600">
                                        {errors.password}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-testid="login-submit"
                                    className="w-full bg-slate-950 text-white hover:bg-slate-800"
                                >
                                    {processing ? "Entrando..." : "Iniciar sesion"}
                                </Button>
                                <Button
                                    variant="outline"
                                    type="button"
                                    className="w-full border-slate-200 bg-white text-slate-950 hover:bg-slate-50"
                                    asChild
                                >
                                    <Link href={route("public.explore")}>
                                        Explorar negocios
                                    </Link>
                                </Button>
                                <FieldDescription className="text-center">
                                    No tienes cuenta?{" "}
                                    <Link
                                        href={route("tenant.register")}
                                        className="underline underline-offset-4"
                                    >
                                        Registrar negocio
                                    </Link>
                                </FieldDescription>
                            </Field>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
        </div>
    )
}
