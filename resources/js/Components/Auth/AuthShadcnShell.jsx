import { Link } from "@inertiajs/react"
import { ShieldCheck, Store } from "lucide-react"
import DovraMonogram from "@/Components/Brand/DovraMonogram"

import { Badge } from "@/Components/ui/badge"
import { Card, CardContent } from "@/Components/ui/card"
import { SkipLink } from "@/Components/ui/skip-link"
import { PUBLIC_BRAND } from '@/lib/publicBrand';
import { cn } from "@/lib/utils"

export default function AuthShadcnShell({
    children,
    imageSrc = "/auth-side.svg",
    imageAlt = "",
    maxWidthClassName = "max-w-md",
    eyebrow = "Acceso seguro",
    title = "Gestiona tu negocio con claridad",
    description = `Entra a tu cuenta, confirma tu correo o recupera el acceso sin salir del flujo principal de ${PUBLIC_BRAND.name}.`,
}) {
    return (
        <div className="grid min-h-svh min-w-0 bg-[radial-gradient(circle_at_top_right,hsl(var(--secondary))_0,transparent_34%),linear-gradient(180deg,#fff_0%,#f8fafc_100%)] text-foreground lg:grid-cols-[minmax(0,0.86fr)_minmax(0,1.14fr)]">
            <SkipLink />
            <div className="flex min-h-svh min-w-0 flex-col gap-6 p-5 sm:p-8 md:p-10">
                <div className="flex justify-center gap-2 md:justify-start">
                    <Link
                        href="/"
                        className="flex items-center gap-2 rounded-md text-sm font-medium text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <div className="flex size-7 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <DovraMonogram variant="mark" aria-hidden="true" className="size-7 text-primary-foreground" />
                        </div>
                        {PUBLIC_BRAND.name}
                    </Link>
                </div>
                <main id="main-content" tabIndex={-1} className="flex min-w-0 flex-1 items-center justify-center outline-none">
                    <div className={cn("min-w-0 w-full", maxWidthClassName)}>{children}</div>
                </main>
            </div>

            <aside className="relative hidden min-h-svh overflow-hidden border-l bg-background lg:block">
                <img
                    src={imageSrc}
                    alt={imageAlt}
                    className="absolute inset-0 h-full w-full object-cover"
                />
                <div className="absolute inset-0 bg-background/88" aria-hidden="true" />
                <div className="relative z-10 flex h-full flex-col justify-between p-10">
                    <div className="flex justify-end">
                        <Badge variant="secondary">{eyebrow}</Badge>
                    </div>

                    <div className="flex max-w-md flex-col gap-6">
                        <div className="flex flex-col gap-3">
                            <h1 className="font-display text-5xl font-bold leading-tight tracking-normal">
                                {title}
                            </h1>
                            <p className="text-base leading-7 text-muted-foreground">
                                {description}
                            </p>
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <Card>
                                <CardContent className="flex items-center gap-3 p-4 text-sm font-medium text-muted-foreground">
                                    <ShieldCheck aria-hidden="true" />
                                    Datos protegidos
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="flex items-center gap-3 p-4 text-sm font-medium text-muted-foreground">
                                    <Store aria-hidden="true" />
                                    Panel de negocio
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    )
}
