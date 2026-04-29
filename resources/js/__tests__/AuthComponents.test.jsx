/**
 * @jest-environment jsdom
 */

import { fireEvent, render, screen, waitFor } from "@testing-library/react"
import { vi } from "vitest"

import GlobalLoginModal from "@/Components/Organisms/Auth/GlobalLoginModal"
import AuthGate from "@/Components/Utils/AuthGate"
import { useUIStore } from "@/Stores/useUIStore"

const inertiaMock = vi.hoisted(() => ({
    pageProps: {
        props: {
            auth: { user: null },
        },
    },
    setData: vi.fn(),
    post: vi.fn(),
    reset: vi.fn(),
}))

vi.mock("@inertiajs/react", () => ({
    usePage: () => inertiaMock.pageProps,
    useForm: () => ({
        data: { email: "", password: "", remember: false },
        setData: inertiaMock.setData,
        post: inertiaMock.post,
        processing: false,
        errors: {},
        reset: inertiaMock.reset,
    }),
}))

vi.mock("axios")

describe("AuthGate Component", () => {
    beforeEach(() => {
        useUIStore.setState({ isLoginModalOpen: false })
        inertiaMock.pageProps = {
            props: {
                auth: { user: null },
            },
        }
    })

    it("opens modal when unauthenticated user clicks protected action", () => {
        const mockAction = vi.fn()

        render(
            <AuthGate onAuth={mockAction}>
                <button>Click Me</button>
            </AuthGate>,
        )

        fireEvent.click(screen.getByText("Click Me"))

        expect(useUIStore.getState().isLoginModalOpen).toBe(true)
        expect(mockAction).not.toHaveBeenCalled()
    })

    it("executes action when authenticated user clicks", () => {
        inertiaMock.pageProps = {
            props: {
                auth: { user: { id: 1, name: "Test User" } },
            },
        }
        const mockAction = vi.fn()

        render(
            <AuthGate onAuth={mockAction}>
                <button>Click Me</button>
            </AuthGate>,
        )

        fireEvent.click(screen.getByText("Click Me"))

        expect(mockAction).toHaveBeenCalled()
        expect(useUIStore.getState().isLoginModalOpen).toBe(false)
    })
})

describe("GlobalLoginModal Component", () => {
    beforeEach(() => {
        useUIStore.setState({ isLoginModalOpen: true, loginView: "otp" })
        inertiaMock.setData.mockClear()
        inertiaMock.post.mockClear()
        inertiaMock.reset.mockClear()
    })

    it("renders with quick access active by default", () => {
        render(<GlobalLoginModal />)

        expect(screen.getByText("Acceso rápido")).toBeInTheDocument()
        expect(screen.getByPlaceholderText(/nombre@negocio.hn/i)).toBeInTheDocument()
    })

    it("renders password fields when password access is active", () => {
        useUIStore.setState({ isLoginModalOpen: true, loginView: "password" })

        render(<GlobalLoginModal />)

        expect(screen.getByLabelText("Correo")).toBeInTheDocument()
        expect(screen.getByLabelText("Contraseña")).toBeInTheDocument()
    })

    it("captures identifier input for quick access", async () => {
        render(<GlobalLoginModal />)

        const input = screen.getByPlaceholderText(/nombre@negocio.hn/i)

        fireEvent.change(input, { target: { value: "test@example.com" } })

        await waitFor(() => {
            expect(input.value).toBe("test@example.com")
        })
    })

    it("closes modal when close action is triggered", () => {
        render(<GlobalLoginModal />)

        useUIStore.getState().closeLoginModal()

        expect(useUIStore.getState().isLoginModalOpen).toBe(false)
    })
})

describe("Zustand Store - useUIStore", () => {
    beforeEach(() => {
        useUIStore.setState({
            isLoginModalOpen: false,
            loginView: "otp",
        })
    })

    it("opens login modal", () => {
        useUIStore.getState().openLoginModal()
        expect(useUIStore.getState().isLoginModalOpen).toBe(true)
    })

    it("closes login modal", () => {
        useUIStore.setState({ isLoginModalOpen: true })
        useUIStore.getState().closeLoginModal()
        expect(useUIStore.getState().isLoginModalOpen).toBe(false)
    })

    it("switches login view", () => {
        useUIStore.getState().setLoginView("password")
        expect(useUIStore.getState().loginView).toBe("password")
    })
})
