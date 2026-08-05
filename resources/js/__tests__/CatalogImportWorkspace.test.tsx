// @vitest-environment jsdom
/// <reference types="@testing-library/jest-dom" />

import { fireEvent, render, screen } from "@testing-library/react"
import axios from "axios"
import { describe, expect, it, vi } from "vitest"

import CatalogImportWorkspace from "@/Pages/CatalogImports/Workspace"

vi.mock("@inertiajs/react", () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,
}))

const workspace = {
    tenant: { id: "tenant-1", name: "Café Central", is_pro: true },
    available: true,
    limits: { products: 500, images_per_batch: 20, image_size_mb: 10 },
    categories: [{ id: 1, name: "Bebidas" }],
    latest_batch_id: null,
    api_url: "/api/v1/catalog-imports",
    products_url: "/app/cafe-central/products",
    upgrade_url: "/app/cafe-central/upgrade-to-pro",
}

describe("CatalogImportWorkspace", () => {
    it("shows the actionable empty state for a Pro business", () => {
        render(<CatalogImportWorkspace workspace={workspace} />)

        expect(screen.getByRole("heading", { name: /digitaliza tu catálogo/i })).toBeInTheDocument()
        expect(screen.getByText(/sube fotos y convierte cada imagen/i)).toBeInTheDocument()
        expect(screen.getByRole("button", { name: /seleccionar fotos/i })).toBeInTheDocument()
        expect(screen.getByRole("button", { name: /tomar foto/i })).toBeInTheDocument()
    })

    it("explains the plan restriction without exposing the importer", () => {
        render(<CatalogImportWorkspace workspace={{ ...workspace, available: false, tenant: { ...workspace.tenant, is_pro: false } }} />)

        expect(screen.getByRole("heading", { name: /digitalización inteligente/i })).toBeInTheDocument()
        expect(screen.getByRole("link", { name: /mejorar mi plan/i })).toHaveAttribute("href", workspace.upgrade_url)
        expect(screen.queryByRole("button", { name: /seleccionar fotos/i })).not.toBeInTheDocument()
    })

    it("validates file count before uploading", () => {
        render(<CatalogImportWorkspace workspace={{ ...workspace, limits: { ...workspace.limits, images_per_batch: 1 } }} />)
        const input = screen.getByLabelText(/seleccionar fotos del catálogo/i)
        const first = new File(["one"], "one.jpg", { type: "image/jpeg" })
        const second = new File(["two"], "two.jpg", { type: "image/jpeg" })

        fireEvent.change(input, { target: { files: [first, second] } })

        expect(screen.getByRole("alert")).toHaveTextContent(/máximo 1 imagen/i)
    })

    it("hydrates a pending review and shows confidence without exposing its private path", async () => {
        Object.defineProperty(URL, "createObjectURL", { configurable: true, value: vi.fn(() => "blob:catalog-source") })
        Object.defineProperty(URL, "revokeObjectURL", { configurable: true, value: vi.fn() })
        const get = vi.spyOn(axios, "get").mockImplementation(async (url) => {
            if (String(url).endsWith("/source")) return { data: new Blob(["image"]) }
            return {
                data: {
                    data: {
                        id: "batch-1",
                        status: "needs_review",
                        total_items: 1,
                        processed_items: 1,
                        failed_items: 0,
                        items: [{
                            id: "item-1",
                            status: "needs_review",
                            original_name: "private-file-name.png",
                            suggestion: {
                                name: "Taza Copán",
                                description: "Cerámica artesanal",
                                price: null,
                                stock_quantity: null,
                                review_reasons: ["Confirma precio"],
                            },
                            field_confidence: { name: 0.94 },
                            overall_confidence: 0.91,
                        }],
                    },
                },
            }
        })

        render(<CatalogImportWorkspace workspace={{ ...workspace, latest_batch_id: "batch-1" }} />)

        expect(await screen.findByDisplayValue("Taza Copán")).toBeInTheDocument()
        expect(screen.getByText("94% confianza")).toBeInTheDocument()
        expect(screen.getByText(/91% confianza general/i)).toBeInTheDocument()
        expect(document.body.textContent).not.toContain("catalog-imports/tenant-1")
        get.mockRestore()
    })
})
