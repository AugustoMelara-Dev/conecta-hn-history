import { formatCurrency, formatCurrencyPrecise } from "@/Utils/currency"

describe("global currency formatting", () => {
    it("formats from explicit locale and currency", () => {
        const formatted = formatCurrency(150.5, { locale: "en-CA", currency: "CAD" })
        const precise = formatCurrencyPrecise(150.5, { locale: "en-CA", currency: "CAD" })

        expect(formatted).toContain("$151")
        expect(precise).toContain("$150.50")
    })

    it("fails closed instead of assuming Honduras", () => {
        expect(() => formatCurrency(150.5)).toThrow(/explicit locale and currency/i)
    })
})
