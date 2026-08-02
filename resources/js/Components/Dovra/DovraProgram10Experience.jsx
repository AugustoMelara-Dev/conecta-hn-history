import { useEffect, useMemo, useState } from "react"
import { MotionConfig } from "framer-motion"
import { ArrowRight, ShieldCheck, Sparkles } from "lucide-react"

import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card"
import { Progress } from "@/Components/ui/progress"
import { SkipLink } from "@/Components/ui/skip-link"
import { PUBLIC_BRAND } from "@/lib/publicBrand"
import { cn } from "@/lib/utils"

import fixtures from "../../../stories/dovra-program-10.fixtures.json" assert { type: "json" }
import { DovraBuyerReadinessPanel } from "./DovraBuyerReadinessPanel"
import { DovraLaunchReadinessPanel } from "./DovraLaunchReadinessPanel"
import { DovraScenarioSwitcher } from "./DovraScenarioSwitcher"

function DovraHero({ brand }) {
    return (
        <Card className="border-border bg-card shadow-sm">
            <CardHeader className="gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary" className="rounded-full">
                        {brand.category}
                    </Badge>
                    <Badge variant="outline" className="rounded-full">
                        Manual AT review required
                    </Badge>
                </div>
                <CardTitle className="text-balance text-3xl font-semibold tracking-tight sm:text-4xl">
                    {brand.name}
                </CardTitle>
                <CardDescription className="max-w-3xl text-pretty text-base leading-7">
                    {brand.promise}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-3">
                {[
                    { label: "Onboarding", copy: "Autoritativo, reversible y visible." },
                    { label: "Comprador", copy: "Preparación explícita antes de actuar." },
                    { label: "Accesibilidad", copy: "Teclado, foco, live regions y reflow." },
                ].map((item) => (
                    <div key={item.label} className="rounded-lg border border-border bg-muted px-4 py-4">
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                            {item.label}
                        </p>
                        <p className="mt-2 text-sm leading-7 text-foreground">{item.copy}</p>
                    </div>
                ))}
            </CardContent>
        </Card>
    )
}

export function DovraProgram10Experience({
    scenarios = fixtures.scenarios,
    brand = fixtures.brand || PUBLIC_BRAND,
    initialScenarioKey = scenarios[0]?.key || "products",
    focusArea = "full",
}) {
    const [selectedScenarioKey, setSelectedScenarioKey] = useState(initialScenarioKey)
    const selectedScenario = useMemo(
        () => scenarios.find((scenario) => scenario.key === selectedScenarioKey) || scenarios[0] || null,
        [scenarios, selectedScenarioKey],
    )

    const [selectedMarketCode, setSelectedMarketCode] = useState(
        selectedScenario?.defaultMarketCode || selectedScenario?.marketOptions?.[0]?.code || null,
    )

    useEffect(() => {
        const fallbackMarketCode = selectedScenario?.defaultMarketCode || selectedScenario?.marketOptions?.[0]?.code || null
        if (!selectedScenario?.marketOptions?.some((market) => market.code === selectedMarketCode)) {
            setSelectedMarketCode(fallbackMarketCode)
        }
    }, [selectedMarketCode, selectedScenario])

    const selectedMarket = useMemo(
        () => selectedScenario?.marketOptions?.find((market) => market.code === selectedMarketCode) || selectedScenario?.marketOptions?.[0] || null,
        [selectedMarketCode, selectedScenario],
    )

    const liveMessage = useMemo(() => {
        if (!selectedScenario || !selectedMarket) {
            return "La preparación aún no tiene un estado seleccionable."
        }

        return `${selectedScenario.buyer.liveMessage} Mercado actual: ${selectedMarket.label}. ${selectedScenario.onboarding.nextAction.title}.`
    }, [selectedMarket, selectedScenario])

    if (!selectedScenario) {
        return (
            <div className="min-h-screen bg-background text-foreground">
                <SkipLink />
                <main id="main-content" tabIndex={-1} className="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-4 py-10 outline-none">
                    <Alert className="max-w-xl">
                        <AlertTitle>Sin escenarios disponibles</AlertTitle>
                        <AlertDescription>
                            No se cargaron escenarios para el slice de Dovra Program 10.
                        </AlertDescription>
                    </Alert>
                </main>
            </div>
        )
    }

    return (
        <MotionConfig reducedMotion="user">
            <div className="min-h-screen bg-background text-foreground">
                <SkipLink />

                <main id="main-content" tabIndex={-1} className="mx-auto flex min-h-screen max-w-6xl flex-col gap-6 px-4 py-6 outline-none sm:px-6 lg:px-8">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                            <span className="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-2">
                                <Sparkles aria-hidden="true" className="size-4" />
                                Programa 10
                            </span>
                            <span className="inline-flex items-center gap-2 rounded-full border border-border bg-muted px-3 py-2">
                                <ShieldCheck aria-hidden="true" className="size-4" />
                                UX / accesibilidad
                            </span>
                        </div>

                        <DovraHero brand={brand} />
                    </div>

                    <Alert variant="default" className="border-border bg-muted/60">
                        <AlertTitle>Contrato de vista previa</AlertTitle>
                        <AlertDescription className="leading-7">
                            El slice usa tokens semánticos, foco visible, live regions y preparación reversible. Los clics del cliente sólo cambian la vista previa; el estado real sigue viniendo del backend autoritativo.
                        </AlertDescription>
                    </Alert>

                    <section className={cn("grid gap-6", focusArea === "buyer" ? "lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]" : "lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]")}>
                        <div className="min-w-0 space-y-6">
                            <div className="space-y-3">
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-semibold text-foreground">Escenarios de activación</p>
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            {selectedScenario.summary}
                                        </p>
                                    </div>
                                    <Badge variant="secondary" className="rounded-full">
                                        {selectedScenario.buyer.status}
                                    </Badge>
                                </div>
                                <DovraScenarioSwitcher
                                    scenarios={scenarios}
                                    selectedScenarioKey={selectedScenario.key}
                                    onSelectScenario={setSelectedScenarioKey}
                                />
                            </div>

                            <DovraLaunchReadinessPanel
                                scenario={selectedScenario}
                                selectedMarket={selectedMarket}
                                onSelectMarket={setSelectedMarketCode}
                            />
                        </div>

                        <div className="min-w-0 space-y-6">
                            <DovraBuyerReadinessPanel
                                scenario={selectedScenario}
                                selectedMarket={selectedMarket}
                                liveMessage={liveMessage}
                            />

                            <Card className="border-border bg-card shadow-sm">
                                <CardHeader className="gap-3">
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline" className="rounded-full">
                                            Estado en vivo
                                        </Badge>
                                        <Badge variant={selectedMarket?.isHonduras ? "secondary" : "outline"} className="rounded-full">
                                            {selectedMarket?.label || "Mercado pendiente"}
                                        </Badge>
                                    </div>
                                    <CardTitle className="text-xl font-semibold tracking-tight">
                                        Lista de lectura para teclado y zoom
                                    </CardTitle>
                                    <CardDescription className="text-pretty leading-7">
                                        El contenido no depende de hover y se refluye en una sola columna en pantallas estrechas.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between gap-4">
                                            <span className="text-sm font-semibold text-foreground">Progreso actual</span>
                                            <span className="text-sm font-semibold text-muted-foreground">
                                                {selectedScenario.onboarding.progress}%
                                            </span>
                                        </div>
                                        <Progress
                                            value={selectedScenario.onboarding.progress}
                                            aria-label={`Avance de ${selectedScenario.title}`}
                                            className="h-3 bg-secondary"
                                        />
                                    </div>

                                    <div className="rounded-lg border border-border bg-muted px-4 py-4">
                                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                            Live region
                                        </p>
                                        <div
                                            role="status"
                                            aria-live="polite"
                                            aria-atomic="true"
                                            data-testid="dovra-program10-live-message"
                                            className="mt-2 text-sm leading-7 text-foreground"
                                        >
                                            {liveMessage}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <Button asChild className="min-h-11 whitespace-normal">
                                            <a href={selectedScenario.onboarding.nextAction.href}>
                                                {selectedScenario.onboarding.nextAction.title}
                                            </a>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11 whitespace-normal"
                                        >
                                            Ver el siguiente paso
                                            <ArrowRight aria-hidden="true" className="size-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </section>
                </main>
            </div>
        </MotionConfig>
    )
}
