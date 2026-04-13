import { Link, useForm } from "@inertiajs/react"
import { useEffect, useMemo, useState } from "react"
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    BarChart3,
    Building2,
    Check,
    CheckCircle2,
    LoaderCircle,
    MapPin,
    ShieldCheck,
    Sparkles,
    Store,
    UserRound,
} from "lucide-react"

import AuthSplitLayout, {
    AuthAsideCard,
    AuthAsideFeature,
} from "@/Components/Auth/AuthSplitLayout"
import { Button } from "@/Components/ui/button"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import {
    PUBLIC_BODY_FONT,
    PUBLIC_DISPLAY_FONT,
    PUBLIC_LABEL_FONT,
} from "@/lib/publicTypography"

const STEPS = [
    { key: "business", label: "Info", title: "Información del negocio", icon: Building2 },
    { key: "location", label: "Loc", title: "Ubicación y contacto", icon: MapPin },
    { key: "account", label: "User", title: "Cuenta propietaria", icon: UserRound },
    { key: "plan", label: "Plan", title: "Plan y activación", icon: Sparkles },
]

const STEP_FIELDS = {
    0: ["business_name", "slug", "category_id", "business_type"],
    1: ["city_id", "whatsapp"],
    2: ["name", "email", "password", "password_confirmation"],
    3: ["selected_plan", "terms_accepted"],
}

const FIELD_TO_STEP = Object.entries(STEP_FIELDS).reduce((carry, [step, fields]) => {
    fields.forEach((field) => {
        carry[field] = Number(step)
    })

    return carry
}, {})

function slugify(value) {
    return value
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/(^-|-$)/g, "")
}

function money(value) {
    return Number(value ?? 0).toLocaleString("es-HN", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    })
}

function StepPill({ step, index, currentStep, onClick }) {
    const Icon = step.icon
    const active = index === currentStep
    const complete = index < currentStep

    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex items-center gap-3 rounded-2xl border px-4 py-3 text-left transition ${
                active
                    ? "border-slate-950 bg-slate-950 text-white"
                    : complete
                        ? "border-slate-200 bg-slate-100 text-slate-950"
                        : "border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-950"
            }`}
        >
            <span className={`flex h-10 w-10 items-center justify-center rounded-xl ${active ? "bg-white/10" : complete ? "bg-white" : "bg-slate-100"}`}>
                {complete ? <Check className="h-4 w-4" /> : <Icon className="h-4 w-4" />}
            </span>
            <span className="min-w-0">
                <span
                    className={`block text-[10px] font-bold uppercase tracking-[0.22em] ${active ? "text-slate-300" : "text-slate-400"}`}
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    {step.label}
                </span>
                <span className="mt-1 block truncate text-sm font-semibold">{step.title}</span>
            </span>
        </button>
    )
}

function PlanCard({ active, recommended = false, title, caption, price, bullets, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`relative rounded-[1.75rem] border p-6 text-left transition ${
                active
                    ? "border-slate-950 bg-slate-950 text-white"
                    : "border-slate-200 bg-slate-50 text-slate-950 hover:border-slate-300 hover:bg-white"
            }`}
        >
            {recommended ? (
                <span
                    className={`absolute left-6 top-0 -translate-y-1/2 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] ${active ? "bg-white text-slate-950" : "bg-slate-950 text-white"}`}
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    Recomendado
                </span>
            ) : null}

            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-2xl font-extrabold tracking-tight" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                        {title}
                    </p>
                    <p
                        className={`mt-1 text-[11px] font-bold uppercase tracking-[0.2em] ${active ? "text-slate-300" : "text-slate-500"}`}
                        style={{ fontFamily: PUBLIC_LABEL_FONT }}
                    >
                        {caption}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-3xl font-extrabold tracking-tight">{price}</p>
                    <span className={`mt-3 inline-flex h-6 w-6 items-center justify-center rounded-full border ${active ? "border-white bg-white text-slate-950" : "border-slate-300 bg-white"}`}>
                        {active ? <Check className="h-3.5 w-3.5" /> : null}
                    </span>
                </div>
            </div>

            <div className="mt-6 space-y-3">
                {bullets.map((bullet) => (
                    <div key={bullet} className={`flex items-center gap-2 text-sm ${active ? "text-slate-100" : "text-slate-700"}`}>
                        <CheckCircle2 className="h-4 w-4" />
                        <span>{bullet}</span>
                    </div>
                ))}
            </div>
        </button>
    )
}

function BusinessTypeCard({ active, title, description, capabilities, examples, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded-[1.5rem] border p-5 text-left transition ${
                active
                    ? "border-slate-950 bg-slate-950 text-white"
                    : "border-slate-200 bg-slate-50 text-slate-950 hover:border-slate-300 hover:bg-white"
            }`}
        >
            <div className="space-y-2">
                <p className="text-lg font-extrabold tracking-tight" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                    {title}
                </p>
                <p className={`text-sm leading-7 ${active ? "text-slate-200" : "text-slate-600"}`}>
                    {description}
                </p>
            </div>

            <div className="mt-4 space-y-2">
                {capabilities.slice(0, 3).map((capability) => (
                    <div key={capability} className={`flex items-center gap-2 text-sm ${active ? "text-slate-100" : "text-slate-700"}`}>
                        <CheckCircle2 className="h-4 w-4" />
                        <span>{capability}</span>
                    </div>
                ))}
            </div>

            <p className={`mt-4 text-xs font-semibold uppercase tracking-[0.18em] ${active ? "text-slate-300" : "text-slate-500"}`} style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                Ideal para: {examples.join(", ")}
            </p>
        </button>
    )
}

function SummaryRow({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4 rounded-xl bg-slate-50 px-4 py-3">
            <span className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                {label}
            </span>
            <span className="text-right text-sm font-semibold text-slate-950">{value}</span>
        </div>
    )
}

export function RegisterTenantWizard({
    categories = [],
    cities = [],
    businessTypes = [],
    businessTypeSuggestions = {},
    segmentProfiles = [],
    settings = {},
}) {
    const [step, setStep] = useState(0)
    const [slugTouched, setSlugTouched] = useState(false)
    const [businessTypeTouched, setBusinessTypeTouched] = useState(false)
    const [localErrors, setLocalErrors] = useState({})
    const [slugState, setSlugState] = useState({
        status: "idle",
                message: "La URL se revisa en vivo mientras escribes.",
        suggestions: [],
    })

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        business_name: "",
        slug: "",
        category_id: "",
        business_type: businessTypes[0]?.value || "retail",
        city_id: "",
        whatsapp: "",
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        selected_plan: "free",
        terms_accepted: false,
    })

    const errorFor = (field) => localErrors[field] || errors[field] || null
    const selectedCategory = useMemo(
        () => categories.find((item) => String(item.id) === String(data.category_id)),
        [categories, data.category_id]
    )
    const selectedBusinessType = useMemo(
        () => businessTypes.find((item) => item.value === data.business_type) || null,
        [businessTypes, data.business_type]
    )
    const selectedCity = useMemo(
        () => cities.find((item) => String(item.id) === String(data.city_id)),
        [cities, data.city_id]
    )
    const suggestedBusinessType = useMemo(() => {
        if (!selectedCategory?.slug) {
            return null
        }

        return businessTypeSuggestions[selectedCategory.slug] || null
    }, [businessTypeSuggestions, selectedCategory])
    const segmentCandidates = useMemo(() => {
        const profiles = segmentProfiles.filter((item) => item.businessType === data.business_type)
        const categoryMatches = selectedCategory?.slug
            ? profiles.filter((item) => item.categories.includes(selectedCategory.slug))
            : []

        return (categoryMatches.length > 0 ? categoryMatches : profiles).slice(0, 4)
    }, [data.business_type, selectedCategory, segmentProfiles])
    const selectedSegment = segmentCandidates[0] || null
    const slugBaseHost = settings.slugBaseHost || "vito.hn"
    const businessPreview = data.business_name.trim() || "Tu negocio"
    const slugPreview = data.slug ? `${slugBaseHost}/${slugify(data.slug)}` : `${slugBaseHost}/tu-negocio`
    const progress = ((step + 1) / STEPS.length) * 100
    const freeLimit = Number(settings.freeProductLimit ?? 10)
    const proLimit = Number(settings.proProductLimit ?? 100)
    const proPrice = money(settings.proPlanPrice ?? 500)
    const canSubmit = categories.length > 0 && cities.length > 0

    useEffect(() => {
        const firstServerError = Object.keys(errors)[0]
        if (firstServerError) {
            setStep(FIELD_TO_STEP[firstServerError] ?? 0)
        }
    }, [errors])

    useEffect(() => {
        if (slugTouched) {
            return
        }

        const nextSlug = slugify(data.business_name)
        if (nextSlug !== data.slug) {
            setData("slug", nextSlug)
        }
    }, [data.business_name, data.slug, setData, slugTouched])

    useEffect(() => {
        if (businessTypeTouched) {
            return
        }

        if (!suggestedBusinessType || suggestedBusinessType === data.business_type) {
            return
        }

        setData("business_type", suggestedBusinessType)
    }, [businessTypeTouched, data.business_type, setData, suggestedBusinessType])

    useEffect(() => {
        const slug = slugify(data.slug)

        if (!slug) {
            setSlugState({
                status: "idle",
                message: "La URL se genera automáticamente a partir del nombre comercial.",
                suggestions: [],
            })
            return undefined
        }

        if (slug.length < 3) {
            setSlugState({
                status: "idle",
                message: "Usa al menos 3 caracteres para revisar disponibilidad.",
                suggestions: [],
            })
            return undefined
        }

        const controller = new AbortController()
        const timer = window.setTimeout(async () => {
            try {
                setSlugState({ status: "checking", message: "Validando URL...", suggestions: [] })

                const response = await fetch(`${route("api.check-slug")}?slug=${encodeURIComponent(slug)}`, {
                    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                    signal: controller.signal,
                })
                const payload = await response.json()

                setSlugState(
                    payload.available
                        ? {
                              status: "available",
                              message: payload.message || "Esta URL está disponible.",
                              suggestions: [],
                          }
                        : {
                              status: "unavailable",
                              message: payload.message || "Esta URL ya está ocupada.",
                              suggestions: payload.suggestions || [],
                          }
                )
            } catch (error) {
                if (error.name !== "AbortError") {
                    setSlugState({
                        status: "error",
                        message: "No pudimos revisar la URL ahora mismo. El servidor la validará al enviar.",
                        suggestions: [],
                    })
                }
            }
        }, 320)

        return () => {
            controller.abort()
            window.clearTimeout(timer)
        }
    }, [data.slug])

    const updateField = (field, value) => {
        setLocalErrors((current) => {
            const next = { ...current }
            delete next[field]
            return next
        })
        clearErrors(field)
        setData(field, value)
    }

    const inputClass = (field) =>
        `h-12 rounded-xl border-slate-200 bg-slate-50 px-4 text-slate-950 placeholder:text-slate-400 focus-visible:border-slate-950 focus-visible:ring-slate-300 ${
            errorFor(field) ? "border-rose-300 focus-visible:ring-rose-200" : ""
        }`

    const validateStep = (targetStep) => {
        const nextErrors = {}
        const slug = slugify(data.slug)

        if (targetStep === 0) {
            if (!data.business_name.trim()) nextErrors.business_name = "Escribe el nombre comercial."
            if (!slug) nextErrors.slug = "Define una URL para el negocio."
            else if (slug.length < 3) nextErrors.slug = "La URL necesita al menos 3 caracteres."
            else if (slugState.status === "checking") nextErrors.slug = "Espera a que termine la revisión de la URL."
            else if (slugState.status === "unavailable") nextErrors.slug = "Esa URL ya está ocupada."
            if (!String(data.category_id).trim()) nextErrors.category_id = "Selecciona una categoría."
            if (!String(data.business_type).trim()) nextErrors.business_type = "Selecciona el tipo de negocio."
        }

        if (targetStep === 1 && !String(data.city_id).trim()) {
            nextErrors.city_id = "Selecciona una ciudad."
        }

        if (targetStep === 2) {
            if (!data.name.trim()) nextErrors.name = "Escribe tu nombre completo."
            if (!data.email.trim()) nextErrors.email = "Escribe el correo de acceso."
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) nextErrors.email = "Escribe un correo válido."
            if (!data.password) nextErrors.password = "Crea una contraseña."
            else if (data.password.length < 8) nextErrors.password = "Usa al menos 8 caracteres."
            if (!data.password_confirmation) nextErrors.password_confirmation = "Confirma la contraseña."
            else if (data.password_confirmation !== data.password) nextErrors.password_confirmation = "Las contraseñas no coinciden."
        }

        if (targetStep === 3) {
            if (!["free", "pro"].includes(data.selected_plan)) nextErrors.selected_plan = "Selecciona un plan válido."
            if (!data.terms_accepted) nextErrors.terms_accepted = "Debes aceptar los términos para continuar."
        }

        const fields = STEP_FIELDS[targetStep]
        setLocalErrors((current) => {
            const next = { ...current }
            fields.forEach((field) => delete next[field])
            return { ...next, ...nextErrors }
        })

        return Object.keys(nextErrors).length === 0
    }

    const submit = (event) => {
        if (event) event.preventDefault()
        if (!validateStep(step)) return

        post(route("tenant.register.store"), {
            onError: (serverErrors) => {
                const firstField = Object.keys(serverErrors)[0]
                if (firstField) setStep(FIELD_TO_STEP[firstField] ?? 0)
            },
        })
    }

    return (
        <AuthSplitLayout
            topActions={
                <>
                    <Link href={route("login")} className="hidden text-sm font-semibold text-slate-600 transition hover:text-slate-950 md:inline-flex">
                        Iniciar sesión
                    </Link>
                    <Link href={route("public.explore")} className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950">
                        Explorar
                    </Link>
                </>
            }
            aside={
                <>
                    <div className="max-w-xl space-y-6">
                        <div
                            className="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-slate-300"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            Onboarding conectado
                        </div>

                        <div className="space-y-4">
                            <h2 className="max-w-lg text-4xl font-extrabold leading-[1.02] tracking-tight text-white xl:text-5xl" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Registro, verificación y panel dentro del mismo sistema.
                            </h2>
                            <p className="max-w-lg text-base leading-7 text-slate-300 xl:text-lg">
                                El alta del negocio ya no se siente como un formulario suelto: sigue la misma capa de auth y aterriza en el backend real.
                            </p>
                        </div>
                    </div>

                    <div className="space-y-5">
                        <AuthAsideCard
                            invert
                            eyebrow={`Paso ${step + 1} de ${STEPS.length}`}
                            title={STEPS[step].title}
                            description="Cada paso muestra solo lo necesario ahora, pero sigue conectado al mismo backend."
                        >
                            <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                                <div className="h-full rounded-full bg-white transition-all duration-500" style={{ width: `${progress}%` }} />
                            </div>
                        </AuthAsideCard>

                        <AuthAsideFeature icon={Store} title="Entrada limpia" body="La cuenta que creas aquí es la misma que entra al panel y a la verificación de correo." />
                        <AuthAsideFeature icon={BarChart3} title="Sin inconsistencias" body="Slug, plan, rubro y datos base se validan igual en frontend y backend." />
                        <AuthAsideFeature icon={ShieldCheck} title="Infraestructura segura" body="El flujo conserva validación real, autenticación y ownership del negocio." />

                        <AuthAsideCard eyebrow="Vista previa" title={businessPreview} description={slugPreview}>
                            <div className="mt-4 space-y-3">
                                <SummaryRow label="Categoría" value={selectedCategory?.name || "Pendiente"} />
                                <SummaryRow label="Tipo" value={selectedBusinessType?.label || "Pendiente"} />
                                <SummaryRow label="Segmento" value={selectedSegment?.label || "Pendiente"} />
                                <SummaryRow label="Ciudad" value={selectedCity?.name || "Pendiente"} />
                                <SummaryRow label="Plan" value={data.selected_plan === "pro" ? "Business Pro" : "Basic"} />
                            </div>
                        </AuthAsideCard>
                    </div>
                </>
            }
        >
            <section className="w-full max-w-3xl" style={{ fontFamily: PUBLIC_BODY_FONT }}>
                <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.06)] sm:p-8">
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                Registro de negocio
                            </p>
                            <h1 className="text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Crea tu cuenta operativa sin salir del mismo flujo.
                            </h1>
                            <p className="max-w-2xl text-sm leading-7 text-slate-500">
                                Paso a paso, con el mismo lenguaje visual de login y verificación.
                            </p>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            {STEPS.map((item, index) => (
                                <StepPill key={item.key} step={item} index={index} currentStep={step} onClick={() => index <= step && setStep(index)} />
                            ))}
                        </div>
                    </div>

                    {!canSubmit ? (
                        <div className="mt-8 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            No pudimos cargar ciudades o categorías. Recarga la página e inténtalo de nuevo.
                        </div>
                    ) : null}

                    <div className="mt-8">
                        <div className="mb-6 border-b border-slate-200 pb-4">
                            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                {`Paso ${step + 1} de ${STEPS.length}`}
                            </p>
                            <h2 className="mt-2 text-2xl font-extrabold tracking-tight text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                {STEPS[step].title}
                            </h2>
                        </div>

                        <form onSubmit={submit}>
                            {step === 0 ? (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="business_name">Nombre comercial</FieldLabel>
                                        <Input id="business_name" value={data.business_name} onChange={(event) => updateField("business_name", event.target.value)} placeholder="Ej. Café La Esperanza" className={inputClass("business_name")} />
                                        {errorFor("business_name") ? <FieldDescription className="text-rose-600">{errorFor("business_name")}</FieldDescription> : null}
                                    </Field>

                                    <Field>
                                        <div className="flex items-center justify-between gap-3">
                                            <FieldLabel htmlFor="slug">URL personalizada</FieldLabel>
                                            <span className={`text-[11px] font-bold uppercase tracking-[0.18em] ${slugState.status === "available" ? "text-slate-950" : slugState.status === "unavailable" || slugState.status === "error" ? "text-rose-600" : "text-slate-400"}`} style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                                {slugState.status === "available" ? "Disponible" : slugState.status === "checking" ? "Revisando" : slugState.status === "unavailable" ? "Ocupada" : "URL"}
                                            </span>
                                        </div>
                                        <div className="relative">
                                            <span className="absolute inset-y-0 left-4 inline-flex items-center text-sm text-slate-400">{slugBaseHost}/</span>
                                            <Input
                                                id="slug"
                                                value={data.slug}
                                                onChange={(event) => {
                                                    setSlugTouched(true)
                                                    updateField("slug", slugify(event.target.value))
                                                }}
                                                placeholder="tunegocio"
                                                className={`${inputClass("slug")} pl-24 pr-4`}
                                            />
                                        </div>
                                        <FieldDescription className={slugState.status === "unavailable" || slugState.status === "error" ? "text-rose-600" : "text-slate-500"}>
                                            {errorFor("slug") || slugState.message}
                                        </FieldDescription>
                                        {slugState.suggestions?.length ? (
                                            <div className="flex flex-wrap gap-2">
                                                {slugState.suggestions.map((suggestion) => (
                                                    <button key={suggestion} type="button" onClick={() => { setSlugTouched(true); updateField("slug", suggestion) }} className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950">
                                                        {suggestion}
                                                    </button>
                                                ))}
                                            </div>
                                        ) : null}
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="category_id">Categoría</FieldLabel>
                                        <select id="category_id" value={data.category_id} onChange={(event) => updateField("category_id", event.target.value)} className={`h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-950 outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-300 ${errorFor("category_id") ? "border-rose-300 focus:ring-rose-200" : ""}`}>
                                            <option value="">Selecciona una categoría</option>
                                            {categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                                        </select>
                                        {errorFor("category_id") ? <FieldDescription className="text-rose-600">{errorFor("category_id")}</FieldDescription> : null}
                                    </Field>

                                    <Field>
                                        <div className="space-y-2">
                                            <FieldLabel>Tipo de negocio</FieldLabel>
                                            <FieldDescription>
                                                Define si tu operación se parece más a una tienda, restaurante, negocio de servicios o un modelo mixto.
                                            </FieldDescription>
                                        </div>

                                        <div className="grid gap-4 xl:grid-cols-2">
                                            {businessTypes.map((item) => (
                                                <BusinessTypeCard
                                                    key={item.value}
                                                    active={data.business_type === item.value}
                                                    title={item.label}
                                                    description={item.description}
                                                    capabilities={item.capabilities}
                                                    examples={item.examples}
                                                    onClick={() => {
                                                        setBusinessTypeTouched(true)
                                                        updateField("business_type", item.value)
                                                    }}
                                                />
                                            ))}
                                        </div>

                                        {suggestedBusinessType && !businessTypeTouched ? (
                                            <FieldDescription>
                                                Te sugerimos <strong>{businessTypes.find((item) => item.value === suggestedBusinessType)?.label || "este tipo"}</strong> por la categoría que elegiste.
                                            </FieldDescription>
                                        ) : null}

                                        {selectedSegment ? (
                                            <div className="rounded-[1.75rem] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-cyan-50 p-5 shadow-[0_20px_50px_rgba(16,185,129,0.08)]">
                                                <div className="space-y-3">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span
                                                            className="inline-flex rounded-full bg-slate-950 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] text-white"
                                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                                        >
                                                            Segmento sugerido
                                                        </span>
                                                        <span
                                                            className="inline-flex rounded-full border border-emerald-300 bg-emerald-100 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-700"
                                                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                                        >
                                                            {selectedSegment.label}
                                                        </span>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <h3 className="text-xl font-extrabold tracking-tight text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                                            {selectedSegment.onboardingTitle}
                                                        </h3>
                                                        <p className="text-sm leading-7 text-slate-600">
                                                            {selectedSegment.onboardingCopy || selectedSegment.teaser}
                                                        </p>
                                                    </div>

                                                    {selectedSegment.modules?.length ? (
                                                        <div className="flex flex-wrap gap-2">
                                                            {selectedSegment.modules.slice(0, 4).map((module) => (
                                                                <span
                                                                    key={module}
                                                                    className="rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-700"
                                                                >
                                                                    {module}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    ) : null}

                                                    {segmentCandidates.length > 1 ? (
                                                        <div className="space-y-2">
                                                            <p
                                                                className="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500"
                                                                style={{ fontFamily: PUBLIC_LABEL_FONT }}
                                                            >
                                                                Rutas cercanas para este rubro
                                                            </p>
                                                            <div className="flex flex-wrap gap-2">
                                                                {segmentCandidates.slice(1).map((segment) => (
                                                                    <span
                                                                        key={segment.key}
                                                                        className="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"
                                                                    >
                                                                        {segment.label}
                                                                    </span>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </div>
                                        ) : null}

                                        {errorFor("business_type") ? <FieldDescription className="text-rose-600">{errorFor("business_type")}</FieldDescription> : null}
                                    </Field>
                                </FieldGroup>
                            ) : null}

                            {step === 1 ? (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="city_id">Ciudad</FieldLabel>
                                        <select id="city_id" value={data.city_id} onChange={(event) => updateField("city_id", event.target.value)} className={`h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-950 outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-300 ${errorFor("city_id") ? "border-rose-300 focus:ring-rose-200" : ""}`}>
                                            <option value="">Selecciona una ciudad</option>
                                            {cities.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                                        </select>
                                        {errorFor("city_id") ? <FieldDescription className="text-rose-600">{errorFor("city_id")}</FieldDescription> : null}
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="whatsapp">WhatsApp</FieldLabel>
                                        <Input id="whatsapp" value={data.whatsapp} onChange={(event) => updateField("whatsapp", event.target.value)} placeholder="+504 9999-9999" className={inputClass("whatsapp")} />
                                        <FieldDescription>Opcional, pero ayuda a conectar pedidos y contacto desde el inicio.</FieldDescription>
                                    </Field>
                                </FieldGroup>
                            ) : null}

                            {step === 2 ? (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="name">Nombre completo</FieldLabel>
                                        <Input id="name" value={data.name} onChange={(event) => updateField("name", event.target.value)} placeholder="Nombre Apellido" className={inputClass("name")} />
                                        {errorFor("name") ? <FieldDescription className="text-rose-600">{errorFor("name")}</FieldDescription> : null}
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="email">Correo</FieldLabel>
                                        <Input id="email" type="email" value={data.email} onChange={(event) => updateField("email", event.target.value)} placeholder="hola@tunegocio.hn" className={inputClass("email")} />
                                        {errorFor("email") ? (
                                            <FieldDescription className="text-rose-600">{errorFor("email")}</FieldDescription>
                                        ) : (
                                            <FieldDescription>Usaremos este correo para login, verificación y recuperación.</FieldDescription>
                                        )}
                                    </Field>

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Field>
                                            <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                                            <Input id="password" type="password" value={data.password} onChange={(event) => updateField("password", event.target.value)} className={inputClass("password")} />
                                            {errorFor("password") ? <FieldDescription className="text-rose-600">{errorFor("password")}</FieldDescription> : <FieldDescription>Mínimo 8 caracteres.</FieldDescription>}
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="password_confirmation">Confirmar contraseña</FieldLabel>
                                            <Input id="password_confirmation" type="password" value={data.password_confirmation} onChange={(event) => updateField("password_confirmation", event.target.value)} className={inputClass("password_confirmation")} />
                                            {errorFor("password_confirmation") ? <FieldDescription className="text-rose-600">{errorFor("password_confirmation")}</FieldDescription> : null}
                                        </Field>
                                    </div>
                                </FieldGroup>
                            ) : null}

                            {step === 3 ? (
                                <FieldGroup>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <PlanCard active={data.selected_plan === "free"} title="Plan Basic" caption="Lo esencial para iniciar" price="$0" bullets={["Perfil digital público", `Catálogo de ${freeLimit} productos`, "Panel base para operar"]} onClick={() => updateField("selected_plan", "free")} />
                                        <PlanCard active={data.selected_plan === "pro"} recommended title="Plan Business Pro" caption="Potencia total" price={`$${proPrice}`} bullets={["Ventas ilimitadas", "Analíticas avanzadas", `Capacidad comercial de ${proLimit} productos`]} onClick={() => updateField("selected_plan", "pro")} />
                                    </div>
                                    {errorFor("selected_plan") ? <FieldDescription className="text-rose-600">{errorFor("selected_plan")}</FieldDescription> : null}

                                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr),300px]">
                                        <div className="space-y-3 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                                            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>Resumen de activación</p>
                                            <SummaryRow label="Negocio" value={businessPreview} />
                                            <SummaryRow label="URL" value={slugPreview} />
                                            <SummaryRow label="Categoría" value={selectedCategory?.name || "Pendiente"} />
                                            <SummaryRow label="Tipo" value={selectedBusinessType?.label || "Pendiente"} />
                                            <SummaryRow label="Segmento" value={selectedSegment?.label || "Pendiente"} />
                                            <SummaryRow label="Ciudad" value={selectedCity?.name || "Pendiente"} />
                                            <SummaryRow label="Cuenta" value={data.email || "Sin correo aún"} />
                                            <SummaryRow label="Plan" value={data.selected_plan === "pro" ? "Business Pro" : "Basic"} />
                                        </div>

                                        <div className="space-y-4">
                                            <label className="flex items-start gap-3 rounded-[1.75rem] border border-slate-200 bg-slate-50 px-5 py-5">
                                                <input type="checkbox" checked={data.terms_accepted} onChange={(event) => updateField("terms_accepted", event.target.checked)} className="mt-1 h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-300" />
                                                <span className="text-sm leading-7 text-slate-600">
                                                    Confirmo que tengo autorización para registrar este negocio y acepto los términos operativos de Conecta HN.
                                                </span>
                                            </label>
                                            {errorFor("terms_accepted") ? <FieldDescription className="text-rose-600">{errorFor("terms_accepted")}</FieldDescription> : null}

                                            <div className="rounded-[1.75rem] bg-slate-950 px-5 py-6 text-white">
                                                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-300" style={{ fontFamily: PUBLIC_LABEL_FONT }}>Lo que sucede al enviar</p>
                                                <p className="mt-3 text-sm leading-7 text-slate-300">
                                                    Creamos la cuenta, el negocio y el acceso al panel en una sola pasada, conectado al backend real.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </FieldGroup>
                            ) : null}
                        </form>
                    </div>

                    <div className="mt-8 flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <span className="text-xs font-bold uppercase tracking-[0.2em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                Progreso
                            </span>
                            <div className="h-2 w-32 overflow-hidden rounded-full bg-slate-200 sm:w-40">
                                <div className="h-full rounded-full bg-slate-950 transition-all duration-500" style={{ width: `${progress}%` }} />
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setStep((current) => Math.max(current - 1, 0))} disabled={step === 0 || processing} className="h-12 rounded-xl border-slate-200 bg-white text-slate-950 hover:bg-slate-50">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Atrás
                            </Button>

                            {step < STEPS.length - 1 ? (
                                <Button type="button" onClick={() => validateStep(step) && setStep((current) => current + 1)} disabled={processing || !canSubmit} className="h-12 rounded-xl bg-slate-950 text-white hover:bg-slate-800">
                                    Continuar
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            ) : (
                                <Button type="button" onClick={submit} disabled={processing || !canSubmit} className="h-12 rounded-xl bg-slate-950 text-white hover:bg-slate-800">
                                    {processing ? (
                                        <>
                                            <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                                            Creando...
                                        </>
                                    ) : (
                                        <>
                                            Crear cuenta
                                            <BadgeCheck className="ml-2 h-4 w-4" />
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </AuthSplitLayout>
    )
}
