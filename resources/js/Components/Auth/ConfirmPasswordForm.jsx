import { useForm } from "@inertiajs/react"

import { Button } from "@/Components/ui/button"
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { Spinner } from "@/Components/ui/spinner"
import { cn } from "@/lib/utils"

export function ConfirmPasswordForm({ className, ...props }) {
    const inputClassName =
        "h-10 border-slate-200 bg-white text-slate-950 placeholder:text-slate-400 focus-visible:ring-slate-300 focus-visible:ring-offset-0"

    const { data, setData, post, processing, errors, reset } = useForm({
        password: "",
    })

    const submit = (event) => {
        event.preventDefault()

        post(route("password.confirm"), {
            onFinish: () => reset("password"),
        })
    }

    return (
        <form onSubmit={submit} className={cn("flex flex-col gap-6", className)} {...props}>
            <FieldGroup>
                <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold text-slate-950">Confirma tu contraseña</h1>
                    <p className="text-sm text-balance text-slate-500">
                        Esta zona protege información sensible. Confirma tu contraseña para continuar.
                    </p>
                </div>

                <Field>
                    <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                    <Input
                        id="password"
                        type="password"
                        required
                        autoFocus
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(event) => setData("password", event.target.value)}
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? "confirm-password-error" : undefined}
                        data-testid="confirm-password-input"
                        className={cn(
                            inputClassName,
                            errors.password && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.password ? (
                        <FieldDescription id="confirm-password-error" className="text-rose-600">
                            {errors.password}
                        </FieldDescription>
                    ) : null}
                </Field>

                <Field>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-testid="confirm-password-submit"
                        className="w-full bg-slate-950 text-white hover:bg-slate-800"
                    >
                        {processing ? <Spinner className="mr-2" /> : null}
                        {processing ? "Confirmando..." : "Confirmar"}
                    </Button>
                </Field>
            </FieldGroup>
        </form>
    )
}
