import { Head } from "@inertiajs/react"
import { Clock3, MessageCircle, ReceiptText } from "lucide-react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card"
import { Separator } from "@/Components/ui/separator"

function money(value) {
    return Number(value ?? 0).toLocaleString("es-HN", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

function DetailRow({ label, value, valueClassName = "" }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <span className="text-sm text-muted-foreground">{label}</span>
            <div className={`text-right text-sm font-semibold text-foreground ${valueClassName}`}>
                {value}
            </div>
        </div>
    )
}

function instructionLines(value) {
    return String(value || "")
        .replace(/<script[\s\S]*?<\/script>/gi, "")
        .replace(/\son\w+="[^"]*"/gi, "")
        .replace(/\son\w+='[^']*'/gi, "")
        .replace(/<br\s*\/?>/gi, "\n")
        .replace(/<\/(p|div|li|h[1-6])>/gi, "\n")
        .replace(/<[^>]*>/g, "")
        .replace(/&nbsp;/gi, " ")
        .replace(/&amp;/gi, "&")
        .replace(/&lt;/gi, "<")
        .replace(/&gt;/gi, ">")
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean)
}

export default function PaymentPending({ payment, bankInstructions, whatsappUrl }) {
    const safeBankInstructions = instructionLines(bankInstructions)

    return (
        <>
            <Head>
                <title>Completar pago | Vito Business OS</title>
                <meta
                    name="description"
                    content="Instrucciones para completar el pago PRO de un negocio registrado en Vito Business OS."
                />
            </Head>

            <AuthShadcnShell imageAlt="Estado de pago pendiente de Vito Business OS" maxWidthClassName="max-w-lg">
                <Card data-testid="payment-pending-card">
                    <CardHeader className="gap-4 text-center">
                        <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-secondary text-secondary-foreground">
                            <Clock3 aria-hidden="true" />
                        </div>
                        <div className="flex justify-center">
                            <Badge variant="secondary">Pago pendiente</Badge>
                        </div>
                        <div className="flex flex-col gap-2">
                            <CardTitle>Falta un paso</CardTitle>
                            <CardDescription>
                                Tu negocio <strong className="font-semibold text-foreground">{payment.tenant_name}</strong> esta casi listo.
                                Solo necesitamos confirmar tu pago PRO.
                            </CardDescription>
                        </div>
                    </CardHeader>

                    <CardContent className="flex flex-col gap-6">
                        <div className="rounded-lg border bg-muted/50 p-4" data-testid="payment-pending-summary">
                            <div className="flex flex-col gap-4">
                                <DetailRow
                                    label="Referencia"
                                    value={payment.reference_number}
                                    valueClassName="font-mono"
                                />
                                <Separator />
                                <DetailRow
                                    label="Monto"
                                    value={`L. ${money(payment.amount)}`}
                                    valueClassName="text-lg"
                                />
                                <Separator />
                                <DetailRow
                                    label="Estado"
                                    value={<Badge variant="outline">Pendiente</Badge>}
                                />
                            </div>
                        </div>

                        {safeBankInstructions.length ? (
                            <Alert data-testid="payment-pending-instructions">
                                <ReceiptText aria-hidden="true" />
                                <AlertTitle>Instrucciones de pago</AlertTitle>
                                <AlertDescription>
                                    <div className="mt-2 flex flex-col gap-1 text-sm text-muted-foreground">
                                        {safeBankInstructions.map((line, index) => (
                                            <p key={`${index}-${line}`}>{line}</p>
                                        ))}
                                    </div>
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        {whatsappUrl ? (
                            <Button asChild size="lg" data-testid="payment-pending-whatsapp">
                                <a href={whatsappUrl} target="_blank" rel="noopener noreferrer">
                                    <MessageCircle data-icon="inline-start" aria-hidden="true" />
                                    Enviar comprobante por WhatsApp
                                </a>
                            </Button>
                        ) : null}

                        <p className="text-center text-sm text-muted-foreground" aria-live="polite" data-testid="payment-pending-live-region">
                            Una vez confirmado el pago, recibiras un correo de activacion.
                        </p>
                    </CardContent>
                </Card>
            </AuthShadcnShell>
        </>
    )
}
