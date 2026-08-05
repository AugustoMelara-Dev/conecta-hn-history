import { Head } from "@inertiajs/react"
import axios, { AxiosError } from "axios"
import { zodResolver } from "@hookform/resolvers/zod"
import {
    AlertCircle,
    ArrowLeft,
    Camera,
    Check,
    CheckCircle2,
    ChevronRight,
    CircleHelp,
    Clock3,
    ImageIcon,
    LoaderCircle,
    PackageCheck,
    Plus,
    RefreshCw,
    Sparkles,
    Trash2,
    UploadCloud,
    X,
} from "lucide-react"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"
import { useFieldArray, useForm } from "react-hook-form"
import { toast } from "sonner"
import { z } from "zod"

import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import { Label } from "@/Components/ui/label"
import { Progress } from "@/Components/ui/progress"
import { Textarea } from "@/Components/ui/textarea"
import { cn } from "@/lib/utils"

type Category = { id: number; name: string }
type Tenant = { id: string; name: string; is_pro: boolean }
type WorkspaceProps = {
    tenant: Tenant
    available: boolean
    limits: { products: number; images_per_batch: number; image_size_mb: number }
    categories: Category[]
    latest_batch_id: string | null
    api_url: string
    products_url: string
    upgrade_url: string
}

type CatalogPayload = {
    name?: string | null
    description?: string | null
    short_description?: string | null
    category_id?: number | null
    category_suggestion?: string | null
    tags?: string[] | null
    colors?: string[] | null
    material?: string | null
    condition?: string | null
    warnings?: string[] | null
    price?: number | string | null
    stock_quantity?: number | string | null
    unit?: string | null
    dimensions?: { length?: number | null; width?: number | null; height?: number | null; unit?: string | null } | null
    variations?: Array<{ name?: string | null; options?: string[] | null }> | null
    review_reasons?: string[] | null
}

type ImportItem = {
    id: string
    status: string
    original_name: string
    mime_type?: string
    size_bytes?: number
    product_id?: string | number | null
    suggestion?: CatalogPayload | null
    review?: CatalogPayload | null
    field_confidence?: Record<string, number> | null
    overall_confidence?: number | null
    error?: { code: string; message: string } | null
}

type ImportBatch = {
    id: string
    status: string
    total_items: number
    processed_items?: number
    failed_items?: number
    items: ImportItem[]
}

const formSchema = z.object({
    name: z.string().trim().min(1, "Confirma el nombre").max(160),
    description: z.string().max(2000),
    short_description: z.string().max(320),
    category_id: z.string(),
    category_suggestion: z.string().max(100),
    tags: z.string().max(500),
    colors: z.string().max(500),
    material: z.string().max(160),
    condition: z.string().max(80),
    warnings: z.string().max(1000),
    price: z.string().refine((value) => value === "" || (Number.isFinite(Number(value)) && Number(value) >= 0), "Precio inválido"),
    stock_quantity: z.string().refine((value) => value === "" || (Number.isInteger(Number(value)) && Number(value) >= 0), "Existencias inválidas"),
    unit: z.string().max(40),
    dimension_length: z.string(),
    dimension_width: z.string(),
    dimension_height: z.string(),
    dimension_unit: z.string().max(20),
    variations: z.array(z.object({ name: z.string().max(80), options: z.string().max(300) })),
})

type FormValues = z.infer<typeof formSchema>

const emptyValues: FormValues = {
    name: "",
    description: "",
    short_description: "",
    category_id: "",
    category_suggestion: "",
    tags: "",
    colors: "",
    material: "",
    condition: "",
    warnings: "",
    price: "",
    stock_quantity: "",
    unit: "unidad",
    dimension_length: "",
    dimension_width: "",
    dimension_height: "",
    dimension_unit: "cm",
    variations: [],
}

const terminalBatchStatuses = new Set(["needs_review", "approved", "published", "rejected", "failed"])
const editableStatuses = new Set(["needs_review"])

const statusMeta: Record<string, { label: string; className: string }> = {
    uploaded: { label: "Cargada", className: "bg-slate-100 text-slate-700" },
    queued: { label: "En espera", className: "bg-amber-50 text-amber-700" },
    processing: { label: "Analizando", className: "bg-sky-50 text-sky-700" },
    needs_review: { label: "Revisar", className: "bg-violet-50 text-violet-700" },
    approved: { label: "Aprobado", className: "bg-emerald-50 text-emerald-700" },
    published: { label: "Publicado", className: "bg-emerald-50 text-emerald-700" },
    failed: { label: "Falló", className: "bg-red-50 text-red-700" },
    rejected: { label: "Descartado", className: "bg-slate-100 text-slate-500" },
}

function splitValues(value: string): string[] {
    return value.split(",").map((item) => item.trim()).filter(Boolean)
}

function numberOrNull(value: string): number | null {
    return value === "" ? null : Number(value)
}

function valuesFromItem(item: ImportItem): FormValues {
    const data = item.review ?? item.suggestion ?? {}
    return {
        ...emptyValues,
        name: data.name ?? "",
        description: data.description ?? "",
        short_description: data.short_description ?? "",
        category_id: data.category_id ? String(data.category_id) : "",
        category_suggestion: data.category_suggestion ?? "",
        tags: (data.tags ?? []).join(", "),
        colors: (data.colors ?? []).join(", "),
        material: data.material ?? "",
        condition: data.condition ?? "",
        warnings: (data.warnings ?? []).join(", "),
        price: data.price == null ? "" : String(data.price),
        stock_quantity: data.stock_quantity == null ? "" : String(data.stock_quantity),
        unit: data.unit ?? "unidad",
        dimension_length: data.dimensions?.length == null ? "" : String(data.dimensions.length),
        dimension_width: data.dimensions?.width == null ? "" : String(data.dimensions.width),
        dimension_height: data.dimensions?.height == null ? "" : String(data.dimensions.height),
        dimension_unit: data.dimensions?.unit ?? "cm",
        variations: (data.variations ?? []).map((variation) => ({
            name: variation.name ?? "",
            options: (variation.options ?? []).join(", "),
        })),
    }
}

function readableError(error: unknown): string {
    if (!(error instanceof AxiosError)) return "No pudimos completar la acción. Inténtalo de nuevo."
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    const firstValidation = data?.errors ? Object.values(data.errors).flat()[0] : undefined
    return firstValidation ?? data?.message ?? "No pudimos completar la acción. Inténtalo de nuevo."
}

function WorkspaceHeader({ workspace }: { workspace: WorkspaceProps }) {
    const initial = workspace.tenant.name.trim().charAt(0).toUpperCase() || "N"
    return (
        <header className="border-b bg-white">
            <div className="mx-auto flex h-16 max-w-screen-2xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div className="flex min-w-0 items-center gap-3">
                    <a href={workspace.products_url} className="inline-flex h-9 w-9 items-center justify-center rounded-lg border text-slate-600 transition hover:bg-slate-50" aria-label="Volver a productos">
                        <ArrowLeft className="h-4 w-4" />
                    </a>
                    <div className="flex items-center gap-2">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-950 text-white"><Sparkles className="h-4 w-4" /></span>
                        <span className="hidden font-display text-base font-bold sm:inline">Dovra AI</span>
                    </div>
                    <span className="hidden h-5 w-px bg-slate-200 sm:block" />
                    <span className="truncate text-sm font-medium text-slate-600">{workspace.tenant.name}</span>
                </div>
                <div className="flex items-center gap-2">
                    <a href={workspace.products_url} className="hidden text-sm font-semibold text-slate-600 hover:text-slate-950 sm:inline">Productos</a>
                    <button type="button" className="inline-flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="Ayuda"><CircleHelp className="h-5 w-5" /></button>
                    <span className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-800">{initial}</span>
                </div>
            </div>
        </header>
    )
}

function PlanUnavailable({ workspace }: { workspace: WorkspaceProps }) {
    return (
        <div className="mx-auto flex max-w-3xl flex-col items-center px-6 py-24 text-center">
            <span className="mb-6 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-sky-50 text-sky-600"><Sparkles className="h-8 w-8" /></span>
            <h1 className="font-display text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Digitalización inteligente</h1>
            <p className="mt-4 max-w-xl text-base leading-7 text-slate-600">Convierte fotos de tus productos en un catálogo editable. La IA propone la información y tú mantienes el control antes de publicar.</p>
            <a href={workspace.upgrade_url} className="mt-8 inline-flex h-11 items-center justify-center rounded-lg bg-slate-950 px-6 text-sm font-semibold text-white transition hover:bg-slate-800">Mejorar mi plan <ChevronRight className="ml-2 h-4 w-4" /></a>
        </div>
    )
}

function ItemStatusIcon({ status }: { status: string }) {
    if (status === "processing" || status === "queued" || status === "uploaded") return <LoaderCircle className="h-4 w-4 animate-spin text-sky-600" />
    if (status === "failed") return <AlertCircle className="h-4 w-4 text-red-600" />
    if (status === "approved" || status === "published") return <CheckCircle2 className="h-4 w-4 text-emerald-600" />
    if (status === "rejected") return <X className="h-4 w-4 text-slate-400" />
    return <Clock3 className="h-4 w-4 text-violet-600" />
}

function FieldHint({ item, field }: { item: ImportItem; field: string }) {
    const confidence = item.field_confidence?.[field]
    if (confidence == null) return null
    const percent = Math.round(confidence * 100)
    return <span className={cn("text-xs font-medium", percent < 70 ? "text-amber-700" : "text-slate-500")}>{percent}% confianza</span>
}

export default function CatalogImportWorkspace({ workspace }: { workspace: WorkspaceProps }) {
    const [batch, setBatch] = useState<ImportBatch | null>(null)
    const [selectedId, setSelectedId] = useState<string | null>(null)
    const [busyAction, setBusyAction] = useState<string | null>(null)
    const [uploadProgress, setUploadProgress] = useState<number | null>(null)
    const [errorMessage, setErrorMessage] = useState<string | null>(null)
    const [sourceUrl, setSourceUrl] = useState<string | null>(null)
    const [isDragging, setIsDragging] = useState(false)
    const selectInput = useRef<HTMLInputElement>(null)
    const cameraInput = useRef<HTMLInputElement>(null)

    const requestConfig = useMemo(() => ({ headers: { "X-Tenant-ID": workspace.tenant.id, Accept: "application/json" } }), [workspace.tenant.id])
    const selectedItem = useMemo(() => batch?.items.find((item) => item.id === selectedId) ?? null, [batch, selectedId])
    const reviewableCount = batch?.items.filter((item) => item.status === "needs_review").length ?? 0
    const completedCount = batch?.items.filter((item) => ["needs_review", "approved", "published", "failed", "rejected"].includes(item.status)).length ?? 0
    const batchProgress = batch?.total_items ? Math.round((completedCount / batch.total_items) * 100) : 0

    const form = useForm<FormValues>({ resolver: zodResolver(formSchema), defaultValues: emptyValues })
    const variations = useFieldArray({ control: form.control, name: "variations" })

    const fetchBatch = useCallback(async (batchId: string, silent = false) => {
        try {
            const response = await axios.get<{ data: ImportBatch }>(`${workspace.api_url}/${batchId}`, requestConfig)
            setBatch(response.data.data)
            setSelectedId((current) => current && response.data.data.items.some((item) => item.id === current)
                ? current
                : (response.data.data.items.find((item) => item.status === "needs_review") ?? response.data.data.items[0])?.id ?? null)
            if (!silent) setErrorMessage(null)
            return response.data.data
        } catch (error) {
            if (!silent) setErrorMessage(readableError(error))
            return null
        }
    }, [requestConfig, workspace.api_url])

    useEffect(() => {
        if (workspace.latest_batch_id) void fetchBatch(workspace.latest_batch_id)
    }, [fetchBatch, workspace.latest_batch_id])

    useEffect(() => {
        if (!batch || terminalBatchStatuses.has(batch.status)) return
        const timer = window.setTimeout(() => void fetchBatch(batch.id, true), 2000)
        return () => window.clearTimeout(timer)
    }, [batch, fetchBatch])

    useEffect(() => {
        if (!selectedItem) return
        form.reset(valuesFromItem(selectedItem))
    }, [form, selectedItem])

    useEffect(() => {
        let objectUrl: string | null = null
        if (!batch || !selectedItem) {
            setSourceUrl(null)
            return
        }
        void axios.get(`${workspace.api_url}/${batch.id}/items/${selectedItem.id}/source`, { ...requestConfig, responseType: "blob" })
            .then((response) => {
                objectUrl = URL.createObjectURL(response.data)
                setSourceUrl(objectUrl)
            })
            .catch(() => setSourceUrl(null))
        return () => { if (objectUrl) URL.revokeObjectURL(objectUrl) }
    }, [batch?.id, requestConfig, selectedItem?.id, workspace.api_url])

    const uploadFiles = async (files: File[]) => {
        setErrorMessage(null)
        if (files.length === 0) return
        if (files.length > workspace.limits.images_per_batch) {
            setErrorMessage(`Puedes subir un máximo ${workspace.limits.images_per_batch} ${workspace.limits.images_per_batch === 1 ? "imagen" : "imágenes"} por lote.`)
            return
        }
        const maxBytes = workspace.limits.image_size_mb * 1024 * 1024
        const invalid = files.find((file) => !["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > maxBytes)
        if (invalid) {
            setErrorMessage(`“${invalid.name}” no es JPG, PNG o WebP válido de hasta ${workspace.limits.image_size_mb} MB.`)
            return
        }
        const data = new FormData()
        files.forEach((file) => data.append("images[]", file))
        setUploadProgress(0)
        try {
            const response = await axios.post<{ data: ImportBatch }>(workspace.api_url, data, {
                ...requestConfig,
                onUploadProgress: (event) => setUploadProgress(event.total ? Math.round((event.loaded / event.total) * 100) : 20),
            })
            setBatch(response.data.data)
            setSelectedId(response.data.data.items[0]?.id ?? null)
            toast.success(`${files.length} ${files.length === 1 ? "foto cargada" : "fotos cargadas"}. La IA ya está trabajando.`)
            void fetchBatch(response.data.data.id, true)
        } catch (error) {
            setErrorMessage(readableError(error))
        } finally {
            setUploadProgress(null)
            if (selectInput.current) selectInput.current.value = ""
            if (cameraInput.current) cameraInput.current.value = ""
        }
    }

    const payloadFromValues = (values: FormValues) => ({
        name: values.name.trim(),
        description: values.description.trim() || null,
        short_description: values.short_description.trim() || null,
        category_id: values.category_id ? Number(values.category_id) : null,
        category_suggestion: values.category_suggestion.trim() || null,
        tags: splitValues(values.tags),
        colors: splitValues(values.colors),
        material: values.material.trim() || null,
        condition: values.condition || null,
        warnings: splitValues(values.warnings),
        price: numberOrNull(values.price),
        stock_quantity: numberOrNull(values.stock_quantity),
        unit: values.unit.trim() || null,
        dimensions: [values.dimension_length, values.dimension_width, values.dimension_height].some(Boolean) ? {
            length: numberOrNull(values.dimension_length),
            width: numberOrNull(values.dimension_width),
            height: numberOrNull(values.dimension_height),
            unit: values.dimension_unit.trim() || null,
        } : null,
        variations: values.variations.filter((variation) => variation.name.trim()).map((variation) => ({ name: variation.name.trim(), options: splitValues(variation.options) })),
    })

    const save = async (values: FormValues, announce = true): Promise<boolean> => {
        if (!batch || !selectedItem) return false
        setBusyAction("save")
        try {
            await axios.patch(`${workspace.api_url}/${batch.id}/items/${selectedItem.id}`, payloadFromValues(values), requestConfig)
            await fetchBatch(batch.id, true)
            if (announce) toast.success("Cambios guardados")
            return true
        } catch (error) {
            toast.error(readableError(error))
            return false
        } finally {
            setBusyAction(null)
        }
    }

    const approve = form.handleSubmit(async (values) => {
        if (!batch || !selectedItem) return
        if (values.price === "" || values.stock_quantity === "") {
            toast.error("Confirma el precio y las existencias antes de aprobar.")
            return
        }
        if (!await save(values, false)) return
        setBusyAction("approve")
        try {
            await axios.post(`${workspace.api_url}/${batch.id}/items/${selectedItem.id}/approve`, {}, requestConfig)
            const refreshed = await fetchBatch(batch.id, true)
            const next = refreshed?.items.find((item) => item.status === "needs_review")
            if (next) setSelectedId(next.id)
            toast.success("Producto aprobado y listo para publicar")
        } catch (error) {
            toast.error(readableError(error))
        } finally {
            setBusyAction(null)
        }
    })

    const itemAction = async (action: "reject" | "retry") => {
        if (!batch || !selectedItem) return
        if (action === "reject" && !window.confirm("¿Descartar esta imagen? No se creará un producto con ella.")) return
        setBusyAction(action)
        try {
            await axios.post(`${workspace.api_url}/${batch.id}/items/${selectedItem.id}/${action}`, {}, requestConfig)
            const refreshed = await fetchBatch(batch.id, true)
            const next = refreshed?.items.find((item) => item.status === "needs_review")
            if (next) setSelectedId(next.id)
            toast.success(action === "retry" ? "Volvimos a enviar la imagen a análisis" : "Imagen descartada")
        } catch (error) {
            toast.error(readableError(error))
        } finally {
            setBusyAction(null)
        }
    }

    const batchAction = async (action: "publish" | "cancel") => {
        if (!batch) return
        if (action === "cancel" && !window.confirm("¿Cancelar este lote? Se eliminarán los borradores que todavía no publicaste.")) return
        setBusyAction(action)
        try {
            await axios.post(`${workspace.api_url}/${batch.id}/${action}`, {}, requestConfig)
            await fetchBatch(batch.id, true)
            toast.success(action === "publish" ? "Tu catálogo ya está publicado" : "Lote cancelado")
        } catch (error) {
            toast.error(readableError(error))
        } finally {
            setBusyAction(null)
        }
    }

    if (!workspace.available) {
        return <><Head title="Digitaliza tu catálogo" /><WorkspaceHeader workspace={workspace} /><PlanUnavailable workspace={workspace} /></>
    }

    return (
        <div className="min-h-screen bg-slate-50 text-slate-950">
            <Head title="Digitaliza tu catálogo" />
            <WorkspaceHeader workspace={workspace} />
            <main className="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <div className="mb-2 flex items-center gap-2 text-sm font-semibold text-sky-700"><Sparkles className="h-4 w-4" /> Catálogo asistido por IA</div>
                        <h1 className="font-display text-3xl font-bold tracking-tight sm:text-4xl">Digitaliza tu catálogo</h1>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">Sube fotos y convierte cada imagen en un producto listo para revisar. Nada se publica sin tu aprobación.</p>
                    </div>
                    {batch && !["published", "rejected"].includes(batch.status) && (
                        <Button variant="outline" onClick={() => void batchAction("cancel")} disabled={busyAction !== null}><Trash2 className="mr-2 h-4 w-4" /> Cancelar lote</Button>
                    )}
                </div>

                {errorMessage && <div role="alert" className="mb-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /><span>{errorMessage}</span></div>}

                <div className="grid gap-6 lg:grid-cols-5">
                    <aside className="space-y-4 lg:col-span-2">
                        <div
                            className={cn("rounded-xl border-2 border-dashed bg-white p-5 transition", isDragging ? "border-sky-500 bg-sky-50" : "border-slate-300")}
                            onDragEnter={(event) => { event.preventDefault(); setIsDragging(true) }}
                            onDragOver={(event) => event.preventDefault()}
                            onDragLeave={() => setIsDragging(false)}
                            onDrop={(event) => { event.preventDefault(); setIsDragging(false); void uploadFiles(Array.from(event.dataTransfer.files)) }}
                        >
                            <div className="flex items-start gap-4">
                                <span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600"><UploadCloud className="h-5 w-5" /></span>
                                <div><h2 className="font-display font-bold">Añade fotos de productos</h2><p className="mt-1 text-sm leading-5 text-slate-500">JPG, PNG o WebP · hasta {workspace.limits.image_size_mb} MB · máximo {workspace.limits.images_per_batch}</p></div>
                            </div>
                            <div className="mt-5 grid grid-cols-2 gap-3">
                                <Button type="button" onClick={() => selectInput.current?.click()} disabled={uploadProgress !== null}><ImageIcon className="mr-2 h-4 w-4" /> Seleccionar fotos</Button>
                                <Button type="button" variant="outline" onClick={() => cameraInput.current?.click()} disabled={uploadProgress !== null}><Camera className="mr-2 h-4 w-4" /> Tomar foto</Button>
                            </div>
                            <input ref={selectInput} className="sr-only" aria-label="Seleccionar fotos del catálogo" type="file" accept="image/jpeg,image/png,image/webp" multiple onChange={(event) => void uploadFiles(Array.from(event.target.files ?? []))} />
                            <input ref={cameraInput} className="sr-only" aria-label="Tomar foto del producto" type="file" accept="image/*" capture="environment" onChange={(event) => void uploadFiles(Array.from(event.target.files ?? []))} />
                            {uploadProgress !== null && <div className="mt-4"><div className="mb-2 flex justify-between text-xs font-semibold"><span>Subiendo imágenes</span><span>{uploadProgress}%</span></div><Progress value={uploadProgress} /></div>}
                        </div>

                        {batch ? (
                            <div className="overflow-hidden rounded-xl border bg-white">
                                <div className="border-b px-5 py-4">
                                    <div className="flex items-center justify-between"><h2 className="font-display font-bold">Lote actual</h2><span className="text-xs font-semibold text-slate-500">{completedCount}/{batch.total_items}</span></div>
                                    <Progress className="mt-3" value={batchProgress} />
                                    <p className="mt-2 text-xs text-slate-500">{reviewableCount ? `${reviewableCount} por revisar` : batchProgress < 100 ? "Analizando tus fotos…" : "Revisión completada"}</p>
                                </div>
                                <div className="max-h-[34rem] divide-y overflow-y-auto">
                                    {batch.items.map((item, index) => {
                                        const meta = statusMeta[item.status] ?? { label: item.status, className: "bg-slate-100 text-slate-600" }
                                        return (
                                            <button key={item.id} type="button" onClick={() => setSelectedId(item.id)} className={cn("flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50", selectedId === item.id && "bg-sky-50 hover:bg-sky-50")}>
                                                <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><span className="text-xs font-bold">{index + 1}</span></span>
                                                <span className="min-w-0 flex-1"><span className="block truncate text-sm font-semibold">{item.review?.name ?? item.suggestion?.name ?? item.original_name}</span><span className="mt-1 flex items-center gap-1.5 text-xs text-slate-500"><ItemStatusIcon status={item.status} />{meta.label}</span></span>
                                                <ChevronRight className="h-4 w-4 text-slate-300" />
                                            </button>
                                        )
                                    })}
                                </div>
                            </div>
                        ) : (
                            <div className="rounded-xl border bg-white px-6 py-10 text-center"><PackageCheck className="mx-auto h-8 w-8 text-slate-300" /><h2 className="mt-4 font-display font-bold">Tu lote aparecerá aquí</h2><p className="mt-2 text-sm leading-6 text-slate-500">Puedes arrastrar varias fotos o tomarlas desde el teléfono.</p></div>
                        )}
                    </aside>

                    <section className="lg:col-span-3">
                        {!selectedItem ? (
                            <div className="flex min-h-[36rem] flex-col items-center justify-center rounded-xl border bg-white px-8 text-center"><span className="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-50 text-slate-300"><Sparkles className="h-8 w-8" /></span><h2 className="mt-5 font-display text-xl font-bold">Lista para empezar</h2><p className="mt-2 max-w-md text-sm leading-6 text-slate-500">Carga tus fotos. La IA sugerirá nombre, descripción, categoría y atributos para que tú los confirmes.</p></div>
                        ) : (
                            <form onSubmit={approve} className="overflow-hidden rounded-xl border bg-white">
                                <div className="border-b p-5 sm:p-6">
                                    <div className="flex flex-col gap-5 sm:flex-row">
                                        <div className="flex h-48 w-full shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 sm:h-44 sm:w-44">
                                            {sourceUrl ? <img src={sourceUrl} alt={selectedItem.original_name} className="h-full w-full object-contain" /> : <ImageIcon className="h-9 w-9 text-slate-300" />}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2"><span className={cn("rounded-full px-2.5 py-1 text-xs font-bold", (statusMeta[selectedItem.status] ?? statusMeta.uploaded).className)}>{(statusMeta[selectedItem.status] ?? statusMeta.uploaded).label}</span>{selectedItem.overall_confidence != null && <span className="text-xs font-medium text-slate-500">{Math.round(selectedItem.overall_confidence * 100)}% confianza general</span>}</div>
                                            <h2 className="mt-3 truncate font-display text-xl font-bold">{selectedItem.review?.name ?? selectedItem.suggestion?.name ?? selectedItem.original_name}</h2>
                                            <p className="mt-2 text-sm leading-6 text-slate-500">Revisa los campos marcados. El precio y las existencias siempre requieren tu confirmación.</p>
                                            {(selectedItem.suggestion?.review_reasons ?? []).length > 0 && <div className="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">{selectedItem.suggestion?.review_reasons?.join(" · ")}</div>}
                                            {selectedItem.error && <div className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{selectedItem.error.message}</div>}
                                        </div>
                                    </div>
                                </div>

                                {editableStatuses.has(selectedItem.status) ? (
                                    <div className="space-y-7 p-5 sm:p-6">
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            <div className="space-y-2 sm:col-span-2"><div className="flex items-center justify-between"><Label htmlFor="name">Nombre del producto *</Label><FieldHint item={selectedItem} field="name" /></div><Input id="name" {...form.register("name")} aria-invalid={Boolean(form.formState.errors.name)} />{form.formState.errors.name && <p className="text-xs text-red-600">{form.formState.errors.name.message}</p>}</div>
                                            <div className="space-y-2 sm:col-span-2"><div className="flex items-center justify-between"><Label htmlFor="short-description">Descripción corta</Label><FieldHint item={selectedItem} field="short_description" /></div><Input id="short-description" {...form.register("short_description")} /></div>
                                            <div className="space-y-2 sm:col-span-2"><div className="flex items-center justify-between"><Label htmlFor="description">Descripción</Label><FieldHint item={selectedItem} field="description" /></div><Textarea id="description" rows={5} {...form.register("description")} /></div>
                                            <div className="space-y-2"><Label htmlFor="category">Categoría</Label><select id="category" className="h-10 w-full rounded-md border bg-white px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100" {...form.register("category_id")}><option value="">Sin categoría</option>{workspace.categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></div>
                                            <div className="space-y-2"><Label htmlFor="category-suggestion">Categoría sugerida</Label><Input id="category-suggestion" {...form.register("category_suggestion")} /></div>
                                            <div className="space-y-2"><Label htmlFor="price">Precio (L) *</Label><Input id="price" inputMode="decimal" placeholder="0.00" {...form.register("price")} />{form.formState.errors.price && <p className="text-xs text-red-600">{form.formState.errors.price.message}</p>}</div>
                                            <div className="grid grid-cols-2 gap-3"><div className="space-y-2"><Label htmlFor="stock">Existencias *</Label><Input id="stock" inputMode="numeric" placeholder="0" {...form.register("stock_quantity")} /></div><div className="space-y-2"><Label htmlFor="unit">Unidad</Label><Input id="unit" {...form.register("unit")} /></div></div>
                                            <div className="space-y-2"><Label htmlFor="tags">Etiquetas</Label><Input id="tags" placeholder="artesanal, regalo" {...form.register("tags")} /><p className="text-xs text-slate-400">Separa cada opción con una coma.</p></div>
                                            <div className="space-y-2"><Label htmlFor="colors">Colores</Label><Input id="colors" placeholder="azul, blanco" {...form.register("colors")} /></div>
                                            <div className="space-y-2"><Label htmlFor="material">Material</Label><Input id="material" {...form.register("material")} /></div>
                                            <div className="space-y-2"><Label htmlFor="condition">Condición</Label><select id="condition" className="h-10 w-full rounded-md border bg-white px-3 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100" {...form.register("condition")}><option value="">Sin confirmar</option><option value="new">Nuevo</option><option value="used">Usado</option><option value="refurbished">Reacondicionado</option></select></div>
                                        </div>

                                        <div><h3 className="font-display text-sm font-bold">Dimensiones</h3><div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4"><Input aria-label="Largo" placeholder="Largo" inputMode="decimal" {...form.register("dimension_length")} /><Input aria-label="Ancho" placeholder="Ancho" inputMode="decimal" {...form.register("dimension_width")} /><Input aria-label="Alto" placeholder="Alto" inputMode="decimal" {...form.register("dimension_height")} /><Input aria-label="Unidad de medida" placeholder="cm" {...form.register("dimension_unit")} /></div></div>

                                        <div>
                                            <div className="flex items-center justify-between"><div><h3 className="font-display text-sm font-bold">Variaciones</h3><p className="mt-1 text-xs text-slate-500">Ejemplo: Talla · S, M, L</p></div><Button type="button" size="sm" variant="outline" onClick={() => variations.append({ name: "", options: "" })}><Plus className="mr-1 h-3.5 w-3.5" /> Añadir</Button></div>
                                            <div className="mt-3 space-y-3">{variations.fields.map((field, index) => <div key={field.id} className="grid grid-cols-[1fr_1.5fr_auto] gap-2"><Input aria-label={`Nombre de variación ${index + 1}`} placeholder="Talla" {...form.register(`variations.${index}.name`)} /><Input aria-label={`Opciones de variación ${index + 1}`} placeholder="S, M, L" {...form.register(`variations.${index}.options`)} /><Button type="button" size="icon" variant="ghost" aria-label="Eliminar variación" onClick={() => variations.remove(index)}><X className="h-4 w-4" /></Button></div>)}</div>
                                        </div>

                                        <div className="space-y-2"><Label htmlFor="warnings">Advertencias</Label><Textarea id="warnings" rows={2} placeholder="Fragilidad, alérgenos u otras observaciones" {...form.register("warnings")} /></div>
                                    </div>
                                ) : (
                                    <div className="p-8 text-center"><ItemStatusIcon status={selectedItem.status} /><p className="mt-3 text-sm font-semibold text-slate-700">{selectedItem.status === "failed" ? "El análisis no pudo completarse." : selectedItem.status === "published" ? "Este producto ya está publicado." : "Este elemento ya no requiere edición."}</p>{selectedItem.status === "failed" && <Button className="mt-5" type="button" onClick={() => void itemAction("retry")} disabled={busyAction !== null}><RefreshCw className="mr-2 h-4 w-4" /> Reintentar análisis</Button>}</div>
                                )}

                                <div className="sticky bottom-0 flex flex-col-reverse gap-3 border-t bg-white/95 p-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                    <Button type="button" variant="ghost" className="text-red-600 hover:bg-red-50 hover:text-red-700" onClick={() => void itemAction("reject")} disabled={busyAction !== null || !["needs_review", "approved"].includes(selectedItem.status)}>Descartar</Button>
                                    <div className="flex flex-col gap-3 sm:flex-row">
                                        {editableStatuses.has(selectedItem.status) && <Button type="button" variant="outline" onClick={form.handleSubmit((values) => void save(values))} disabled={busyAction !== null}>{busyAction === "save" && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}Guardar borrador</Button>}
                                        {editableStatuses.has(selectedItem.status) && <Button type="submit" disabled={busyAction !== null}>{busyAction === "approve" ? <LoaderCircle className="mr-2 h-4 w-4 animate-spin" /> : <Check className="mr-2 h-4 w-4" />}Aprobar producto</Button>}
                                        {batch?.items.some((item) => item.status === "approved") && !batch.items.some((item) => ["queued", "processing", "uploaded", "needs_review"].includes(item.status)) && <Button type="button" className="bg-sky-600 hover:bg-sky-700" onClick={() => void batchAction("publish")} disabled={busyAction !== null}><UploadCloud className="mr-2 h-4 w-4" /> Publicar catálogo</Button>}
                                    </div>
                                </div>
                            </form>
                        )}
                    </section>
                </div>
            </main>
        </div>
    )
}
