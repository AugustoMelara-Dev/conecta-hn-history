import { Link, useForm } from "@inertiajs/react"
import { Building2, Mail, ShieldCheck } from "lucide-react"

import { QuickAccessForm } from "@/Components/Auth/QuickAccessForm"
import { Alert, AlertDescription } from "@/Components/ui/alert"
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
import { Spinner } from "@/Components/ui/spinner"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs"
import { cn } from "@/lib/utils"

export function LoginForm({
    canResetPassword = true,
    status = null,
    className,
    ...props
}) {
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
        <Card className={cn("overflow-hidden border-slate-200 bg-white text-slate-950 shadow-xl shadow-slate-200/60", className)} {...props}>
            <CardHeader className="space-y-3 border-b border-slate-100 bg-slate-50/70">
                <div className="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <ShieldCheck className="h-3.5 w-3.5 text-emerald-600" />
                    Acceso seguro
                </div>
                <div>
                    <CardTitle className="text-2xl font-semibold text-slate-950">
                        Entra a Vito Business OS
                    </CardTitle>
                    <CardDescription className="mt-2 text-slate-600">
                        Usa contraseña para el panel de negocio o acceso rápido con correo y código.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent className="p-5 sm:p-6">
                <Tabs defaultValue="password" className="w-full">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="password" className="gap-2">
                            <Building2 className="h-4 w-4" />
                            Acceso con correo
                        </TabsTrigger>
                        <TabsTrigger value="quick" className="gap-2">
                            <Mail className="h-4 w-4" />
                            Acceso rápido
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="password" className="mt-6">
                        <form onSubmit={submit} data-testid="login-form">
                            <FieldGroup>
                                {status ? (
                                    <Alert>
                                        <AlertDescription>{status}</AlertDescription>
                                    </Alert>
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
                                        className={cn(errors.email && "border-rose-300 focus-visible:ring-rose-200")}
                                    />
                                    {errors.email ? (
                                        <FieldDescription className="text-rose-600">
                                            {errors.email}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Field>
                                    <div className="flex items-center">
                                        <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                                        {canResetPassword ? (
                                            <Link
                                                href={route("password.request")}
                                                className="ml-auto text-sm text-slate-600 underline-offset-4 hover:text-slate-950 hover:underline"
                                            >
                                                ¿Olvidaste tu contraseña?
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
                                        className={cn(errors.password && "border-rose-300 focus-visible:ring-rose-200")}
                                    />
                                    {errors.password ? (
                                        <FieldDescription className="text-rose-600">
                                            {errors.password}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Field>
                                    <Button type="submit" disabled={processing} data-testid="login-submit" className="w-full">
                                        {processing ? <Spinner className="mr-2" /> : null}
                                        {processing ? "Entrando..." : "Iniciar sesión"}
                                    </Button>
                                    <FieldDescription className="text-center">
                                        ¿No tienes cuenta?{" "}
                                        <Link href={route("tenant.register")} className="font-medium underline underline-offset-4">
                                            Registrar negocio
                                        </Link>
                                    </FieldDescription>
                                </Field>
                            </FieldGroup>
                        </form>
                    </TabsContent>

                    <TabsContent value="quick" className="mt-6">
                        <QuickAccessForm />
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>
    )
}
