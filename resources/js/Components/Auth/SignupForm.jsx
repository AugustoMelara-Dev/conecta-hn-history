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

function safeRoute(name, fallback) {
    try {
        if (route().has(name)) {
            return route(name, undefined, false)
        }
    } catch {
        return fallback
    }

    return fallback
}

export function SignupForm({
    categories = [],
    cities = [],
    className,
    ...props
}) {
    const exploreUrl = safeRoute("directory.index", "/directorio")
    const inputClassName =
        "border-input bg-background text-foreground placeholder:text-muted-foreground focus-visible:ring-ring focus-visible:ring-offset-0"
    const selectClassName =
        "flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-0"

    const { data, setData, post, processing, errors } = useForm({
        business_name: "",
        category_id: "",
        city_id: "",
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        selected_plan: "free",
        terms_accepted: false,
    })

    const submit = (event) => {
        event.preventDefault()
        post(route("tenant.register.store", undefined, false))
    }

    return (
        <div className={cn("flex flex-col gap-6", className)}>
            <Card
                {...props}
                className={cn("border-border bg-card text-card-foreground shadow-sm", props.className)}
            >
                <CardHeader>
                    <CardTitle className="text-2xl font-semibold text-foreground">
                        Crea tu cuenta
                    </CardTitle>
                    <CardDescription className="text-muted-foreground">
                        Registra tu negocio y prepara el acceso administrativo.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit}>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="business_name">Nombre del negocio</FieldLabel>
                                <Input
                                    id="business_name"
                                    type="text"
                                    placeholder="Café La Esperanza"
                                    required
                                    value={data.business_name}
                                    onChange={(event) => setData("business_name", event.target.value)}
                                    className={cn(
                                        inputClassName,
                                        errors.business_name && "border-destructive focus-visible:ring-destructive/30"
                                    )}
                                />
                                <FieldDescription>
                                    Generaremos la URL pública de tu negocio desde este nombre.
                                </FieldDescription>
                                {errors.business_name ? (
                                    <FieldDescription className="text-destructive" role="alert">
                                        {errors.business_name}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="name">Nombre completo</FieldLabel>
                                <Input
                                    id="name"
                                    type="text"
                                    placeholder="Augusto Melara"
                                    required
                                    value={data.name}
                                    onChange={(event) => setData("name", event.target.value)}
                                    className={cn(
                                        inputClassName,
                                        errors.name && "border-destructive focus-visible:ring-destructive/30"
                                    )}
                                />
                                {errors.name ? (
                                    <FieldDescription className="text-destructive" role="alert">
                                        {errors.name}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="email">Correo</FieldLabel>
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="nombre@negocio.hn"
                                    required
                                    value={data.email}
                                    onChange={(event) => setData("email", event.target.value)}
                                    className={cn(
                                        inputClassName,
                                        errors.email && "border-destructive focus-visible:ring-destructive/30"
                                    )}
                                />
                                <FieldDescription>
                                    Usaremos este correo para avisos de acceso, facturación y seguridad.
                                </FieldDescription>
                                {errors.email ? (
                                    <FieldDescription className="text-destructive" role="alert">
                                        {errors.email}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    value={data.password}
                                    onChange={(event) => setData("password", event.target.value)}
                                    className={cn(
                                        inputClassName,
                                        errors.password && "border-destructive focus-visible:ring-destructive/30"
                                    )}
                                />
                                <FieldDescription>
                                    Debe tener al menos 8 caracteres.
                                </FieldDescription>
                                {errors.password ? (
                                    <FieldDescription className="text-destructive" role="alert">
                                        {errors.password}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="password_confirmation">Confirmar contraseña</FieldLabel>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    required
                                    value={data.password_confirmation}
                                    onChange={(event) => setData("password_confirmation", event.target.value)}
                                    className={cn(
                                        inputClassName,
                                        errors.password_confirmation && "border-destructive focus-visible:ring-destructive/30"
                                    )}
                                />
                                <FieldDescription>Escribe la misma contraseña para confirmarla.</FieldDescription>
                                {errors.password_confirmation ? (
                                    <FieldDescription className="text-destructive" role="alert">
                                        {errors.password_confirmation}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="category_id">Categoría</FieldLabel>
                                <select
                                    id="category_id"
                                    required
                                    value={data.category_id}
                                    onChange={(event) => setData("category_id", event.target.value)}
                                    className={cn(
                                        selectClassName,
                                        errors.category_id && "border-destructive focus-visible:ring-destructive/30"
                                    )}
                                >
                                    <option value="">Selecciona una categoría</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.category_id ? (
                                    <FieldDescription className="text-destructive" role="alert">
                                        {errors.category_id}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <FieldLabel htmlFor="city_id">Ciudad</FieldLabel>
                                <select
                                    id="city_id"
                                    required
                                    value={data.city_id}
                                    onChange={(event) => setData("city_id", event.target.value)}
                                    className={cn(
                                        selectClassName,
                                        errors.city_id && "border-destructive focus-visible:ring-destructive/30"
                                    )}
                                >
                                    <option value="">Selecciona una ciudad</option>
                                    {cities.map((city) => (
                                        <option key={city.id} value={city.id}>
                                            {city.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.city_id ? (
                                    <FieldDescription className="text-destructive" role="alert">
                                        {errors.city_id}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <label className="flex items-start gap-3 text-sm text-muted-foreground">
                                    <input
                                        type="checkbox"
                                        checked={data.terms_accepted}
                                        onChange={(event) => setData("terms_accepted", event.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded-sm border-input text-accent focus:ring-ring"
                                    />
                                    <span>Acepto los términos necesarios para crear esta cuenta de negocio.</span>
                                </label>
                                {errors.terms_accepted ? (
                                    <FieldDescription className="text-destructive" role="alert">
                                        {errors.terms_accepted}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-primary text-primary-foreground hover:bg-primary/90"
                                >
                                    {processing ? "Creando cuenta..." : "Crear cuenta"}
                                </Button>
                                <Button
                                    variant="outline"
                                    type="button"
                                    className="w-full border-border bg-background text-foreground hover:bg-muted"
                                    asChild
                                >
                                    <Link href={exploreUrl}>
                                        Explorar negocios
                                    </Link>
                                </Button>
                                <FieldDescription className="px-6 text-center">
                                    ¿Ya tienes una cuenta?{" "}
                                    <Link href="/login" className="underline underline-offset-4">
                                        Iniciar sesión
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
