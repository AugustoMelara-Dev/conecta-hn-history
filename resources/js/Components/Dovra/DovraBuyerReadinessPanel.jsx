import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardFooter, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card"
import { Badge } from "@/Components/ui/badge"
import { cn } from "@/lib/utils"

export function DovraBuyerReadinessPanel({ scenario, selectedMarket, liveMessage }) {
    return (
        <Card className="min-w-0 border-border bg-card shadow-sm">
            <CardHeader className="gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary" className="rounded-full">
                        Preparación del comprador
                    </Badge>
                    <Badge variant={selectedMarket?.isHonduras ? "outline" : "secondary"} className="rounded-full">
                        {selectedMarket?.label || "Mercado sin seleccionar"}
                    </Badge>
                </div>

                <CardTitle className="text-balance text-2xl font-semibold tracking-tight">
                    {scenario.buyer.title}
                </CardTitle>
                <CardDescription className="text-pretty leading-7">
                    {scenario.buyer.copy}
                </CardDescription>
            </CardHeader>

            <CardContent className="flex flex-col gap-4">
                <div
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                    data-testid="dovra-live-status"
                    className="rounded-lg border border-border bg-muted px-4 py-4 text-sm leading-7 text-foreground"
                >
                    {liveMessage}
                </div>

                <div className="rounded-lg border border-border bg-background px-4 py-4">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                        Preparación reversible
                    </p>
                    <p className="mt-2 text-sm leading-7 text-muted-foreground">
                        {scenario.buyer.reversibleCopy}
                    </p>
                    <p className="mt-3 text-sm leading-7 text-muted-foreground">
                        {scenario.buyer.secondaryHint}
                    </p>
                </div>

                <ul className="grid gap-2 text-sm leading-7 text-muted-foreground">
                    <li className="rounded-md bg-muted px-3 py-2">
                        <span className="font-semibold text-foreground">Estado:</span> {scenario.buyer.status}
                    </li>
                    <li className="rounded-md bg-muted px-3 py-2">
                        <span className="font-semibold text-foreground">Mercado:</span> {selectedMarket?.note || "Pendiente"}
                    </li>
                </ul>

                <div className="rounded-lg border border-border bg-muted/50 px-4 py-4">
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                        Chat y estado en vivo
                    </p>
                    <ul className="mt-3 space-y-3" aria-label="Actualizaciones del chat">
                        {scenario.chat.map((item) => (
                            <li key={`${item.speaker}-${item.message}`} className="rounded-md bg-background px-3 py-2">
                                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">
                                    {item.speaker}
                                </p>
                                <p className="mt-1 text-sm leading-6 text-foreground">
                                    {item.message}
                                </p>
                            </li>
                        ))}
                    </ul>
                </div>
            </CardContent>

            <CardFooter className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Button asChild className="min-h-11 w-full whitespace-normal sm:w-auto">
                    <a href={scenario.buyer.ctaHref}>
                        {scenario.buyer.ctaLabel}
                    </a>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    className={cn("min-h-11 w-full whitespace-normal sm:w-auto")}
                    aria-label={scenario.buyer.secondaryLabel}
                >
                    {scenario.buyer.secondaryLabel}
                </Button>
            </CardFooter>
        </Card>
    )
}
