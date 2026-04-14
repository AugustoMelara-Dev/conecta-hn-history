import { Link, useForm } from "@inertiajs/react"
import { useEffect, useMemo, useState } from "react"
import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Building2,
    Check,
    CheckCircle2,
    LoaderCircle,
    MapPin,
    Sparkles,
    UserRound,
} from "lucide-react"

import AuthSplitLayout, {
    AuthAsideCard,
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
    { key: "business", label: "Negocio", title: "InformaciÃ³n del negocio", icon: Building2 },
    { key: "location", label: "Zona", title: "UbicaciÃ³n y contacto", icon: MapPin },
    { key: "account", label: "Cuenta", title: "Cuenta propietaria", icon: UserRound },
    { key: "plan", label: "Lanzar", title: "Plan y activaciÃ³n", icon: Sparkles },
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

function normalizeHost(value) {
    if (!value || typeof value !== "string") {
        return null
    }

    return value
        .replace(/^https?:\/\//i, "")
        .replace(/\/+$/, "")
        .replace(/\/.*/, "")
        .trim() || null
}

function firstValidUrl(...candidates) {
    for (const candidate of candidates) {
        if (typeof candidate !== "string") {
            continue
        }

        const normalized = candidate.trim()
        if (!normalized || normalized === "#") {
            continue
        }

        return normalized
    }

    return null
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
    const slugBaseHost = useMemo(() => {
        const currentHost = typeof window !== "undefined" ? normalizeHost(window.location?.host) : null
        const forwardedHost = normalizeHost(settings?.requestHost)
        const configuredHost = normalizeHost(settings?.registrationHost)
        const legacyHost = normalizeHost(settings?.slugBaseHost)

        return currentHost || forwardedHost || configuredHost || legacyHost || "vito.hn"
    }, [settings?.registrationHost, settings?.requestHost, settings?.slugBaseHost])
    const slugCheckCandidates = useMemo(() => {
        const configured = firstValidUrl(settings?.slugCheckUrl)
        const candidates = []

        if (configured) {
            candidates.push(configured)
        }

        try {
            candidates.push(route("api.check-slug"))
        } catch {}

        try {
            candidates.push(route("check-slug"))
        } catch {}

        candidates.push("/api/check-slug")
        candidates.push("/check-slug")

        return [...new Set(candidates.filter(Boolean))]
    }, [settings?.slugCheckUrl])
    const registerStoreEndpoint = useMemo(() => {
        const candidates = []

        try {
            candidates.push(route("tenant.register.store"))
        } catch {}

        return firstValidUrl(...candidates, "/registrar-negocio") || "/registrar-negocio"
    }, [])
    const businessPreview = data.business_name.trim() || "Tu negocio"
    const slugPreview = data.slug ? `${slugBaseHost}/t/${slugify(data.slug)}` : `${slugBaseHost}/t/tu-negocio`
    const progress = ((step + 1) / STEPS.length) * 100
    const freeLimit = Number(settings.freeProductLimit ?? 10)
    const proLimit = Number(settings.proProductLimit ?? 100)
    const proPrice = money(settings.proPlanPrice ?? 500)
    const canSubmit = categories.length > 0 && cities.length > 0

    const focusField = (field) => {
        if (typeof window === "undefined") return

        window.requestAnimationFrame(() => {
            const node = document.getElementById(field)
            if (node && typeof node.focus === "function") {
                node.focus()
            }
        })
    }

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
                message: "La URL se genera automÃ¡ticamente a partir del nombre comercial.",
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

                let payload = null
                let response = null

                for (const endpoint of slugCheckCandidates) {
                    const candidateResponse = await fetch(`${endpoint}?slug=${encodeURIComponent(slug)}`, {
                        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                        signal: controller.signal,
                    })
                    const contentType = candidateResponse.headers.get("content-type") || ""
                    const candidatePayload = contentType.includes("application/json")
                        ? await candidateResponse.json()
                        : null

                    if (candidateResponse.ok && candidatePayload) {
                        response = candidateResponse
                        payload = candidatePayload
                        break
                    }
                }

                if (!response || !payload) {
                    throw new Error("slug-check-unavailable")
                }

                setSlugState(
                    payload.available
                        ? {
                              status: "available",
                              message: payload.message || "Esta URL estÃ¡ disponible.",
                              suggestions: [],
                          }
                        : {
                              status: "unavailable",
                              message: payload.message || "Esta URL ya estÃ¡ ocupada.",
                              suggestions: payload.suggestions || [],
                          }
                )
            } catch (error) {
                if (error.name !== "AbortError") {
                    setSlugState({
                        status: "idle",
                        message: "No pudimos revisar la URL en tiempo real. Se validará al crear el negocio.",
                        suggestions: [],
                    })
                }
            }
        }, 320)

        return () => {
            controller.abort()
            window.clearTimeout(timer)
        }
    }, [data.slug, slugCheckCandidates])

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
            else if (slugState.status === "checking") nextErrors.slug = "Espera a que termine la revisiÃ³n de la URL."
            else if (slugState.status === "unavailable") nextErrors.slug = "Esa URL ya estÃ¡ ocupada."
            if (!String(data.category_id).trim()) nextErrors.category_id = "Selecciona una categorÃ­a."
            if (!String(data.business_type).trim()) nextErrors.business_type = "Selecciona el tipo de negocio."
        }

        if (targetStep === 1 && !String(data.city_id).trim()) {
            nextErrors.city_id = "Selecciona una ciudad."
        }

        if (targetStep === 2) {
            if (!data.name.trim()) nextErrors.name = "Escribe tu nombre completo."
            if (!data.email.trim()) nextErrors.email = "Escribe el correo de acceso."
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) nextErrors.email = "Escribe un correo vÃ¡lido."
            if (!data.password) nextErrors.password = "Crea una contraseÃ±a."
            else if (data.password.length < 8) nextErrors.password = "Usa al menos 8 caracteres."
            if (!data.password_confirmation) nextErrors.password_confirmation = "Confirma la contraseÃ±a."
            else if (data.password_confirmation !== data.password) nextErrors.password_confirmation = "Las contraseÃ±as no coinciden."
        }

        if (targetStep === 3) {
            if (!["free", "pro"].includes(data.selected_plan)) nextErrors.selected_plan = "Selecciona un plan vÃ¡lido."
            if (!data.terms_accepted) nextErrors.terms_accepted = "Debes aceptar los tÃ©rminos para continuar."
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
        if (!validateStep(step)) {
            const firstInvalid = (STEP_FIELDS[step] || []).find((field) => Boolean(errorFor(field)))
            if (firstInvalid) focusField(firstInvalid)
            return
        }

        post(registerStoreEndpoint, {
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
                        Iniciar sesiÃ³n
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
                                Lanza tu negocio en minutos con un registro guiado.
                            </h2>
                            <p className="max-w-lg text-base leading-7 text-slate-300 xl:text-lg">
                                Definimos URL, perfil comercial, cuenta y plan en un solo flujo conectado a producciÃ³n.
                            </p>
                        </div>
                    </div>

                    <div className="space-y-5">
                        <AuthAsideCard
                            invert
                            eyebrow={`Paso ${step + 1} de ${STEPS.length}`}
                            title={STEPS[step].title}
                            description="Cada paso pide solo lo necesario para activar tu panel sin fricciÃ³n."
                        >
                            <div className="mt-6 h-2 overflow-hidden rounded-full bg-white/10">
                                <div className="h-full rounded-full bg-white transition-all duration-500" style={{ width: `${progress}%` }} />
                            </div>
                        </AuthAsideCard>

                        <AuthAsideCard
                            invert
                            eyebrow="Checklist de activaciÃ³n"
                            title="Lo que queda listo al enviar"
                            description="Este registro no es demo: crea recursos reales dentro de la plataforma."
                        >
                            <div className="mt-4 space-y-3">
                                {[
                                    "Cuenta propietaria con login real",
                                    "Negocio con slug validado y aislado",
                                    "Acceso al panel y flujo de verificaciÃ³n",
                                ].map((item) => (
                                    <div key={item} className="flex items-start gap-2 text-sm text-slate-200">
                                        <CheckCircle2 className="mt-0.5 h-4 w-4" />
                                        <span>{item}</span>
                                    </div>
                                ))}
                            </div>
                        </AuthAsideCard>

                        <AuthAsideCard eyebrow="Vista previa" title={businessPreview} description={slugPreview}>
                            <div className="mt-4 space-y-3">
                                <SummaryRow label="CategorÃ­a" value={selectedCategory?.name || "Pendiente"} />
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
                                Crea tu negocio con una experiencia mÃ¡s clara.
                            </h1>
                            <p className="max-w-2xl text-sm leading-7 text-slate-500">
                                Cuatro pasos, validaciones en vivo y activaciÃ³n real al finalizar.
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
                            No pudimos cargar ciudades o categorÃ­as. Recarga la pÃ¡gina e intÃ©ntalo de nuevo.
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
                                        <Input id="business_name" name="business_name" autoComplete="organization" value={data.business_name} onChange={(event) => updateField("business_name", event.target.value)} placeholder="Ej. Cafe La Esperanza" className={inputClass("business_name")} />
                                        {errorFor("business_name") ? <FieldDescription className="text-rose-600">{errorFor("business_name")}</FieldDescription> : null}
                                    </Field>

                                    <Field>
                                        <div className="flex items-center justify-between gap-3">
                                            <FieldLabel htmlFor="slug">URL personalizada</FieldLabel>
                                            <span className={`text-[11px] font-bold uppercase tracking-[0.18em] ${slugState.status === "available" ? "text-slate-950" : slugState.status === "unavailable" || slugState.status === "error" ? "text-rose-600" : "text-slate-400"}`} style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                                {slugState.status === "available" ? "Disponible" : slugState.status === "checking" ? "Revisando" : slugState.status === "unavailable" ? "Ocupada" : "Pendiente"}
                                            </span>
                                        </div>
                                        <div className={`grid h-12 grid-cols-[minmax(90px,auto)_auto_1fr] items-center gap-1 rounded-xl border bg-slate-50 px-3 text-sm transition focus-within:ring-4 ${
                                            errorFor("slug")
                                                ? "border-rose-300 focus-within:border-rose-300 focus-within:ring-rose-200"
                                                : "border-slate-200 focus-within:border-slate-950 focus-within:ring-slate-300"
                                        }`}>
                                            <span className="truncate text-xs text-slate-500" title={slugBaseHost}>
                                                {slugBaseHost}
                                            </span>
                                            <span className="text-xs text-slate-400">/t/</span>
                                            <input id="slug" name="slug" autoComplete="off"
                                                value={data.slug}
                                                onChange={(event) => {
                                                    setSlugTouched(true)
                                                    updateField("slug", slugify(event.target.value))
                                                }}
                                                placeholder="tunegocio"
                                                className="h-full w-full border-0 bg-transparent px-1 text-slate-950 outline-none placeholder:text-slate-400"
                                            />
                                        </div>
                                        <FieldDescription aria-live="polite" className={slugState.status === "unavailable" || slugState.status === "error" ? "text-rose-600" : "text-slate-500"}>
                                            {errorFor("slug") || slugState.message}
                                        </FieldDescription>
                                        {slugState.suggestions?.length ? (
                                            <div className="space-y-2">
                                                <p className="text-xs font-semibold text-slate-500">Opciones sugeridas:</p>
                                                <div className="flex flex-wrap gap-2">
                                                    {slugState.suggestions.map((suggestion) => (
                                                        <button key={suggestion} type="button" onClick={() => { setSlugTouched(true); updateField("slug", suggestion) }} className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-950">
                                                            {suggestion}
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : null}
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="category_id">CategorÃ­a</FieldLabel>
                                        <select id="category_id" name="category_id" autoComplete="off" value={data.category_id} onChange={(event) => updateField("category_id", event.target.value)} className={`h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-950 outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-300 ${errorFor("category_id") ? "border-rose-300 focus:ring-rose-200" : ""}`}>
                                            <option value="">Selecciona una categorÃ­a</option>
                                            {categories.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                                        </select>
                                        {errorFor("category_id") ? <FieldDescription className="text-rose-600">{errorFor("category_id")}</FieldDescription> : null}
                                    </Field>

                                    <Field>
                                        <div className="space-y-2">
                                            <FieldLabel>Tipo de negocio</FieldLabel>
                                            <FieldDescription>
                                                Elige la operaciÃ³n que mejor representa cÃ³mo vendes hoy.
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
                                                Te sugerimos <strong>{businessTypes.find((item) => item.value === suggestedBusinessType)?.label || "este tipo"}</strong> por la categorÃ­a que elegiste.
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
                                        <select id="city_id" name="city_id" autoComplete="address-level2" value={data.city_id} onChange={(event) => updateField("city_id", event.target.value)} className={`h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-950 outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-300 ${errorFor("city_id") ? "border-rose-300 focus:ring-rose-200" : ""}`}>
                                            <option value="">Selecciona una ciudad</option>
                                            {cities.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                                        </select>
                                        {errorFor("city_id") ? <FieldDescription className="text-rose-600">{errorFor("city_id")}</FieldDescription> : null}
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="whatsapp">WhatsApp</FieldLabel>
                                        <Input id="whatsapp" name="whatsapp" type="tel" autoComplete="tel" inputMode="tel" value={data.whatsapp} onChange={(event) => updateField("whatsapp", event.target.value)} placeholder="+504 9999-9999" className={inputClass("whatsapp")} />
                                        <FieldDescription>Opcional, pero ayuda a conectar pedidos y contacto desde el inicio.</FieldDescription>
                                    </Field>
                                </FieldGroup>
                            ) : null}

                            {step === 2 ? (
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="name">Nombre completo</FieldLabel>
                                        <Input id="name" name="name" autoComplete="name" value={data.name} onChange={(event) => updateField("name", event.target.value)} placeholder="Nombre Apellido" className={inputClass("name")} />
                                        {errorFor("name") ? <FieldDescription className="text-rose-600">{errorFor("name")}</FieldDescription> : null}
                                    </Field>

                                    <Field>
                                        <FieldLabel htmlFor="email">Correo</FieldLabel>
                                        <Input id="email" name="email" type="email" autoComplete="email" spellCheck={false} value={data.email} onChange={(event) => updateField("email", event.target.value)} placeholder="hola@tunegocio.hn" className={inputClass("email")} />
                                        {errorFor("email") ? (
                                            <FieldDescription className="text-rose-600">{errorFor("email")}</FieldDescription>
                                        ) : (
                                            <FieldDescription>Usaremos este correo para login, verificaciÃ³n y recuperaciÃ³n.</FieldDescription>
                                        )}
                                    </Field>

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Field>
                                            <FieldLabel htmlFor="password">ContraseÃ±a</FieldLabel>
                                            <Input id="password" name="password" type="password" autoComplete="new-password" value={data.password} onChange={(event) => updateField("password", event.target.value)} className={inputClass("password")} />
                                            {errorFor("password") ? <FieldDescription className="text-rose-600">{errorFor("password")}</FieldDescription> : <FieldDescription>MÃ­nimo 8 caracteres.</FieldDescription>}
                                        </Field>

                                        <Field>
                                            <FieldLabel htmlFor="password_confirmation">Confirmar contraseÃ±a</FieldLabel>
                                            <Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" value={data.password_confirmation} onChange={(event) => updateField("password_confirmation", event.target.value)} className={inputClass("password_confirmation")} />
                                            {errorFor("password_confirmation") ? <FieldDescription className="text-rose-600">{errorFor("password_confirmation")}</FieldDescription> : null}
                                        </Field>
                                    </div>
                                </FieldGroup>
                            ) : null}

                            {step === 3 ? (
                                <FieldGroup>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <PlanCard active={data.selected_plan === "free"} title="Plan Basic" caption="Lo esencial para iniciar" price="$0" bullets={["Perfil digital pÃºblico", `CatÃ¡logo de ${freeLimit} productos`, "Panel base para operar"]} onClick={() => updateField("selected_plan", "free")} />
                                        <PlanCard active={data.selected_plan === "pro"} recommended title="Plan Business Pro" caption="Potencia total" price={`$${proPrice}`} bullets={["Ventas ilimitadas", "AnalÃ­ticas avanzadas", `Capacidad comercial de ${proLimit} productos`]} onClick={() => updateField("selected_plan", "pro")} />
                                    </div>
                                    {errorFor("selected_plan") ? <FieldDescription className="text-rose-600">{errorFor("selected_plan")}</FieldDescription> : null}

                                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr),300px]">
                                        <div className="space-y-3 rounded-[1.75rem] border border-slate-200 bg-white p-5">
                                            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>Resumen de activaciÃ³n</p>
                                            <SummaryRow label="Negocio" value={businessPreview} />
                                            <SummaryRow label="URL" value={slugPreview} />
                                            <SummaryRow label="CategorÃ­a" value={selectedCategory?.name || "Pendiente"} />
                                            <SummaryRow label="Tipo" value={selectedBusinessType?.label || "Pendiente"} />
                                            <SummaryRow label="Segmento" value={selectedSegment?.label || "Pendiente"} />
                                            <SummaryRow label="Ciudad" value={selectedCity?.name || "Pendiente"} />
                                            <SummaryRow label="Cuenta" value={data.email || "Sin correo aÃºn"} />
                                            <SummaryRow label="Plan" value={data.selected_plan === "pro" ? "Business Pro" : "Basic"} />
                                        </div>

                                        <div className="space-y-4">
                                            <label className="flex items-start gap-3 rounded-[1.75rem] border border-slate-200 bg-slate-50 px-5 py-5">
                                                <input type="checkbox" checked={data.terms_accepted} onChange={(event) => updateField("terms_accepted", event.target.checked)} className="mt-1 h-4 w-4 rounded border-slate-300 text-slate-950 focus:ring-slate-300" />
                                                <span className="text-sm leading-7 text-slate-600">
                                                    Confirmo que tengo autorizaciÃ³n para registrar este negocio y acepto los tÃ©rminos operativos de Conecta HN.
                                                </span>
                                            </label>
                                            {errorFor("terms_accepted") ? <FieldDescription className="text-rose-600">{errorFor("terms_accepted")}</FieldDescription> : null}

                                            <div className="rounded-[1.75rem] bg-slate-950 px-5 py-6 text-white">
                                                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-300" style={{ fontFamily: PUBLIC_LABEL_FONT }}>Lo que sucede al enviar</p>
                                                <p className="mt-3 text-sm leading-7 text-slate-300">
                                                    Creamos cuenta, negocio y acceso al panel en una sola pasada sobre el backend real.
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
                                AtrÃ¡s
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
                                            Crear negocio
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
