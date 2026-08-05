import { useForm, usePage } from "@inertiajs/react"
import { useMemo, useState } from "react"
import { Building2, Mail } from "lucide-react"

import { QuickAccessForm } from "@/Components/Auth/QuickAccessForm"
import { Button } from "@/Components/ui/button"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/Components/ui/dialog"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { Spinner } from "@/Components/ui/spinner"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs"
import { PUBLIC_BRAND } from '@/lib/publicBrand';
import { useUIStore } from "@/Stores/useUIStore"

export default function GlobalLoginModal() {
    const { isLoginModalOpen, closeLoginModal, loginView, setLoginView } = useUIStore()
    const { props: pageProps } = usePage()
    const pageErrors = pageProps.errors ?? {}
    const [isPasswordSubmitting, setIsPasswordSubmitting] = useState(false)
    const csrfToken = useMemo(
        () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        []
    )

    const passwordForm = useForm({
        email: "",
        password: "",
        remember: false,
    })

    const passwordErrors = { ...pageErrors, ...passwordForm.errors }
    const emailErrorId = passwordErrors.email ? "modal-email-error" : undefined
    const passwordErrorId = passwordErrors.password ? "modal-password-error" : undefined

    const handleClose = (open) => {
        if (!open) {
            closeLoginModal()
            passwordForm.reset()
            setIsPasswordSubmitting(false)
        }
    }

    return (
        <Dialog open={isLoginModalOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle className="text-2xl font-semibold">
                        Entrar a {PUBLIC_BRAND.name}
                    </DialogTitle>
                    <DialogDescription>
                        Elige acceso con contraseña o enlace/código de respaldo.
                    </DialogDescription>
                </DialogHeader>

                <Tabs value={loginView} onValueChange={setLoginView} className="w-full">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="password" className="gap-2">
                            <Building2 data-icon="inline-start" aria-hidden="true" />
                            Acceso con correo
                        </TabsTrigger>
                        <TabsTrigger value="otp" className="gap-2">
                            <Mail data-icon="inline-start" aria-hidden="true" />
                            Acceso rápido
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="password" className="mt-5">
                        <form method="post" action="/login" onSubmit={() => setIsPasswordSubmitting(true)}>
                            <input type="hidden" name="_token" value={csrfToken} />
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="modal-email">Correo</FieldLabel>
                                    <Input
                                        id="modal-email"
                                        data-testid="modal-login-email-input"
                                        name="email"
                                        type="email"
                                        value={passwordForm.data.email}
                                        onChange={(event) => passwordForm.setData("email", event.target.value)}
                                        required
                                        autoComplete="email"
                                        aria-invalid={Boolean(passwordErrors.email)}
                                        aria-describedby={emailErrorId}
                                    />
                                    {passwordErrors.email ? (
                                        <FieldDescription id="modal-email-error" role="alert" className="text-destructive">
                                            {passwordErrors.email}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="modal-password">Contraseña</FieldLabel>
                                    <Input
                                        id="modal-password"
                                        data-testid="modal-login-password-input"
                                        name="password"
                                        type="password"
                                        value={passwordForm.data.password}
                                        onChange={(event) => passwordForm.setData("password", event.target.value)}
                                        required
                                        autoComplete="current-password"
                                        aria-invalid={Boolean(passwordErrors.password)}
                                        aria-describedby={passwordErrorId}
                                    />
                                    {passwordErrors.password ? (
                                        <FieldDescription id="modal-password-error" role="alert" className="text-destructive">
                                            {passwordErrors.password}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Button type="submit" data-testid="modal-login-submit" className="w-full" disabled={isPasswordSubmitting}>
                                    {isPasswordSubmitting ? <Spinner className="mr-2" /> : null}
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
