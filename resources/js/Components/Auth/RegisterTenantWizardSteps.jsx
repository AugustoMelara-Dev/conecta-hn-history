import { CheckCircle2 } from "lucide-react"

import { Button } from "@/Components/ui/button"
import { Checkbox } from "@/Components/ui/checkbox"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { RadioGroup, RadioGroupItem } from "@/Components/ui/radio-group"
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select"

const errorId = (field) => `${field}-error`
const helpId = (field) => `${field}-help`

function describedBy(field, errorFor, fallback = null) {
    return errorFor(field) ? errorId(field) : fallback
}

function PlanCard({ active, recommended = false, title, caption, price, bullets, id, value }) {
    return (
        <label
            htmlFor={id}
            className={`relative cursor-pointer rounded-[1.75rem] border p-6 text-left ${
                active
                    ? "border-primary bg-primary text-primary-foreground"
                    : "border-border bg-muted text-foreground hover:border-ring/40 hover:bg-background"
            }`}
        >
            {recommended ? (
                <span
                    className={`absolute left-6 top-0 -translate-y-1/2 rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] ${active ? "bg-background text-foreground" : "bg-primary text-primary-foreground"}`}
                >
                    Recomendado
                </span>
            ) : null}

            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-2xl font-extrabold tracking-tight">
                        {title}
                    </p>
                    <p
                        className={`mt-1 text-[11px] font-bold uppercase tracking-[0.2em] ${active ? "text-primary-foreground/70" : "text-muted-foreground"}`}
                    >
                        {caption}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-3xl font-extrabold tracking-tight">{price}</p>
                    <RadioGroupItem
                        id={id}
                        value={value}
                        className={`mt-3 ${active ? "border-background bg-background text-foreground" : "border-input bg-background"}`}
                    />
                </div>
            </div>

            <div className="mt-6 flex flex-col gap-3">
                {bullets.map((bullet) => (
                    <div key={bullet} className={`flex items-center gap-2 text-sm ${active ? "text-primary-foreground/90" : "text-foreground/80"}`}>
                        <CheckCircle2 aria-hidden="true" />
                        <span>{bullet}</span>
                    </div>
                ))}
            </div>
        </label>
    )
}

function BusinessTypeCard({ active, title, description, capabilities, examples, id, value, disabled = false, badge = null, starterPack = null }) {
    return (
        <label
            htmlFor={id}
            aria-disabled={disabled ? "true" : undefined}
            className={`cursor-pointer rounded-[1.5rem] border p-5 text-left ${
                disabled
                    ? "border-dashed border-border bg-muted/50 text-muted-foreground"
                    :
                active
                    ? "border-primary bg-primary text-primary-foreground"
                    : "border-border bg-muted text-foreground hover:border-ring/40 hover:bg-background"
            }`}
        >
            <div className="flex items-start justify-between gap-4">
                <div className="flex flex-col gap-2">
                    <p className="text-lg font-extrabold tracking-tight">
                        {title}
                    </p>
                    {badge ? (
                        <span className={`w-fit rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] ${active ? "bg-primary-foreground/10 text-primary-foreground" : "bg-background text-muted-foreground"}`}>
                            {badge}
                        </span>
                    ) : null}
                    <p className={`text-sm leading-7 ${active ? "text-primary-foreground/80" : "text-muted-foreground"}`}>
                        {description}
                    </p>
                    {starterPack ? (
                        <span className={`w-fit rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-[0.16em] ${active ? "bg-primary-foreground/10 text-primary-foreground" : "bg-background text-muted-foreground"}`}>
                            {starterPack}
                        </span>
                    ) : null}
                </div>
                <RadioGroupItem
                    id={id}
                    value={value}
                    disabled={disabled}
                    className={`mt-1 shrink-0 ${active ? "border-background bg-background text-foreground" : "border-input bg-background"}`}
                />
            </div>

            <div className="mt-4 flex flex-col gap-2">
                {capabilities.slice(0, 3).map((capability) => (
                    <div key={capability} className={`flex items-center gap-2 text-sm ${active ? "text-primary-foreground/90" : "text-foreground/80"}`}>
                        <CheckCircle2 aria-hidden="true" />
                        <span>{capability}</span>
                    </div>
                ))}
            </div>

            <p className={`mt-4 text-xs font-semibold uppercase tracking-[0.18em] ${active ? "text-primary-foreground/70" : "text-muted-foreground"}`}>
                Ideal para: {examples.join(", ")}
            </p>
        </label>
    )
}

function SummaryRow({ label, value }) {
    return (
        <div className="flex items-start justify-between gap-4 rounded-xl bg-muted px-4 py-3">
            <span className="text-[11px] font-bold uppercase tracking-[0.18em] text-muted-foreground">
                {label}
            </span>
            <span className="text-right text-sm font-semibold text-foreground">{value}</span>
        </div>
    )
}

export function BusinessStep({
    data,
    categories,
    businessTypes,
    selectedCategoryValue,
    slugBaseHost,
    registrationHost,
    crossSurfaceHost,
    slugState,
    errorFor,
    inputClass,
    updateField,
    setSlugTouched,
    suggestedBusinessType,
    businessTypeTouched,
    setBusinessTypeTouched,
    selectedSegment,
    segmentCandidates,
    slugify,
}) {
    return (
        <FieldGroup>
            <Field data-invalid={errorFor("business_name") ? "true" : undefined}>
                <FieldLabel htmlFor="business_name">Nombre comercial</FieldLabel>
                <Input id="business_name" name="business_name" autoComplete="organization" value={data.business_name} onChange={(event) => updateField("business_name", event.target.value)} placeholder="Ej. Cafe La Esperanza" className={inputClass("business_name")} aria-invalid={Boolean(errorFor("business_name"))} aria-describedby={describedBy("business_name", errorFor)} data-testid="register-business-name-input" />
                {errorFor("business_name") ? <FieldDescription id={errorId("business_name")} role="alert" className="text-destructive">{errorFor("business_name")}</FieldDescription> : null}
            </Field>

            <Field data-invalid={errorFor("slug") ? "true" : undefined}>
                <div className="flex items-center justify-between gap-3">
                    <FieldLabel htmlFor="slug">URL personalizada</FieldLabel>
                    <span
                        data-testid="register-slug-status"
                        className={`text-[11px] font-bold uppercase tracking-[0.18em] ${slugState.status === "available" ? "text-foreground" : slugState.status === "unavailable" || slugState.status === "error" ? "text-destructive" : "text-muted-foreground"}`}
                    >
                        {slugState.status === "available" ? "Disponible" : slugState.status === "checking" ? "Revisando" : slugState.status === "unavailable" ? "Ocupada" : "Pendiente"}
                    </span>
                </div>
                <div className={`grid h-12 grid-cols-[minmax(90px,auto)_auto_1fr] items-center gap-1 rounded-xl border bg-muted px-3 text-sm focus-within:ring-4 ${
                    errorFor("slug")
                        ? "border-destructive/50 focus-within:border-destructive/50 focus-within:ring-destructive/20"
                        : "border-border focus-within:border-primary focus-within:ring-ring/30"
                }`}>
                    <span className="truncate text-xs text-muted-foreground" title={slugBaseHost}>
                        {slugBaseHost}
                    </span>
                    <span className="text-xs text-muted-foreground">/t/</span>
                    <Input id="slug" name="slug" data-testid="register-slug-input" autoComplete="off"
                        value={data.slug}
                        onChange={(event) => {
                            setSlugTouched(true)
                            updateField("slug", slugify(event.target.value))
                        }}
                        placeholder="tunegocio"
                        aria-invalid={Boolean(errorFor("slug"))}
                        aria-describedby="slug-help"
                        className="h-full w-full border-0 bg-transparent px-1 text-foreground shadow-none placeholder:text-muted-foreground focus-visible:ring-0 focus-visible:ring-offset-0"
                    />
                </div>
                <FieldDescription id="slug-help" aria-live="polite" role={errorFor("slug") ? "alert" : undefined} className={slugState.status === "unavailable" || slugState.status === "error" || errorFor("slug") ? "text-destructive" : "text-muted-foreground"}>
                    {errorFor("slug") || slugState.message}
                </FieldDescription>
                {crossSurfaceHost ? (
                    <FieldDescription className="text-muted-foreground">
                        Tu panel opera en {registrationHost} y tu micrositio publico se publica como subdominio de {slugBaseHost}.
                    </FieldDescription>
                ) : null}
                {slugState.suggestions?.length ? (
                    <div className="flex flex-col gap-2">
                        <p className="text-xs font-semibold text-muted-foreground">Opciones sugeridas:</p>
                        <div className="flex flex-wrap gap-2">
                            {slugState.suggestions.map((suggestion) => (
                                <Button key={suggestion} type="button" variant="outline" onClick={() => { setSlugTouched(true); updateField("slug", suggestion) }} className="h-8 rounded-full border-border bg-background px-3 text-xs font-semibold text-foreground/80 hover:border-ring/40 hover:text-foreground">
                                    {suggestion}
                                </Button>
                            ))}
                        </div>
                    </div>
                ) : null}
            </Field>

            <Field data-invalid={errorFor("category_id") ? "true" : undefined}>
                <FieldLabel htmlFor="category_id">Rubro de referencia</FieldLabel>
                <Select
                    value={selectedCategoryValue}
                    onValueChange={(value) => updateField("category_id", value === "none" ? "" : value)}
                >
                    <SelectTrigger
                        id="category_id"
                        data-testid="register-category-select"
                        aria-invalid={Boolean(errorFor("category_id"))}
                        aria-describedby={describedBy("category_id", errorFor)}
                        className={`h-12 rounded-xl border-border bg-muted px-4 text-foreground focus:ring-4 focus:ring-ring/30 ${errorFor("category_id") ? "border-destructive/50 focus:ring-destructive/20" : ""}`}
                    >
                        <SelectValue placeholder="Selecciona una categoria base" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="none">Selecciona una categoria base</SelectItem>
                            {categories.map((item) => (
                                <SelectItem key={item.id} value={String(item.id)}>{item.name}</SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                {errorFor("category_id") ? (
                    <FieldDescription id={errorId("category_id")} role="alert" className="text-destructive">{errorFor("category_id")}</FieldDescription>
                ) : (
                    <FieldDescription>Se usa para SEO y recomendaciones. Tu operacion la define el modo comercial.</FieldDescription>
                )}
            </Field>

            <Field data-invalid={errorFor("commercial_mode") ? "true" : undefined}>
                <div className="flex flex-col gap-2">
                    <FieldLabel>Como quieres recibir clientes</FieldLabel>
                    <FieldDescription id={helpId("commercial_mode")}>
                        Elige la ruta comercial principal. La categoria queda para SEO y recomendaciones, no para limitar tu negocio.
                    </FieldDescription>
                </div>

                <RadioGroup
                    name="commercial_mode"
                    value={data.commercial_mode}
                    onValueChange={(value) => {
                        const nextType = businessTypes.find((item) => item.value === value)
                        if (nextType?.disabled) return
                        setBusinessTypeTouched(true)
                        updateField("commercial_mode", value)
                        if (nextType?.business_type) updateField("business_type", nextType.business_type)
                        if (nextType?.rubro_key) updateField("rubro_key", nextType.rubro_key)
                    }}
                    className="grid gap-4 xl:grid-cols-2"
                    aria-label="Ruta comercial"
                    aria-describedby={describedBy("commercial_mode", errorFor, helpId("commercial_mode"))}
                >
                    {businessTypes.map((item) => (
                        <BusinessTypeCard
                            key={item.value}
                            id={`business-type-${item.value}`}
                            value={item.value}
                            active={data.commercial_mode === item.value}
                            title={item.label}
                            description={item.description}
                            capabilities={item.capabilities}
                            examples={item.examples}
                            disabled={Boolean(item.disabled)}
                            badge={item.badge}
                            starterPack={item.starter_pack ?? null}
                        />
                    ))}
                </RadioGroup>

                {suggestedBusinessType && !businessTypeTouched ? (
                    <FieldDescription>
                        Te sugerimos <strong>{businessTypes.find((item) => item.value === suggestedBusinessType)?.label || "esta ruta"}</strong> por la categoria que elegiste.
                    </FieldDescription>
                ) : null}

                {selectedSegment ? (
                    <div className="rounded-[1.75rem] border border-primary/20 bg-gradient-to-br from-primary/10 via-white to-accent p-5 shadow-[0_20px_50px_rgba(16,185,129,0.08)]">
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <span
                                    className="inline-flex rounded-full bg-primary px-3 py-1 text-[10px] font-bold uppercase tracking-[0.22em] text-primary-foreground"
                                >
                                    Segmento sugerido
                                </span>
                                <span
                                    className="inline-flex rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-primary"
                                >
                                    {selectedSegment.label}
                                </span>
                            </div>

                            <div className="flex flex-col gap-2">
                                <h3 className="text-xl font-extrabold tracking-tight text-foreground">
                                    {selectedSegment.onboardingTitle}
                                </h3>
                                <p className="text-sm leading-7 text-muted-foreground">
                                    {selectedSegment.onboardingCopy || selectedSegment.teaser}
                                </p>
                            </div>

                            {selectedSegment.modules?.length ? (
                                <div className="flex flex-wrap gap-2">
                                    {selectedSegment.modules.slice(0, 4).map((module) => (
                                        <span
                                            key={module}
                                            className="rounded-full border border-border bg-background px-3 py-1 text-[11px] font-semibold text-foreground/80"
                                        >
                                            {module}
                                        </span>
                                    ))}
                                </div>
                            ) : null}

                            {segmentCandidates.length > 1 ? (
                                <div className="flex flex-col gap-2">
                                    <p
                                        className="text-[11px] font-bold uppercase tracking-[0.2em] text-muted-foreground"
                                    >
                                        Rutas cercanas para este rubro
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {segmentCandidates.slice(1).map((segment) => (
                                            <span
                                                key={segment.key}
                                                className="rounded-full border border-border bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
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

                {errorFor("commercial_mode") ? <FieldDescription id={errorId("commercial_mode")} role="alert" className="text-destructive">{errorFor("commercial_mode")}</FieldDescription> : null}
            </Field>
        </FieldGroup>
    )
}

export function LocationStep({ data, cities, selectedCityValue, updateField, errorFor, inputClass }) {
    return (
        <FieldGroup>
            <Field data-invalid={errorFor("city_id") ? "true" : undefined}>
                <FieldLabel htmlFor="city_id">Ciudad</FieldLabel>
                <Select
                    value={selectedCityValue}
                    onValueChange={(value) => updateField("city_id", value === "none" ? "" : value)}
                >
                    <SelectTrigger
                        id="city_id"
                        data-testid="register-city-select"
                        aria-invalid={Boolean(errorFor("city_id"))}
                        aria-describedby={describedBy("city_id", errorFor)}
                        className={`h-12 rounded-xl border-border bg-muted px-4 text-foreground focus:ring-4 focus:ring-ring/30 ${errorFor("city_id") ? "border-destructive/50 focus:ring-destructive/20" : ""}`}
                    >
                        <SelectValue placeholder="Selecciona una ciudad" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="none">Selecciona una ciudad</SelectItem>
                            {cities.map((item) => (
                                <SelectItem key={item.id} value={String(item.id)}>{item.name}</SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                {errorFor("city_id") ? <FieldDescription id={errorId("city_id")} role="alert" className="text-destructive">{errorFor("city_id")}</FieldDescription> : null}
            </Field>

            <Field>
                <FieldLabel htmlFor="whatsapp">WhatsApp</FieldLabel>
                <Input id="whatsapp" name="whatsapp" type="tel" autoComplete="tel" inputMode="tel" value={data.whatsapp} onChange={(event) => updateField("whatsapp", event.target.value)} placeholder="+504 9999-9999" className={inputClass("whatsapp")} aria-invalid={Boolean(errorFor("whatsapp"))} aria-describedby={describedBy("whatsapp", errorFor, helpId("whatsapp"))} />
                {errorFor("whatsapp") ? (
                    <FieldDescription id={errorId("whatsapp")} role="alert" className="text-destructive">{errorFor("whatsapp")}</FieldDescription>
                ) : (
                    <FieldDescription id={helpId("whatsapp")}>Opcional, pero ayuda a centralizar pedidos y contacto desde el inicio.</FieldDescription>
                )}
            </Field>
        </FieldGroup>
    )
}

export function AccountStep({ data, updateField, errorFor, inputClass }) {
    return (
        <FieldGroup>
            <Field data-invalid={errorFor("name") ? "true" : undefined}>
                <FieldLabel htmlFor="name">Nombre completo</FieldLabel>
                <Input id="name" name="name" autoComplete="name" value={data.name} onChange={(event) => updateField("name", event.target.value)} placeholder="Nombre Apellido" className={inputClass("name")} aria-invalid={Boolean(errorFor("name"))} aria-describedby={describedBy("name", errorFor)} />
                {errorFor("name") ? <FieldDescription id={errorId("name")} role="alert" className="text-destructive">{errorFor("name")}</FieldDescription> : null}
            </Field>

            <Field data-invalid={errorFor("email") ? "true" : undefined}>
                <FieldLabel htmlFor="email">Correo</FieldLabel>
                <Input id="email" name="email" type="email" autoComplete="email" inputMode="email" spellCheck={false} value={data.email} onChange={(event) => updateField("email", event.target.value)} placeholder="hola@tunegocio.hn" className={inputClass("email")} aria-invalid={Boolean(errorFor("email"))} aria-describedby={describedBy("email", errorFor, helpId("email"))} />
                {errorFor("email") ? (
                    <FieldDescription id={errorId("email")} role="alert" className="text-destructive">{errorFor("email")}</FieldDescription>
                ) : (
                    <FieldDescription id={helpId("email")}>Usaremos este correo para login, verificacion y recuperacion.</FieldDescription>
                )}
            </Field>

            <div className="grid gap-4 md:grid-cols-2">
                <Field data-invalid={errorFor("password") ? "true" : undefined}>
                    <FieldLabel htmlFor="password">Contrasena</FieldLabel>
                    <Input id="password" name="password" type="password" autoComplete="new-password" value={data.password} onChange={(event) => updateField("password", event.target.value)} className={inputClass("password")} aria-invalid={Boolean(errorFor("password"))} aria-describedby={describedBy("password", errorFor, helpId("password"))} />
                    {errorFor("password") ? <FieldDescription id={errorId("password")} role="alert" className="text-destructive">{errorFor("password")}</FieldDescription> : <FieldDescription id={helpId("password")}>Minimo 8 caracteres.</FieldDescription>}
                </Field>

                <Field data-invalid={errorFor("password_confirmation") ? "true" : undefined}>
                    <FieldLabel htmlFor="password_confirmation">Confirmar contrasena</FieldLabel>
                    <Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" value={data.password_confirmation} onChange={(event) => updateField("password_confirmation", event.target.value)} className={inputClass("password_confirmation")} aria-invalid={Boolean(errorFor("password_confirmation"))} aria-describedby={describedBy("password_confirmation", errorFor)} />
                    {errorFor("password_confirmation") ? <FieldDescription id={errorId("password_confirmation")} role="alert" className="text-destructive">{errorFor("password_confirmation")}</FieldDescription> : null}
                </Field>
            </div>
        </FieldGroup>
    )
}

export function PlanSummaryStep({
    data,
    freeLimit,
    proLimit,
    freePrice,
    proPrice,
    businessPreview,
    slugPreview,
    selectedCategory,
    selectedBusinessType,
    selectedSegment,
    selectedCity,
    errorFor,
    updateField,
    legalTermsUrl,
    legalPrivacyUrl,
}) {
    const mode = selectedBusinessType?.value || data.commercial_mode
    const modeBasicBullets = {
        appointments: [
            "Perfil digital publico",
            "Servicios visibles y contacto para validar demanda",
            `Hasta ${freeLimit} productos/servicios publicados`,
        ],
        consulting_leads: [
            "Perfil digital publico",
            "Landing consultiva y CTA principal activos",
            `Hasta ${freeLimit} ofertas o servicios publicados`,
        ],
        products: [
            "Perfil digital publico",
            `Catalogo de hasta ${freeLimit} productos`,
            "Directorio, WhatsApp, llamada y mapa para validar",
        ],
        hybrid: [
            "Perfil digital publico",
            "Operacion mixta disponible solo por piloto",
            "Panel base con activacion por allowlist",
        ],
    }
    const modeProBullets = {
        appointments: [
            "Reservas avanzadas y agenda con mas control",
            "Reportes, campanas e IA operativa activable",
            `Capacidad comercial ampliada hasta ${proLimit} productos/servicios`,
        ],
        consulting_leads: [
            "Captura y seguimiento comercial ampliados",
            "Reportes, campanas e IA operativa activable",
            "Capacidad comercial ampliada para consultas y conversion",
        ],
        products: [
            `Capacidad comercial de hasta ${proLimit} productos`,
            "Reservas/reportes y campanas segun capacidades",
            "Agente IA privado con activacion segura por tenant",
        ],
        hybrid: [
            "Operacion mixta avanzada",
            "Analiticas avanzadas",
            "Capacidad comercial ampliada por piloto",
        ],
    }
    const basicBullets = modeBasicBullets[mode] || modeBasicBullets.products
    const proBullets = modeProBullets[mode] || modeProBullets.products

    return (
        <FieldGroup>
            <RadioGroup
                name="selected_plan"
                value={data.selected_plan}
                onValueChange={(value) => updateField("selected_plan", value)}
                className="grid gap-4 md:grid-cols-2"
                aria-label="Plan de activacion"
                aria-describedby={describedBy("selected_plan", errorFor)}
            >
                <PlanCard id="plan-free" value="free" active={data.selected_plan === "free"} title="Plan Basic" caption="Lo esencial para iniciar" price={`L. ${freePrice}`} bullets={basicBullets} />
                <PlanCard id="plan-pro" value="pro" active={data.selected_plan === "pro"} recommended title="Plan Business Pro" caption="Potencia total" price={`L. ${proPrice}`} bullets={proBullets} />
            </RadioGroup>
            {errorFor("selected_plan") ? <FieldDescription id={errorId("selected_plan")} role="alert" className="text-destructive">{errorFor("selected_plan")}</FieldDescription> : null}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr),300px]">
                <div className="flex flex-col gap-3 rounded-[1.75rem] border border-border bg-background p-5">
                    <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-muted-foreground">Resumen de activacion</p>
                    <SummaryRow label="Negocio" value={businessPreview} />
                    <SummaryRow label="URL" value={slugPreview} />
                    <SummaryRow label="Categoria" value={selectedCategory?.name || "Pendiente"} />
                    <SummaryRow label="Tipo" value={selectedBusinessType?.label || "Pendiente"} />
                    <SummaryRow label="Segmento" value={selectedSegment?.label || "Pendiente"} />
                    <SummaryRow label="Ciudad" value={selectedCity?.name || "Pendiente"} />
                    <SummaryRow label="Cuenta" value={data.email || "Sin correo aun"} />
                    <SummaryRow label="Plan" value={data.selected_plan === "pro" ? "Business Pro" : "Basic"} />
                </div>

                <div className="flex flex-col gap-4">
                    <label className="flex items-start gap-3 rounded-[1.75rem] border border-border bg-muted px-5 py-5">
                        <Checkbox
                            id="terms_accepted"
                            name="terms_accepted"
                            data-testid="register-terms-checkbox"
                            checked={data.terms_accepted}
                            onCheckedChange={(checked) => updateField("terms_accepted", checked === true)}
                            aria-invalid={Boolean(errorFor("terms_accepted"))}
                            aria-describedby={describedBy("terms_accepted", errorFor, helpId("terms_accepted"))}
                            className="mt-1 border-input text-primary-foreground data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                        />
                        <span id={helpId("terms_accepted")} className="text-sm leading-7 text-muted-foreground">
                            Confirmo que tengo autorización para registrar este negocio y acepto los {" "}
                            <a href={legalTermsUrl} target="_blank" rel="noreferrer" className="font-semibold underline underline-offset-2">
                                términos de servicio
                            </a>.
                        </span>
                    </label>
                    {errorFor("terms_accepted") ? <FieldDescription id={errorId("terms_accepted")} role="alert" className="text-destructive">{errorFor("terms_accepted")}</FieldDescription> : null}

                    <label className="flex items-start gap-3 rounded-[1.75rem] border border-border bg-muted px-5 py-5">
                        <Checkbox
                            id="privacy_accepted"
                            name="privacy_accepted"
                            data-testid="register-privacy-checkbox"
                            checked={data.privacy_accepted}
                            onCheckedChange={(checked) => updateField("privacy_accepted", checked === true)}
                            aria-invalid={Boolean(errorFor("privacy_accepted"))}
                            aria-describedby={describedBy("privacy_accepted", errorFor, helpId("privacy_accepted"))}
                            className="mt-1 border-input text-primary-foreground data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                        />
                        <span id={helpId("privacy_accepted")} className="text-sm leading-7 text-muted-foreground">
                            Confirmo que leí y acepto la {" "}
                            <a href={legalPrivacyUrl} target="_blank" rel="noreferrer" className="font-semibold underline underline-offset-2">
                                política de privacidad
                            </a>{" "}
                            y el tratamiento de datos para esta cuenta.
                        </span>
                    </label>
                    {errorFor("privacy_accepted") ? <FieldDescription id={errorId("privacy_accepted")} role="alert" className="text-destructive">{errorFor("privacy_accepted")}</FieldDescription> : null}

                    <div className="rounded-[1.75rem] bg-primary px-5 py-6 text-primary-foreground">
                        <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-primary-foreground/70">Lo que sucede al enviar</p>
                        <p className="mt-3 text-sm leading-7 text-primary-foreground/70">
                            Creamos cuenta, negocio y acceso al panel en una sola pasada sobre el backend real.
                        </p>
                    </div>
                </div>
            </div>
        </FieldGroup>
    )
}
