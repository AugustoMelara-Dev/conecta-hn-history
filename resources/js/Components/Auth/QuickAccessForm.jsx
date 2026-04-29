import { useState } from "react"
import { KeyRound, MailCheck, RotateCcw, Send } from "lucide-react"
import axios from "axios"

import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Button } from "@/Components/ui/button"
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { InputOTP } from "@/Components/ui/input-otp"
import { Spinner } from "@/Components/ui/spinner"

export function QuickAccessForm({ className = "", onAuthenticated }) {
    const [step, setStep] = useState("identifier")
    const [identifier, setIdentifier] = useState("")
    const [otpIdentifier, setOtpIdentifier] = useState("")
    const [tenantId, setTenantId] = useState("")
    const [code, setCode] = useState("")
    const [deliveryMode, setDeliveryMode] = useState("code")
    const [isLoading, setIsLoading] = useState(false)
    const [error, setError] = useState("")
    const [notice, setNotice] = useState("")

    const submitIdentifier = async (event) => {
        event.preventDefault()
        setIsLoading(true)
        setError("")
        setNotice("")

        try {
            const response = await axios.post("/api/v1/otp/generate", {
                identifier,
            })

            setOtpIdentifier(identifier)
            setTenantId(response.data.tenant_id || "")
            setDeliveryMode(response.data.delivery_mode || "code")
            setNotice(response.data.message || "Te enviamos un código de acceso.")
            setStep("verify")
        } catch (requestError) {
            setError(
                requestError.response?.data?.errors?.identifier?.[0]
                || requestError.response?.data?.message
                || "No pudimos enviar el acceso. Revisa el correo, espera unos segundos e intenta de nuevo."
            )
        } finally {
            setIsLoading(false)
        }
    }

    const submitCode = async (event) => {
        event.preventDefault()
        setIsLoading(true)
        setError("")

        try {
            const response = await axios.post("/api/v1/otp/verify", {
                identifier: otpIdentifier,
                otp_code: code,
                tenant_id: tenantId || undefined,
            })

            if (response.data.requires_registration) {
                window.location.href = `${route("tenant.register")}?identifier=${encodeURIComponent(otpIdentifier)}`
                return
            }

            const destination = response.data.redirect_to || response.data.redirect || route("directory.index")
            onAuthenticated?.(destination)
            window.location.href = destination
        } catch (requestError) {
            setError(
                requestError.response?.data?.errors?.otp_code?.[0]
                || requestError.response?.data?.errors?.code?.[0]
                || requestError.response?.data?.message
                || "Código inválido o vencido. Solicita uno nuevo si el correo tardó en llegar."
            )
        } finally {
            setIsLoading(false)
        }
    }

    const reset = () => {
        setStep("identifier")
        setCode("")
        setError("")
        setNotice("")
        setTenantId("")
        setOtpIdentifier("")
        setDeliveryMode("code")
    }

    if (step === "verify") {
        return (
            <form onSubmit={submitCode} className={className} data-testid="otp-verify-form">
                <FieldGroup>
                    <Alert variant="success">
                        <div className="flex gap-3">
                            <MailCheck className="mt-0.5 h-4 w-4 shrink-0" />
                            <div>
                                <AlertTitle>Revisa tu correo</AlertTitle>
                                <AlertDescription>
                                    {deliveryMode === "magic_link_and_code"
                                        ? `Toca el enlace seguro en este dispositivo o escribe el código enviado a ${otpIdentifier}.`
                                        : `Escribe el código de 6 dígitos enviado a ${otpIdentifier}.`}
                                </AlertDescription>
                            </div>
                        </div>
                    </Alert>

                    {notice ? (
                        <FieldDescription className="text-slate-600">{notice}</FieldDescription>
                    ) : null}

                    <Field>
                        <FieldLabel htmlFor="otp-code">Código de respaldo</FieldLabel>
                        <InputOTP
                            id="otp-code"
                            value={code}
                            onChange={setCode}
                            required
                            autoFocus
                            data-testid="otp-code-input"
                        />
                        <FieldDescription>
                            Úsalo si abriste el correo en otro dispositivo o si prefieres escribirlo manualmente.
                        </FieldDescription>
                    </Field>

                    {error ? (
                        <Alert variant="destructive" role="alert" aria-live="polite">
                            <AlertTitle>No pudimos verificar el acceso</AlertTitle>
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    ) : null}

                    <div className="grid grid-cols-[0.7fr_1fr] gap-2">
                        <Button type="button" variant="outline" onClick={reset} disabled={isLoading}>
                            <RotateCcw className="mr-2 h-4 w-4" />
                            Cambiar correo
                        </Button>
                        <Button type="submit" disabled={isLoading || code.length !== 6}>
                            {isLoading ? <Spinner className="mr-2" /> : <KeyRound className="mr-2 h-4 w-4" />}
                            Verificar y entrar
                        </Button>
                    </div>
                </FieldGroup>
            </form>
        )
    }

    return (
        <form onSubmit={submitIdentifier} className={className} data-testid="otp-generate-form">
            <FieldGroup>
                <Field>
                    <FieldLabel htmlFor="quick-access-identifier">Correo de acceso</FieldLabel>
                    <Input
                        id="quick-access-identifier"
                        type="email"
                        value={identifier}
                        onChange={(event) => setIdentifier(event.target.value)}
                        placeholder="nombre@negocio.hn"
                        autoComplete="email"
                        spellCheck={false}
                        required
                        data-testid="otp-identifier-input"
                    />
                    <FieldDescription>
                        Te enviaremos un enlace seguro si está habilitado y un código de 6 dígitos como respaldo.
                    </FieldDescription>
                </Field>

                {error ? (
                    <Alert variant="destructive" role="alert" aria-live="polite">
                        <AlertTitle>No pudimos enviar el acceso</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <Button type="submit" disabled={isLoading} className="w-full">
                    {isLoading ? <Spinner className="mr-2" /> : <Send className="mr-2 h-4 w-4" />}
                    Enviar acceso
                </Button>
            </FieldGroup>
        </form>
    )
}
