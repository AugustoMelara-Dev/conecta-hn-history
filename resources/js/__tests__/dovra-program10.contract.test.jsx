/**
 * @jest-environment jsdom
 */

import { fireEvent, render, screen } from "@testing-library/react"

import fixtures from "../../stories/dovra-program-10.fixtures.json" assert { type: "json" }
import { DovraProgram10Experience } from "@/Components/Dovra/DovraProgram10Experience"

describe("Dovra Program 10 UX contract", () => {
    it("renders the brand truth, skip link, named progressbar, and a polite live region", () => {
        render(<DovraProgram10Experience />)

        expect(screen.getByRole("link", { name: "Saltar al contenido principal" }))
            .toHaveAttribute("href", "#main-content")
        expect(screen.getByRole("main")).toHaveAttribute("id", "main-content")
        expect(screen.getByRole("heading", { name: fixtures.brand.name })).toBeInTheDocument()
        expect(screen.getByText(fixtures.brand.promise)).toBeInTheDocument()

        const progress = screen.getAllByRole("progressbar", {
            name: `Avance de ${fixtures.scenarios[0].title}`,
        })[0]
        expect(progress).toHaveAttribute("aria-valuenow", "72")

        const liveRegion = screen.getByTestId("dovra-live-status")
        expect(liveRegion).toHaveAttribute("aria-live", "polite")
        expect(liveRegion).toHaveTextContent("Guatemala")
        expect(liveRegion).toHaveTextContent("reversible")
    })

    it("keeps the scenario switcher, market switcher, and buyer CTA accessible and stateful", () => {
        render(<DovraProgram10Experience />)

        const scenarioSwitcher = screen.getByTestId("dovra-scenario-switcher")
        expect(scenarioSwitcher).toHaveAttribute("role", "group")

        const hybridScenario = screen.getByRole("button", {
            name: "Mixto: Una sola operación para productos y servicios",
        })
        expect(hybridScenario).toHaveClass("motion-reduce:transition-none")
        fireEvent.click(hybridScenario)
        expect(hybridScenario).toHaveAttribute("aria-pressed", "true")

        const liveRegion = screen.getByTestId("dovra-live-status")
        expect(liveRegion).toHaveTextContent(/operación mixta/i)
        expect(liveRegion).toHaveTextContent("Honduras")

        const panamaMarket = screen.getByRole("button", {
            name: /Panamá: Mercado alterno para comprobar un lanzamiento regional\./i,
        })
        fireEvent.click(panamaMarket)
        expect(panamaMarket).toHaveAttribute("aria-pressed", "true")
        expect(liveRegion).toHaveTextContent("Panamá")
        expect(liveRegion).toHaveTextContent("revisarse sin pérdida de estado")

        expect(screen.getByRole("link", { name: "Abrir oferta principal" }))
            .toHaveAttribute("href", "#mix")
        expect(screen.getByRole("button", { name: "Guardar combinaciones" }))
            .toBeEnabled()
    })
})
