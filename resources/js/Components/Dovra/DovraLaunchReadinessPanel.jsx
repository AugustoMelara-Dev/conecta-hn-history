import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import { Progress } from "@/Components/ui/progress"
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { cn } from "@/lib/utils"

const statusTone = {
    ready: "secondary",
    pending: "outline",
    blocked_by_plan: "destructive",
}

function MarketButton({ market, active, onSelect }) {
    return (
        <Button
            type="button"
            variant={active ? "default" : "outline"}
            aria-pressed={active}
            aria-label={`${market.label}: ${market.note}`}
            onClick={() => onSelect(market.code)}
            data-testid={`dovra-market-${market.code.toLowerCase()}`}
            className={cn(
                "min-h-11 whitespace-normal rounded-full px-4 py-3 text-left transition-colors motion-safe:duration-200 motion-reduce:transition-none",
                active ? "shadow-sm" : "",
            )}
            title={market.note}
        >
            <span className="flex min-w-0 flex-col">
                <span className="text-sm font-semibold">{market.label}</span>
                <span className={cn("text-xs font-normal", active ? "text-primary-foreground/80" : "text-muted-foreground")}>
                    {market.note}
                </span>
            </span>
        </Button>
    )
}

export function DovraLaunchReadinessPanel({
    scenario,
    selectedMarket,
    onSelectMarket,
}) {
    return (
        <Card className="min-w-0 border-border bg-card shadow-sm">
            <CardHeader className="gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary" className="rounded-full">
                        Modo {scenario.commercialMode}
                    </Badge>
                    <Badge
                        variant={selectedMarket?.isHonduras ? "secondary" : "outline"}
                        className="rounded-full"
                    >
                        {selectedMarket?.isHonduras ? "Mercado de Honduras" : "Mercado fuera de Honduras"}
                    </Badge>
                </div>

                <CardTitle className="text-balance text-2xl font-semibold tracking-tight">
                    {scenario.title}
                </CardTitle>
                <CardDescription className="text-pretty leading-7">
                    {scenario.summary}
                </CardDescription>
            </CardHeader>

            <CardContent className="flex flex-col gap-5">
                <div className="space-y-3">
                    <div className="flex items-center justify-between gap-4">
                        <span className="text-sm font-semibold text-foreground">Avance de onboarding</span>
                        <span className="text-sm font-semibold text-muted-foreground">
                            {scenario.onboarding.progress}%
                        </span>
                    </div>
                    <Progress
                        value={scenario.onboarding.progress}
                        aria-label={`Avance de ${scenario.title}`}
                        className="h-3 bg-secondary"
                    />
                </div>

                <Alert variant="default" className="border-border bg-muted/60">
                    <AlertTitle className="text-sm">
                        Fuente esperada del onboarding
                    </AlertTitle>
                    <AlertDescription className="leading-7 text-muted-foreground">
                        {scenario.onboarding.authoritativeNote} Esta preview no confirma ni escribe ese estado.
                    </AlertDescription>
                </Alert>

                <div className="space-y-3">
                    <div className="flex items-center justify-between gap-4">
                        <h3 className="text-sm font-semibold text-foreground">Hitos visibles</h3>
                        <span className="text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground">
                            {scenario.onboarding.nextAction.title}
                        </span>
                    </div>

                    <ul className="grid gap-3">
                        {scenario.onboarding.milestones.map((milestone) => {
                            const tone = statusTone[milestone.status] ?? "outline"
                            return (
                                <li
                                    key={milestone.key}
                                    className="rounded-lg border border-border bg-background p-4"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0 space-y-1">
                                            <p className="font-semibold text-foreground">{milestone.title}</p>
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                {milestone.copy}
                                            </p>
                                        </div>
                                        <Badge variant={tone} className="shrink-0 rounded-full">
                                            {milestone.completed ? "Listo" : milestone.status === "blocked_by_plan" ? "Bloqueado" : "Pendiente"}
                                        </Badge>
                                    </div>
                                </li>
                            )
                        })}
                    </ul>
                </div>

                <div className="space-y-3">
                    <h3 className="text-sm font-semibold text-foreground">Mercado seleccionado</h3>
                    <div
                        role="group"
                        aria-label="Mercados disponibles"
                        className="flex flex-wrap gap-3"
                    >
                        {scenario.marketOptions.map((market) => (
                            <MarketButton
                                key={market.code}
                                market={market}
                                active={market.code === selectedMarket?.code}
                                onSelect={onSelectMarket}
                            />
                        ))}
                    </div>
                </div>
            </CardContent>
        </Card>
    )
}
