/**
 * @jest-environment jsdom
 */

import { fireEvent, render, screen, waitFor } from "@testing-library/react"
import { vi } from "vitest"

import { ForgotPasswordForm } from "@/Components/Auth/ForgotPasswordForm"
import { LoginForm } from "@/Components/Auth/LoginForm"
import GlobalLoginModal from "@/Components/Organisms/Auth/GlobalLoginModal"
import AuthGate from "@/Components/Utils/AuthGate"
import VerifyEmail from "@/Pages/Auth/VerifyEmail"
import { useUIStore } from "@/Stores/useUIStore"
import { t } from "@/lib/i18n"

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
    Head: ({ title }) => <title>{title}</title>,
    Link: ({ children, ...props }) => <button {...props}>{children}</button>,
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

        expect(screen.getByText("Entrar a Vito Business OS")).toBeInTheDocument()
        expect(screen.getByRole("tab", { name: /acceso con correo/i })).toBeInTheDocument()
        expect(screen.getByRole("tab", { name: /acceso rápido/i })).toBeInTheDocument()
    })

    it("renders password fields when password access is active", () => {
        useUIStore.setState({ isLoginModalOpen: true, loginView: "password" })

        render(<GlobalLoginModal />)

        expect(screen.getByLabelText("Correo")).toBeInTheDocument()
        expect(screen.getByLabelText(/contraseña/i)).toBeInTheDocument()
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

describe("VerifyEmail Page", () => {
    beforeEach(() => {
        global.route = vi.fn((name) => `/${name}`)
        inertiaMock.pageProps = {
            props: {
                auth: { user: { email: "dueno@negocio.test" } },
            },
        }
        inertiaMock.post.mockClear()
    })

    it("renders catalog-backed verification copy and onboarding next action", () => {
        render(
            <VerifyEmail
                onboardingNextAction={{
                    title: "Publica tu primera oferta",
                    copy: "Agrega un producto o servicio.",
                    progress: 20,
                }}
            />,
        )

        expect(screen.getByText("Confirma tu correo")).toBeInTheDocument()
        expect(screen.getByText(/Enviamos un enlace de verificacion a/i)).toBeInTheDocument()
        expect(screen.getByText(/Siguiente paso: Publica tu primera oferta/i)).toBeInTheDocument()
    })
})

describe("Auth catalog-backed forms", () => {
    beforeEach(() => {
        global.route = vi.fn((name) => `/${name}`)
        inertiaMock.post.mockClear()
    })

    it("renders login form copy from the initial auth catalog", () => {
        render(<LoginForm />)

        expect(screen.getByText("Acceso seguro")).toBeInTheDocument()
        expect(screen.getByText("Entra a Vito Business OS")).toBeInTheDocument()
        expect(screen.getByText(/Usa contrasena para el panel de negocio/i)).toBeInTheDocument()
        expect(screen.getByLabelText("Contrasena")).toBeInTheDocument()
        expect(screen.getByRole("button", { name: /Iniciar sesion/i })).toBeInTheDocument()
    })

    it("renders forgot password form copy from the initial auth catalog", () => {
        render(<ForgotPasswordForm />)

        expect(screen.getByTestId("forgot-password-heading")).toHaveTextContent("Recupera tu acceso")
        expect(screen.getByText(/enlace seguro para cambiar la contrasena/i)).toBeInTheDocument()
        expect(screen.getByLabelText("Correo")).toBeInTheDocument()
        expect(screen.getByRole("button", { name: /Enviar enlace/i })).toBeInTheDocument()
        expect(screen.getByRole("button", { name: /Iniciar sesion/i })).toBeInTheDocument()
    })
})

describe("Initial dashboard catalog", () => {
    it("exposes dashboard and account copy without inline-only strings", () => {
        expect(t("dashboard.fallback.title")).toBe("Tu panel principal depende de tu rol")
        expect(t("dashboard.account.greeting", { name: "Ana" })).toBe("Hola, Ana")
        expect(t("dashboard.account.favoritesCount", { count: 2 })).toBe("2 guardados")
        expect(t("dashboard.account.visitsSummary", { visits: 7 })).toContain("Visitas registradas: 7")
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
