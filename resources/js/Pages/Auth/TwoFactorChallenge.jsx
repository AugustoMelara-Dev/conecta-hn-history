import { Head, useForm } from "@inertiajs/react"
import { KeyRound, ShieldCheck } from "lucide-react"
import { useEffect, useRef, useState } from "react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import InputError from "@/Components/InputError"
import { Button } from "@/Components/ui/button"
import { Input } from "@/Components/ui/input"
import { Label } from "@/Components/ui/label"

export default function TwoFactorChallenge() {
    const [useRecoveryCode, setUseRecoveryCode] = useState(false)
    const { data, setData, post, processing, errors, reset } = useForm({
        code: "",
        recovery_code: "",
    })

    const codeRef = useRef(null)
    const recoveryRef = useRef(null)
    useEffect(() => {
        const target = useRecoveryCode ? recoveryRef.current : codeRef.current
        if (target) target.focus()
    }, [useRecoveryCode])

    const submit = (event) => {
        event.preventDefault()
        post(route("two-factor.login.store"), {
            onFinish: () => reset(useRecoveryCode ? "recovery_code" : "code"),
        })
    }

    const toggleMode = () => {
        setUseRecoveryCode((current) => !current)
        reset("code", "recovery_code")
    }

    return (
        <>
            <Head title="Verificacion en dos pasos | Dovra AI" />
            <AuthShadcnShell
                imageAlt="Verificacion segura de acceso"
                eyebrow="Segundo factor"
                title="Confirma que eres tu"
                description="Introduce el codigo temporal de tu aplicacion autenticadora. Tambien puedes usar uno de tus codigos de recuperacion."
            >
                <form className="grid gap-5" onSubmit={submit}>
                    <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">
                        <ShieldCheck className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                        <p>La contrasena ya fue validada. Este paso evita que alguien entre aunque la conozca.</p>
                    </div>

                    {useRecoveryCode ? (
                        <div className="grid gap-2">
                            <Label htmlFor="recovery_code">Codigo de recuperacion</Label>
                            <Input
                                id="recovery_code"
                                name="recovery_code"
                                autoComplete="one-time-code"
                                ref={recoveryRef}
                                value={data.recovery_code}
                                onChange={(event) => setData("recovery_code", event.target.value)}
                                required
                            />
                            <InputError message={errors.recovery_code} />
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor="code">Codigo de 6 digitos</Label>
                            <Input
                                id="code"
                                name="code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                pattern="[0-9]*"
                                maxLength={6}
                                ref={codeRef}
                                value={data.code}
                                onChange={(event) => setData("code", event.target.value.replace(/\D/g, "").slice(0, 6))}
                                required
                            />
                            <InputError message={errors.code} />
                        </div>
                    )}

                    <Button type="submit" disabled={processing} className="w-full">
                        <KeyRound className="mr-2 size-4" aria-hidden="true" />
                        {processing ? "Verificando..." : "Verificar y entrar"}
                    </Button>

                    <Button type="button" variant="ghost" onClick={toggleMode} className="w-full">
                        {useRecoveryCode ? "Usar codigo de la aplicacion" : "Usar codigo de recuperacion"}
                    </Button>
                </form>
            </AuthShadcnShell>
        </>
    )
}
