/** @jest-environment jsdom */

import { render, screen } from "@testing-library/react"
import { vi } from "vitest"

import { LocationStep } from "@/Components/Auth/RegisterTenantWizardSteps"

const canadaMarket = {
    countryCode: "CA",
    countryName: "Canada",
    callingCode: "+1",
    defaults: {
        localeCode: "en-CA",
        currencyCode: "CAD",
        timezone: "America/Toronto",
    },
    locales: [{ code: "en-CA", name: "English (Canada)", nativeName: "English (Canada)" }],
    currencies: [{ code: "CAD", name: "Canadian dollar", symbol: "$" }],
    timezones: [{ name: "America/Toronto", label: "Eastern Time — Toronto" }],
    localities: [{ id: 51, name: "Toronto", administrativeArea: "Ontario", timezone: "America/Toronto" }],
}

const baseData = {
    city_id: "",
    country_code: "CA",
    locality_id: "51",
    locale_code: "en-CA",
    currency_code: "CAD",
    timezone: "America/Toronto",
    address: "",
    whatsapp: "",
}

const commonProps = {
    updateField: vi.fn(),
    errorFor: () => null,
    inputClass: () => "",
}

describe("Register tenant market step", () => {
    it("renders the explicit global market contract when published markets exist", () => {
        render(
            <LocationStep
                {...commonProps}
                data={baseData}
                markets={[canadaMarket]}
                cities={[]}
                selectedCityValue="none"
            />,
        )

        expect(screen.getByTestId("register-country-select")).toBeInTheDocument()
        expect(screen.getByTestId("register-locality-select")).toBeInTheDocument()
        expect(screen.getByTestId("register-locale-select")).toBeInTheDocument()
        expect(screen.getByTestId("register-currency-select")).toBeInTheDocument()
        expect(screen.getByTestId("register-timezone-select")).toBeInTheDocument()
        expect(screen.getByLabelText("Dirección")).toBeInTheDocument()
        expect(screen.getByPlaceholderText("+1 teléfono")).toBeInTheDocument()
        expect(screen.queryByTestId("register-city-select")).not.toBeInTheDocument()
    })

    it("keeps the legacy city selector during the Honduras shadow-read transition", () => {
        render(
            <LocationStep
                {...commonProps}
                data={{ ...baseData, country_code: "", locality_id: "" }}
                markets={[]}
                cities={[{ id: 1, name: "Tegucigalpa" }]}
                selectedCityValue="1"
            />,
        )

        expect(screen.getByTestId("register-city-select")).toBeInTheDocument()
        expect(screen.queryByTestId("register-country-select")).not.toBeInTheDocument()
    })
})
