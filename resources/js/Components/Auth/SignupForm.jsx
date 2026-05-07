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

export function SignupForm({
    categories = [],
    cities = [],
    className,
    ...props
}) {
    const inputClassName =
        "border-slate-200 bg-white text-slate-950 placeholder:text-slate-400 focus-visible:ring-slate-300 focus-visible:ring-offset-0"
    const selectClassName =
        "flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-300 focus-visible:ring-offset-0"

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
        post(route("tenant.register.store"))
    }

    return (
        <div className={cn("flex flex-col gap-6", className)}>
            <Card
                {...props}
                className={cn("border-slate-200 bg-white text-slate-950 shadow-sm", props.className)}
            >
                <CardHeader>
                    <CardTitle className="text-2xl font-semibold text-slate-950">
                        Crea tu cuenta
                    </CardTitle>
                    <CardDescription className="text-slate-500">
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
                                        errors.business_name && "border-rose-300 focus-visible:ring-rose-200"
                                    )}
                                />
                                <FieldDescription>
                                    Generaremos la URL pública de tu negocio desde este nombre.
                                </FieldDescription>
                                {errors.business_name ? (
                                    <FieldDescription className="text-rose-600">
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
                                        errors.name && "border-rose-300 focus-visible:ring-rose-200"
                                    )}
                                />
                                {errors.name ? (
                                    <FieldDescription className="text-rose-600">
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
                                        errors.email && "border-rose-300 focus-visible:ring-rose-200"
                                    )}
                                />
                                <FieldDescription>
                                    Usaremos este correo para avisos de acceso, facturación y seguridad.
                                </FieldDescription>
                                {errors.email ? (
                                    <FieldDescription className="text-rose-600">
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
                                        errors.password && "border-rose-300 focus-visible:ring-rose-200"
                                    )}
                                />
                                <FieldDescription>
                                    Debe tener al menos 8 caracteres.
                                </FieldDescription>
                                {errors.password ? (
                                    <FieldDescription className="text-rose-600">
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
                                        errors.password_confirmation && "border-rose-300 focus-visible:ring-rose-200"
                                    )}
                                />
                                <FieldDescription>Escribe la misma contraseña para confirmarla.</FieldDescription>
                                {errors.password_confirmation ? (
                                    <FieldDescription className="text-rose-600">
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
                                        errors.category_id && "border-rose-300 focus-visible:ring-rose-200"
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
                                    <FieldDescription className="text-rose-600">
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
                                        errors.city_id && "border-rose-300 focus-visible:ring-rose-200"
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
                                    <FieldDescription className="text-rose-600">
                                        {errors.city_id}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <label className="flex items-start gap-3 text-sm text-slate-600">
                                    <input
                                        type="checkbox"
                                        checked={data.terms_accepted}
                                        onChange={(event) => setData("terms_accepted", event.target.checked)}
                                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-300"
                                    />
                                    <span>Acepto los términos necesarios para crear esta cuenta de negocio.</span>
                                </label>
                                {errors.terms_accepted ? (
                                    <FieldDescription className="text-rose-600">
                                        {errors.terms_accepted}
                                    </FieldDescription>
                                ) : null}
                            </Field>

                            <Field>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-slate-950 text-white hover:bg-slate-800"
                                >
                                    {processing ? "Creando cuenta..." : "Crear cuenta"}
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
                                <FieldDescription className="px-6 text-center">
                                    ¿Ya tienes una cuenta?{" "}
                                    <Link href={route("login")} className="underline underline-offset-4">
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
