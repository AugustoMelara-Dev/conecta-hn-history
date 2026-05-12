import { useForm } from "@inertiajs/react"
import { Building2, Mail } from "lucide-react"

import { QuickAccessForm } from "@/Components/Auth/QuickAccessForm"
import { Button } from "@/Components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/Components/ui/dialog"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { Spinner } from "@/Components/ui/spinner"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs"
import { useUIStore } from "@/Stores/useUIStore"

export default function GlobalLoginModal() {
    const { isLoginModalOpen, closeLoginModal, loginView, setLoginView } = useUIStore()

    const passwordForm = useForm({
        email: "",
        password: "",
        remember: false,
    })
    const emailErrorId = passwordForm.errors.email ? "modal-email-error" : undefined
    const passwordErrorId = passwordForm.errors.password ? "modal-password-error" : undefined

    const handlePasswordLogin = (event) => {
        event.preventDefault()
        passwordForm.post(route("login"), {
            onSuccess: () => {
                closeLoginModal()
                passwordForm.reset()
            },
            onFinish: () => passwordForm.reset("password"),
        })
    }

    const handleClose = (open) => {
        if (!open) {
            closeLoginModal()
            passwordForm.reset()
        }
    }

    return (
        <Dialog open={isLoginModalOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle className="text-2xl font-semibold">Entrar a Vito Business OS</DialogTitle>
                    <DialogDescription>
                        Elige acceso con contraseña o enlace/código de respaldo.
                    </DialogDescription>
                </DialogHeader>

                <Tabs value={loginView} onValueChange={setLoginView} className="w-full">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="password" className="gap-2">
                            <Building2 className="h-4 w-4" aria-hidden="true" />
                            Acceso con correo
                        </TabsTrigger>
                        <TabsTrigger value="otp" className="gap-2">
                            <Mail className="h-4 w-4" aria-hidden="true" />
                            Acceso rápido
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="password" className="mt-5">
                        <form onSubmit={handlePasswordLogin}>
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="modal-email">Correo</FieldLabel>
                                    <Input
                                        id="modal-email"
                                        data-testid="modal-login-email-input"
                                        type="email"
                                        value={passwordForm.data.email}
                                        onChange={(event) => passwordForm.setData("email", event.target.value)}
                                        required
                                        autoComplete="email"
                                        aria-invalid={Boolean(passwordForm.errors.email)}
                                        aria-describedby={emailErrorId}
                                    />
                                    {passwordForm.errors.email ? (
                                        <FieldDescription id="modal-email-error" role="alert" className="text-rose-600">
                                            {passwordForm.errors.email}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="modal-password">Contraseña</FieldLabel>
                                    <Input
                                        id="modal-password"
                                        data-testid="modal-login-password-input"
                                        type="password"
                                        value={passwordForm.data.password}
                                        onChange={(event) => passwordForm.setData("password", event.target.value)}
                                        required
                                        autoComplete="current-password"
                                        aria-invalid={Boolean(passwordForm.errors.password)}
                                        aria-describedby={passwordErrorId}
                                    />
                                    {passwordForm.errors.password ? (
                                        <FieldDescription id="modal-password-error" role="alert" className="text-rose-600">
                                            {passwordForm.errors.password}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Button type="submit" data-testid="modal-login-submit" className="w-full" disabled={passwordForm.processing}>
                                    {passwordForm.processing ? <Spinner className="mr-2" /> : null}
                                    Iniciar sesión
                                </Button>
                            </FieldGroup>
                        </form>
                    </TabsContent>

                    <TabsContent value="otp" className="mt-5">
                        <QuickAccessForm onAuthenticated={closeLoginModal} />
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    )
}
