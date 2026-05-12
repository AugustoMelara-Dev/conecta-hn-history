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
import {
    AccountStep,
    BusinessStep,
    LocationStep,
    PlanSummaryStep,
} from "@/Components/Auth/RegisterTenantWizardSteps"
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Button } from "@/Components/ui/button"
import { Card } from "@/Components/ui/card"
import { Progress } from "@/Components/ui/progress"
import {
    PUBLIC_BODY_FONT,
    PUBLIC_DISPLAY_FONT,
    PUBLIC_LABEL_FONT,
} from "@/lib/publicTypography"

const STEPS = [
    { key: "business", label: "Negocio", title: "Información del negocio", icon: Building2 },
    { key: "location", label: "Zona", title: "Ubicación y contacto", icon: MapPin },
    { key: "account", label: "Cuenta", title: "Cuenta propietaria", icon: UserRound },
    { key: "plan", label: "Lanzar", title: "Plan y activación", icon: Sparkles },
]

const STEP_FIELDS = {
    0: ["business_name", "slug", "category_id", "commercial_mode"],
    1: ["city_id", "whatsapp"],
    2: ["name", "email", "password", "password_confirmation"],
    3: ["selected_plan", "terms_accepted", "privacy_accepted"],
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

function routeUrlIfExists(name) {
    if (typeof route !== "function") {
        return null
    }

    try {
        const helper = route()
        if (helper && typeof helper.has === "function" && !helper.has(name)) {
            return null
        }
    } catch {}

    try {
        const value = route(name, undefined, false)
        return typeof value === "string" && value !== "" ? value : null
    } catch {
        return null
    }
}
function StepPill({ step, index, currentStep, onClick }) {
    const Icon = step.icon
    const active = index === currentStep
    const complete = index < currentStep
    const locked = index > currentStep

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={locked}
            aria-current={active ? "step" : undefined}
            aria-disabled={locked ? "true" : undefined}
            className={`flex items-center gap-3 rounded-2xl border px-4 py-3 text-left ${
                active
                    ? "border-primary bg-primary text-primary-foreground"
                : complete
                        ? "border-border bg-muted text-foreground"
                        : "border-border bg-background text-muted-foreground hover:border-ring hover:text-foreground"
            }`}
        >
            <span className={`flex size-10 items-center justify-center rounded-xl ${active ? "bg-primary-foreground/10" : complete ? "bg-background" : "bg-muted"}`}>
                {complete ? <Check aria-hidden="true" /> : <Icon aria-hidden="true" />}
            </span>
            <span className="min-w-0">
                <span
                    className={`block text-[10px] font-bold uppercase tracking-[0.22em] ${active ? "text-primary-foreground/70" : "text-muted-foreground"}`}
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    {step.label}
                </span>
                <span className="mt-1 block truncate text-sm font-semibold">{step.title}</span>
            </span>
            {locked ? <span className="sr-only">Paso bloqueado hasta completar los anteriores.</span> : null}
        </button>
    )
}

function SummaryRow({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4 rounded-xl bg-muted px-4 py-3">
            <span className="text-[11px] font-bold uppercase tracking-[0.18em] text-muted-foreground" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                {label}
            </span>
            <span className="text-right text-sm font-semibold text-foreground">{value}</span>
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
    const [pendingFocusField, setPendingFocusField] = useState(null)
    const [slugState, setSlugState] = useState({
        status: "idle",
        message: "La URL se revisa en vivo mientras escribes.",
        suggestions: [],
    })

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        business_name: "",
        slug: "",
        category_id: "",
        commercial_mode: businessTypes[0]?.value || "products",
        business_type: businessTypes[0]?.business_type || "retail",
        rubro_key: businessTypes[0]?.rubro_key || "",
        city_id: "",
        whatsapp: "",
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        selected_plan: "free",
        terms_accepted: false,
        privacy_accepted: false,
        terms_version: settings?.legalVersions?.terms ?? "",
        privacy_version: settings?.legalVersions?.privacy ?? "",
    })

    const errorFor = (field) => localErrors[field] || errors[field] || null
    const selectedCategory = useMemo(
        () => categories.find((item) => String(item.id) === String(data.category_id)),
        [categories, data.category_id]
    )
    const selectedBusinessType = useMemo(
        () => businessTypes.find((item) => item.value === data.commercial_mode) || null,
        [businessTypes, data.commercial_mode]
    )
    const suggestedCategoryForMode = useMemo(() => {
        const mode = String(data.commercial_mode || "").trim()
        if (!mode || categories.length === 0) return null

        const slugByMode = {
            appointments: ["servicios", "salud"],
            products: ["tiendas", "restaurantes"],
            consulting_leads: ["consultorias", "abogados", "servicios"],
        }

        const preferredSlugs = slugByMode[mode] || []
        const bySlug = preferredSlugs
            .map((slug) => categories.find((item) => item?.slug === slug))
            .find(Boolean)

        return bySlug || categories[0] || null
    }, [categories, data.commercial_mode])
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
        const profiles = segmentProfiles.filter((item) => {
            if (item.commercialMode) {
                return item.commercialMode === data.commercial_mode
            }

            return true
        })
        const categoryMatches = selectedCategory?.slug
            ? profiles.filter((item) => item.categories.includes(selectedCategory.slug))
            : []

        return (categoryMatches.length > 0 ? categoryMatches : profiles).slice(0, 4)
    }, [data.commercial_mode, selectedCategory, segmentProfiles])
    const selectedSegment = segmentCandidates[0] || null
    useEffect(() => {
        const nextRubroKey = selectedSegment?.key || selectedBusinessType?.rubro_key || ""

        if (nextRubroKey && data.rubro_key !== nextRubroKey) {
            setData("rubro_key", nextRubroKey)
        }
    }, [data.rubro_key, selectedBusinessType, selectedSegment, setData])
    useEffect(() => {
        if (String(data.category_id || "").trim()) {
            return
        }

        if (!suggestedCategoryForMode?.id) {
            return
        }

        setData("category_id", String(suggestedCategoryForMode.id))
    }, [data.category_id, setData, suggestedCategoryForMode])
    const slugBaseHost = useMemo(() => {
        const currentHost = typeof window !== "undefined" ? normalizeHost(window.location?.host) : null
        const configuredDirectoryHost = normalizeHost(settings?.directoryHost)
        const configuredDirectoryUrl = normalizeHost(settings?.platformDirectoryUrl)
        const forwardedHost = normalizeHost(settings?.requestHost)
        const configuredHost = normalizeHost(settings?.registrationHost)
        const legacyHost = normalizeHost(settings?.slugBaseHost)

        return configuredDirectoryHost || configuredDirectoryUrl || currentHost || forwardedHost || configuredHost || legacyHost || "vito.hn"
    }, [settings?.directoryHost, settings?.platformDirectoryUrl, settings?.registrationHost, settings?.requestHost, settings?.slugBaseHost])
    const registrationHost = useMemo(() => {
        const currentHost = typeof window !== "undefined" ? normalizeHost(window.location?.host) : null
        const forwardedHost = normalizeHost(settings?.requestHost)
        const configuredHost = normalizeHost(settings?.registrationHost)

        return currentHost || forwardedHost || configuredHost || null
    }, [settings?.registrationHost, settings?.requestHost])
    const crossSurfaceHost = Boolean(
        registrationHost
        && slugBaseHost
        && registrationHost.toLowerCase() !== slugBaseHost.toLowerCase()
    )
    const slugCheckCandidates = useMemo(() => {
        const configured = firstValidUrl(settings?.slugCheckUrl)
        const candidates = []

        if (configured) {
            candidates.push(configured)
        }

        const apiSlugRoute = routeUrlIfExists("api.check-slug")
        if (apiSlugRoute) {
            candidates.push(apiSlugRoute)
        }

        candidates.push("/api/check-slug")

        return [...new Set(candidates.filter(Boolean))]
    }, [settings?.slugCheckUrl])
    const registerStoreEndpoint = useMemo(() => {
        const candidates = []

        try {
            candidates.push(route("tenant.register.store", undefined, false))
        } catch {}

        return firstValidUrl(...candidates, "/registrar-negocio") || "/registrar-negocio"
    }, [])
    const legalTermsUrl = useMemo(() => firstValidUrl(routeUrlIfExists("legal.terms"), "/legal/terms"), [])
    const legalPrivacyUrl = useMemo(() => firstValidUrl(routeUrlIfExists("legal.privacy"), "/legal/privacy"), [])
    const directoryUrl = useMemo(() => firstValidUrl(routeUrlIfExists("directory.index"), "/directorio"), [])
    const businessPreview = data.business_name.trim() || "Tu negocio"
    const slugPreview = data.slug ? `${slugify(data.slug)}.${slugBaseHost}` : `tu-negocio.${slugBaseHost}`
    const progress = ((step + 1) / STEPS.length) * 100
    const freeLimit = Number(settings.freeProductLimit ?? 10)
    const proLimit = Number(settings.proProductLimit ?? 100)
    const freePrice = money(settings.freePlanPrice ?? 0)
    const proPrice = money(settings.proPlanPrice ?? 500)
    const canSubmit = categories.length > 0 && cities.length > 0
    const selectedCategoryValue = data.category_id ? String(data.category_id) : "none"
    const selectedCityValue = data.city_id ? String(data.city_id) : "none"

    const focusField = (field) => {
        if (typeof window === "undefined") return

        window.requestAnimationFrame(() => {
            const fallbackId = field === "business_type"
                ? `business-type-${data.commercial_mode}`
                : field === "commercial_mode"
                    ? `business-type-${data.commercial_mode}`
                : field === "selected_plan"
                    ? `plan-${data.selected_plan}`
                    : field
            const node = document.getElementById(field) || document.getElementById(fallbackId)
            if (node && typeof node.focus === "function") {
                node.focus()
            }
        })
    }

    useEffect(() => {
        const firstServerError = Object.keys(errors)[0]
        if (firstServerError) {
            setStep(FIELD_TO_STEP[firstServerError] ?? 0)
            setPendingFocusField(firstServerError)
        }
    }, [errors])

    useEffect(() => {
        if (!pendingFocusField) {
            return
        }

        if ((FIELD_TO_STEP[pendingFocusField] ?? 0) !== step) {
            return
        }

        focusField(pendingFocusField)
        setPendingFocusField(null)
    }, [pendingFocusField, step])

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

        if (!suggestedBusinessType || suggestedBusinessType === data.commercial_mode) {
            return
        }

        const suggestedMode = businessTypes.find((item) => item.value === suggestedBusinessType)
        setData("commercial_mode", suggestedBusinessType)
        if (suggestedMode?.rubro_key) setData("rubro_key", suggestedMode.rubro_key)
    }, [businessTypeTouched, businessTypes, data.commercial_mode, setData, suggestedBusinessType])

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

                let payload = null
                let response = null

                for (const endpoint of slugCheckCandidates) {
                    const endpointController = new AbortController()
                    const syncAbort = () => endpointController.abort()
                    controller.signal.addEventListener("abort", syncAbort, { once: true })
                    const endpointTimeout = window.setTimeout(() => endpointController.abort(), 4000)

                    let candidateResponse
                    try {
                        candidateResponse = await fetch(`${endpoint}?slug=${encodeURIComponent(slug)}`, {
                            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                            signal: endpointController.signal,
                        })
                    } catch {
                        controller.signal.removeEventListener("abort", syncAbort)
                        window.clearTimeout(endpointTimeout)
                        continue
                    }

                    controller.signal.removeEventListener("abort", syncAbort)
                    window.clearTimeout(endpointTimeout)
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
        `h-12 rounded-xl bg-muted px-4 text-foreground placeholder:text-muted-foreground ${
            errorFor(field) ? "border-destructive/50 focus-visible:ring-destructive/20" : ""
        }`

    const validateStep = (targetStep, { focus = false } = {}) => {
        const nextErrors = {}
        const slug = slugify(data.slug)

        if (targetStep === 0) {
            if (!data.business_name.trim()) nextErrors.business_name = "Escribe el nombre comercial."
            if (!slug) nextErrors.slug = "Define una URL para el negocio."
            else if (slug.length < 3) nextErrors.slug = "La URL necesita al menos 3 caracteres."
            else if (slugState.status === "unavailable") nextErrors.slug = "Esa URL ya está ocupada."
            if (!String(data.category_id).trim()) nextErrors.category_id = "Selecciona una categoría."
            if (!String(data.commercial_mode).trim()) nextErrors.commercial_mode = "Selecciona como quieres recibir clientes."
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
            if (!data.privacy_accepted) nextErrors.privacy_accepted = "Debes aceptar la política de privacidad para continuar."
        }

        const fields = STEP_FIELDS[targetStep]
        setLocalErrors((current) => {
            const next = { ...current }
            fields.forEach((field) => delete next[field])
            return { ...next, ...nextErrors }
        })

        const firstInvalid = (fields || []).find((field) => Boolean(nextErrors[field]))
        if (focus && firstInvalid) {
            setPendingFocusField(firstInvalid)
        }

        return Object.keys(nextErrors).length === 0
    }

    const submit = (event) => {
        if (event) event.preventDefault()
        if (!validateStep(step, { focus: true })) {
            return
        }

        post(registerStoreEndpoint, {
            onError: (serverErrors) => {
                const firstField = Object.keys(serverErrors)[0]
                if (firstField) {
                    setStep(FIELD_TO_STEP[firstField] ?? 0)
                    setPendingFocusField(firstField)
                }
            },
        })
    }

    return (
        <AuthSplitLayout
            topActions={
                <>
                    <Button variant="ghost" className="hidden md:inline-flex" asChild>
                        <Link href={route("login")}>Iniciar sesión</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={directoryUrl}>Directorio</Link>
                    </Button>
                </>
            }
            aside={
                <>
                    <div className="flex max-w-xl flex-col gap-6">
                        <div
                            className="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.2em] text-slate-300"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            Onboarding integrado
                        </div>

                        <div className="flex flex-col gap-4">
                            <h2 className="max-w-lg text-4xl font-extrabold leading-[1.02] tracking-tight text-white xl:text-5xl" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Lanza tu negocio en minutos con un registro guiado.
                            </h2>
                            <p className="max-w-lg text-base leading-7 text-slate-300 xl:text-lg">
                                Definimos URL, perfil comercial, cuenta y plan en un solo flujo listo para empezar.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col gap-5">
                        <AuthAsideCard
                            invert
                            eyebrow={`Paso ${step + 1} de ${STEPS.length}`}
                            title={STEPS[step].title}
                            description="Cada paso pide solo lo necesario para activar tu panel sin fricción."
                        >
                            <Progress value={progress} className="mt-6 h-2 bg-white/10 [&>div]:bg-white" />
                        </AuthAsideCard>

                        <AuthAsideCard
                            invert
                            eyebrow="Checklist de activación"
                            title="Lo que queda listo al enviar"
                            description="Este registro no es demo: crea recursos reales dentro de la plataforma."
                        >
                            <div className="mt-4 flex flex-col gap-3">
                                {[
                                    "Cuenta propietaria con login real",
                                    "Negocio con slug validado y aislado",
                                    "Acceso al panel y flujo de verificación",
                                ].map((item) => (
                                    <div key={item} className="flex items-start gap-2 text-sm text-slate-200">
                                        <CheckCircle2 className="mt-0.5 h-4 w-4" />
                                        <span>{item}</span>
                                    </div>
                                ))}
                            </div>
                        </AuthAsideCard>

                        <AuthAsideCard eyebrow="Vista previa" title={businessPreview} description={slugPreview}>
                            <div className="mt-4 flex flex-col gap-3">
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
                <Card className="rounded-[2rem] border-slate-200 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.06)] sm:p-8">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                Registro de negocio
                            </p>
                            <h1 className="text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                                Crea tu negocio con una experiencia más clara.
                            </h1>
                            <p className="max-w-2xl text-sm leading-7 text-slate-500">
                                Cuatro pasos, validaciones en vivo y activación real al finalizar.
                            </p>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4" aria-label="Progreso de registro por pasos">
                            {STEPS.map((item, index) => (
                                <StepPill key={item.key} step={item} index={index} currentStep={step} onClick={() => index <= step && setStep(index)} />
                            ))}
                        </div>
                        <p className="text-xs text-slate-500">
                            Completa cada paso para desbloquear el siguiente.
                        </p>
                    </div>

                    {!canSubmit ? (
                        <Alert variant="destructive" className="mt-8 rounded-2xl">
                            <AlertTitle>Datos incompletos</AlertTitle>
                            <AlertDescription>
                                No pudimos cargar ciudades o categorías. Recarga la página e inténtalo de nuevo.
                            </AlertDescription>
                        </Alert>
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
                                <BusinessStep
                                    data={data}
                                    categories={categories}
                                    businessTypes={businessTypes}
                                    selectedCategoryValue={selectedCategoryValue}
                                    slugBaseHost={slugBaseHost}
                                    registrationHost={registrationHost}
                                    crossSurfaceHost={crossSurfaceHost}
                                    slugState={slugState}
                                    errorFor={errorFor}
                                    inputClass={inputClass}
                                    updateField={updateField}
                                    setSlugTouched={setSlugTouched}
                                    suggestedBusinessType={suggestedBusinessType}
                                    businessTypeTouched={businessTypeTouched}
                                    setBusinessTypeTouched={setBusinessTypeTouched}
                                    selectedSegment={selectedSegment}
                                    segmentCandidates={segmentCandidates}
                                    slugify={slugify}
                                />
                            ) : null}

                            {step === 1 ? (
                                <LocationStep
                                    data={data}
                                    cities={cities}
                                    selectedCityValue={selectedCityValue}
                                    updateField={updateField}
                                    errorFor={errorFor}
                                    inputClass={inputClass}
                                />
                            ) : null}

                            {step === 2 ? (
                                <AccountStep
                                    data={data}
                                    updateField={updateField}
                                    errorFor={errorFor}
                                    inputClass={inputClass}
                                />
                            ) : null}

                            {step === 3 ? (
                                <PlanSummaryStep
                                    data={data}
                                    freeLimit={freeLimit}
                                    proLimit={proLimit}
                                    freePrice={freePrice}
                                    proPrice={proPrice}
                                    businessPreview={businessPreview}
                                    slugPreview={slugPreview}
                                    selectedCategory={selectedCategory}
                                    selectedBusinessType={selectedBusinessType}
                                    selectedSegment={selectedSegment}
                                    selectedCity={selectedCity}
                                    errorFor={errorFor}
                                    updateField={updateField}
                                    legalTermsUrl={legalTermsUrl}
                                    legalPrivacyUrl={legalPrivacyUrl}
                                />
                            ) : null}
                        </form>
                    </div>

                    <div className="mt-8 flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <span className="text-xs font-bold uppercase tracking-[0.2em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                Progreso
                            </span>
                            <Progress value={progress} className="h-2 w-32 bg-slate-200 sm:w-40 [&>div]:bg-slate-950" />
                        </div>

                        <div className="flex items-center justify-end gap-2">
                            <Button type="button" variant="outline" onClick={() => setStep((current) => Math.max(current - 1, 0))} disabled={step === 0 || processing} className="h-12 rounded-xl border-slate-200 bg-white text-slate-950 hover:bg-slate-50">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Atrás
                            </Button>

                            {step < STEPS.length - 1 ? (
                                <Button type="button" data-testid="register-next-step" onClick={() => validateStep(step, { focus: true }) && setStep((current) => current + 1)} disabled={processing || !canSubmit} className="h-12 rounded-xl bg-slate-950 text-white hover:bg-slate-800">
                                    Continuar
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Button>
                            ) : (
                                <Button type="button" data-testid="register-submit" onClick={submit} disabled={processing || !canSubmit} className="h-12 rounded-xl bg-slate-950 text-white hover:bg-slate-800">
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
                </Card>
            </section>
        </AuthSplitLayout>
    )
}
