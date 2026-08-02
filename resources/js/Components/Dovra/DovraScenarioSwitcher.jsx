import { Button } from "@/Components/ui/button"
import { cn } from "@/lib/utils"

export function DovraScenarioSwitcher({ scenarios = [], selectedScenarioKey, onSelectScenario }) {
    return (
        <div
            role="group"
            aria-label="Escenarios de activación"
            className="flex flex-wrap gap-3"
            data-testid="dovra-scenario-switcher"
        >
            {scenarios.map((scenario) => {
                const active = scenario.key === selectedScenarioKey

                return (
                    <Button
                        key={scenario.key}
                        type="button"
                        variant={active ? "default" : "outline"}
                        onClick={() => onSelectScenario(scenario.key)}
                        aria-pressed={active}
                        aria-label={`${scenario.label}: ${scenario.title}`}
                        data-testid={`dovra-scenario-${scenario.key}`}
                        className={cn(
                            "min-h-11 whitespace-normal rounded-full px-4 py-3 text-left transition-colors motion-safe:duration-200 motion-reduce:transition-none",
                            active ? "shadow-sm" : "",
                        )}
                    >
                        <span className="flex min-w-0 flex-col">
                            <span className="text-sm font-semibold">{scenario.label}</span>
                            <span className={cn("text-xs font-normal", active ? "text-primary-foreground/80" : "text-muted-foreground")}>
                                {scenario.title}
                            </span>
                        </span>
                    </Button>
                )
            })}
        </div>
    )
}
