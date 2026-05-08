import { useForm } from "@inertiajs/react"
import { LockKeyhole } from "lucide-react"

import { Button } from "@/Components/ui/button"
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card"
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
        <Card className={cn("overflow-hidden shadow-sm", className)} {...props}>
            <CardHeader className="gap-2 border-b bg-muted/30 text-center">
                <div className="mx-auto flex size-11 items-center justify-center rounded-lg border bg-background text-muted-foreground">
                    <LockKeyhole aria-hidden="true" />
                </div>
                <CardTitle>Confirma tu contrasena</CardTitle>
                <CardDescription>
                    Esta zona protege informacion sensible. Confirma tu contrasena para continuar.
                </CardDescription>
            </CardHeader>

            <CardContent className="p-5 sm:p-6">
                <form onSubmit={submit}>
                    <FieldGroup>
                        <Field data-invalid={Boolean(errors.password)}>
                            <FieldLabel htmlFor="password">Contrasena</FieldLabel>
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
                                className={cn(errors.password && "border-destructive/50 focus-visible:ring-destructive/20")}
                            />
                            {errors.password ? (
                                <FieldDescription id="confirm-password-error" role="alert" className="text-destructive">
                                    {errors.password}
                                </FieldDescription>
                            ) : null}
                        </Field>

                        <Field>
                            <Button type="submit" disabled={processing} data-testid="confirm-password-submit" className="w-full">
                                {processing ? <Spinner data-icon="inline-start" /> : null}
                                {processing ? "Confirmando..." : "Confirmar"}
                            </Button>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    )
}
