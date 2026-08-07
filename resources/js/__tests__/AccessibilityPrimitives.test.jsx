/**
 * @jest-environment jsdom
 */

import { render, screen } from "@testing-library/react"

import { Dialog, DialogContent, DialogDescription, DialogTitle } from "@/Components/ui/dialog"
import { Progress } from "@/Components/ui/progress"
import { Sheet, SheetContent, SheetDescription, SheetTitle } from "@/Components/ui/sheet"
import { SkipLink } from "@/Components/ui/skip-link"

describe("accessibility primitives", () => {
    it("exposes a named progressbar with bounded values", () => {
        render(<Progress value={150} max={100} aria-label="Avance del registro" />)

        const progress = screen.getByRole("progressbar", { name: "Avance del registro" })
        expect(progress).toHaveAttribute("aria-valuemin", "0")
        expect(progress).toHaveAttribute("aria-valuemax", "100")
        expect(progress).toHaveAttribute("aria-valuenow", "100")
    })

    it("provides a usable default name for generic progress", () => {
        render(<Progress value={25} />)

        const progress = screen.getByRole("progressbar", { name: "Progreso" })
        expect(progress).toBeInTheDocument()
        expect(progress.firstElementChild).toHaveStyle({
            transform: "scaleX(0.25)",
            transformOrigin: "left",
        })
    })

    it("links keyboard users to the canonical main landmark", () => {
        render(
            <>
                <SkipLink />
                <main id="main-content" tabIndex={-1}>Contenido</main>
            </>,
        )

        expect(screen.getByRole("link", { name: "Saltar al contenido principal" }))
            .toHaveAttribute("href", "#main-content")
    })

    it("keeps standalone dialog and sheet close controls at least 40px", () => {
        render(
            <>
                <Dialog open>
                    <DialogContent>
                        <DialogTitle>Dialogo</DialogTitle>
                        <DialogDescription>Contenido del dialogo</DialogDescription>
                    </DialogContent>
                </Dialog>
                <Sheet open>
                    <SheetContent>
                        <SheetTitle>Panel</SheetTitle>
                        <SheetDescription>Contenido del panel</SheetDescription>
                    </SheetContent>
                </Sheet>
            </>,
        )

        const closeButtons = screen.getAllByRole("button", { name: "Cerrar", hidden: true })
        expect(closeButtons).toHaveLength(2)
        closeButtons.forEach((button) => expect(button).toHaveClass("size-10"))
    })
})
