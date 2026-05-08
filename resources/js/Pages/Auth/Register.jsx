import { Head, Link, useForm } from "@inertiajs/react"
import { ArrowRight, Globe, Store, UserRound } from "lucide-react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { Alert, AlertDescription } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
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
import { Separator } from "@/Components/ui/separator"
import { Spinner } from "@/Components/ui/spinner"
import { cn } from "@/lib/utils"

const generateSlug = (name) => {
    if (!name) return ""

    return name
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "")
        .substring(0, 50)
}

const errorId = (field) => `register-${field}-error`

function errorDescription(field, errors) {
    return errors[field] ? errorId(field) : undefined
}

function RegistrationField({
    id,
    label,
    error,
    description,
    children,
}) {
    return (
        <Field data-invalid={error ? "true" : undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            {children}
            {error ? (
                <FieldDescription id={errorId(id)} role="alert" className="text-destructive">
                    {error}
                </FieldDescription>
            ) : description ? (
                <FieldDescription>{description}</FieldDescription>
            ) : null}
        </Field>
    )
}

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        tenant_name: "",
        slug: "",
    })

    const handleTenantNameChange = (value) => {
        setData((previous) => ({
            ...previous,
            tenant_name: value,
            slug: generateSlug(value),
        }))
    }

    const submit = (event) => {
        event.preventDefault()

        post(route("register"), {
            onFinish: () => reset("password", "password_confirmation"),
        })
    }

    const inputClassName = (field) =>
        cn(errors[field] && "border-destructive/50 focus-visible:ring-destructive/20")

    return (
        <>
            <Head>
                <title>Registrar cuenta | Conecta HN</title>
                <meta
                    name="description"
                    content="Crea una cuenta de negocio en Conecta HN con URL publica, datos de acceso y validaciones claras."
                />
                <link rel="canonical" href={route("register")} />
            </Head>

            <AuthShadcnShell
                imageAlt="Acceso para registrar un negocio en Conecta HN"
                maxWidthClassName="max-w-xl"
                eyebrow="Registro de negocio"
                title="Crea una cuenta lista para operar"
                description="Define el nombre publico de tu negocio y el acceso propietario sin pasos innecesarios."
            >
                <Card className="overflow-hidden shadow-sm" data-testid="register-card">
                    <CardHeader className="gap-4 border-b bg-muted/30">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">Cuenta propietaria</Badge>
                            <Badge variant="outline">URL publica</Badge>
                        </div>
                        <div className="flex flex-col gap-2">
                            <CardTitle>Crea tu cuenta de negocio</CardTitle>
                            <CardDescription>
                                Prepara el acceso principal y la direccion publica que usaran tus clientes.
                            </CardDescription>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <form onSubmit={submit} data-testid="register-form">
                            <FieldGroup>
                                <Alert>
                                    <Store aria-hidden="true" />
                                    <AlertDescription>
                                        Para elegir rubro, ciudad y plan desde el inicio puedes usar{" "}
                                        <Link href={route("tenant.register")} className="font-medium underline underline-offset-4">
                                            registrar negocio
                                        </Link>
                                        .
                                    </AlertDescription>
                                </Alert>

                                <div className="flex flex-col gap-4">
                                    <div className="flex items-center gap-2">
                                        <Store aria-hidden="true" />
                                        <h2 className="text-sm font-semibold text-foreground">Identidad del negocio</h2>
                                    </div>

                                    <RegistrationField
                                        id="tenant_name"
                                        label="Nombre de tu negocio"
                                        error={errors.tenant_name}
                                        description="Generaremos una URL publica a partir de este nombre."
                                    >
                                        <Input
                                            id="tenant_name"
                                            name="tenant_name"
                                            value={data.tenant_name}
                                            autoComplete="organization"
                                            placeholder="Ej. Pizzeria Don Juan"
                                            onChange={(event) => handleTenantNameChange(event.target.value)}
                                            required
                                            aria-invalid={Boolean(errors.tenant_name)}
                                            aria-describedby={errorDescription("tenant_name", errors)}
                                            data-testid="register-tenant-name-input"
                                            className={inputClassName("tenant_name")}
                                        />
                                    </RegistrationField>

                                    <RegistrationField
                                        id="slug"
                                        label="Direccion web unica"
                                        error={errors.slug}
                                        description="Esta sera la URL permanente de tu negocio."
                                    >
                                        <div className="grid min-h-10 grid-cols-[auto_1fr] items-center rounded-md border border-input bg-background focus-within:ring-2 focus-within:ring-ring">
                                            <span className="flex h-full items-center border-r border-border px-3 text-sm text-muted-foreground">
                                                conectahn.com/
                                            </span>
                                            <Input
                                                id="slug"
                                                name="slug"
                                                value={data.slug}
                                                placeholder="pizzeria-don-juan"
                                                onChange={(event) => setData("slug", generateSlug(event.target.value))}
                                                required
                                                aria-invalid={Boolean(errors.slug)}
                                                aria-describedby={errorDescription("slug", errors)}
                                                data-testid="register-slug-input"
                                                className={cn("border-0 shadow-none focus-visible:ring-0", inputClassName("slug"))}
                                            />
                                        </div>
                                    </RegistrationField>
                                </div>

                                <Separator />

                                <div className="flex flex-col gap-4">
                                    <div className="flex items-center gap-2">
                                        <UserRound aria-hidden="true" />
                                        <h2 className="text-sm font-semibold text-foreground">Cuenta propietaria</h2>
                                    </div>

                                    <RegistrationField id="name" label="Nombre completo" error={errors.name}>
                                        <Input
                                            id="name"
                                            name="name"
                                            value={data.name}
                                            autoComplete="name"
                                            placeholder="Juan Perez"
                                            onChange={(event) => setData("name", event.target.value)}
                                            required
                                            aria-invalid={Boolean(errors.name)}
                                            aria-describedby={errorDescription("name", errors)}
                                            data-testid="register-name-input"
                                            className={inputClassName("name")}
                                        />
                                    </RegistrationField>

                                    <RegistrationField
                                        id="email"
                                        label="Correo electronico"
                                        error={errors.email}
                                        description="Usaremos este correo para acceso, avisos de seguridad y comunicacion de la cuenta."
                                    >
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            value={data.email}
                                            autoComplete="username"
                                            inputMode="email"
                                            spellCheck={false}
                                            placeholder="juan@ejemplo.com"
                                            onChange={(event) => setData("email", event.target.value)}
                                            required
                                            aria-invalid={Boolean(errors.email)}
                                            aria-describedby={errorDescription("email", errors)}
                                            data-testid="register-email-input"
                                            className={inputClassName("email")}
                                        />
                                    </RegistrationField>

                                    <RegistrationField
                                        id="password"
                                        label="Contrasena"
                                        error={errors.password}
                                        description="Debe tener al menos 8 caracteres."
                                    >
                                        <Input
                                            id="password"
                                            name="password"
                                            type="password"
                                            value={data.password}
                                            autoComplete="new-password"
                                            placeholder="Minimo 8 caracteres"
                                            onChange={(event) => setData("password", event.target.value)}
                                            required
                                            aria-invalid={Boolean(errors.password)}
                                            aria-describedby={errorDescription("password", errors)}
                                            data-testid="register-password-input"
                                            className={inputClassName("password")}
                                        />
                                    </RegistrationField>

                                    <RegistrationField
                                        id="password_confirmation"
                                        label="Confirmar contrasena"
                                        error={errors.password_confirmation}
                                        description="Escribe la misma contrasena para confirmarla."
                                    >
                                        <Input
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            type="password"
                                            value={data.password_confirmation}
                                            autoComplete="new-password"
                                            placeholder="Repite tu contrasena"
                                            onChange={(event) => setData("password_confirmation", event.target.value)}
                                            required
                                            aria-invalid={Boolean(errors.password_confirmation)}
                                            aria-describedby={errorDescription("password_confirmation", errors)}
                                            data-testid="register-password-confirmation-input"
                                            className={inputClassName("password_confirmation")}
                                        />
                                    </RegistrationField>
                                </div>

                                <Field>
                                    <Button type="submit" disabled={processing} data-testid="register-submit" className="w-full">
                                        {processing ? <Spinner data-icon="inline-start" /> : <ArrowRight data-icon="inline-start" aria-hidden="true" />}
                                        {processing ? "Creando tu negocio..." : "Crear mi cuenta"}
                                    </Button>
                                </Field>

                                <FieldSeparator>Acceso existente</FieldSeparator>

                                <Field>
                                    <Button variant="outline" type="button" className="w-full" asChild>
                                        <Link href={route("login")}>
                                            <Globe data-icon="inline-start" aria-hidden="true" />
                                            Iniciar sesion
                                        </Link>
                                    </Button>
                                    <FieldDescription className="text-center">
                                        Tambien puedes explorar negocios antes de crear tu cuenta.
                                    </FieldDescription>
                                </Field>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </AuthShadcnShell>
        </>
    )
}
