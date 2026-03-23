import { useForm } from "@inertiajs/react"

import { Button } from "@/Components/ui/button"
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
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
                    <h1 className="text-2xl font-bold text-slate-950">Confirm your password</h1>
                    <p className="text-sm text-balance text-slate-500">
                        This is a secure area of the application. Please confirm your password before continuing.
                    </p>
                </div>

                <Field>
                    <FieldLabel htmlFor="password">Password</FieldLabel>
                    <Input
                        id="password"
                        type="password"
                        required
                        autoFocus
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(event) => setData("password", event.target.value)}
                        className={cn(
                            inputClassName,
                            errors.password && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.password ? (
                        <FieldDescription className="text-rose-600">{errors.password}</FieldDescription>
                    ) : null}
                </Field>

                <Field>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-slate-950 text-white hover:bg-slate-800"
                    >
                        {processing ? "Confirming..." : "Confirm"}
                    </Button>
                </Field>
            </FieldGroup>
        </form>
    )
}
