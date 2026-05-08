import { useCallback, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Plus, ShoppingBag, Ticket } from 'lucide-react';
import TenantLayout from '@/Layouts/TenantLayout';
import CouponCard from '@/Components/Marketing/CouponCard';
import StatCard from '@/Components/Stats/StatCard';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

function CreateCouponDialog({ open, onOpenChange }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        type: 'percentage',
        value: '',
        usage_limit: '',
        expires_at: '',
    });

    const closeDialog = () => {
        onOpenChange(false);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        post(route('tenant.coupons.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                closeDialog();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nuevo cupon</DialogTitle>
                    <DialogDescription>
                        Crea un codigo de descuento sin salir del panel de marketing.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    <FieldGroup>
                        <Field data-invalid={Boolean(errors.code)}>
                            <FieldLabel htmlFor="coupon-code">Codigo del cupon *</FieldLabel>
                            <Input
                                id="coupon-code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                placeholder="VERANO2026"
                                className="font-mono uppercase tracking-wide"
                                aria-invalid={Boolean(errors.code)}
                            />
                            {errors.code ? (
                                <FieldDescription className="text-destructive">
                                    {errors.code}
                                </FieldDescription>
                            ) : null}
                        </Field>

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Field>
                                <FieldLabel htmlFor="coupon-type">Tipo de descuento</FieldLabel>
                                <Select
                                    value={data.type}
                                    onValueChange={(value) => setData('type', value)}
                                >
                                    <SelectTrigger id="coupon-type">
                                        <SelectValue placeholder="Selecciona tipo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="percentage">Porcentaje (%)</SelectItem>
                                            <SelectItem value="fixed">Monto fijo (L.)</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>

                            <Field data-invalid={Boolean(errors.value)}>
                                <FieldLabel htmlFor="coupon-value">Valor *</FieldLabel>
                                <Input
                                    id="coupon-value"
                                    type="number"
                                    min="1"
                                    max={data.type === 'percentage' ? 100 : 99999}
                                    value={data.value}
                                    onChange={(e) => setData('value', e.target.value)}
                                    placeholder={data.type === 'percentage' ? '20' : '100'}
                                    aria-invalid={Boolean(errors.value)}
                                />
                                {errors.value ? (
                                    <FieldDescription className="text-destructive">
                                        {errors.value}
                                    </FieldDescription>
                                ) : null}
                            </Field>
                        </div>

                        <Field>
                            <FieldLabel htmlFor="coupon-usage-limit">
                                Limite de uso (opcional)
                            </FieldLabel>
                            <Input
                                id="coupon-usage-limit"
                                type="number"
                                min="1"
                                value={data.usage_limit}
                                onChange={(e) => setData('usage_limit', e.target.value)}
                                placeholder="Sin limite"
                            />
                            <FieldDescription>
                                Dejalo vacio si el cupon no tiene limite de canjes.
                            </FieldDescription>
                        </Field>

                        <Field>
                            <FieldLabel htmlFor="coupon-expires-at">
                                Fecha de expiracion (opcional)
                            </FieldLabel>
                            <Input
                                id="coupon-expires-at"
                                type="date"
                                value={data.expires_at}
                                onChange={(e) => setData('expires_at', e.target.value)}
                                min={new Date().toISOString().split('T')[0]}
                            />
                        </Field>
                    </FieldGroup>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button type="button" variant="outline" onClick={closeDialog}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creando...' : 'Crear cupon'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function MarketingIndex({ coupons = [], stats = {} }) {
    const [showCreateDialog, setShowCreateDialog] = useState(false);

    const handleToggle = useCallback(() => {
        // CouponCard owns the request; this callback preserves the existing component API.
    }, []);

    return (
        <TenantLayout>
            <Head title="Marketing" />

            <div className="min-h-screen bg-background">
                <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div>
                            <h1 className="text-xl font-semibold text-foreground">Marketing</h1>
                            <p className="text-sm text-muted-foreground">
                                Cupones y promociones para convertir visitas en pedidos.
                            </p>
                        </div>
                        <Button onClick={() => setShowCreateDialog(true)}>
                            <Plus data-icon="inline-start" />
                            Nuevo cupon
                        </Button>
                    </div>
                </header>

                <main className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
                    <section className="grid grid-cols-1 gap-6 md:grid-cols-3" aria-label="Resumen de marketing">
                        <StatCard
                            title="Total cupones"
                            value={stats.total_coupons || 0}
                            icon={<Ticket aria-hidden="true" />}
                        />
                        <StatCard
                            title="Cupones activos"
                            value={stats.active_coupons || 0}
                            trend={stats.active_trend}
                            icon={<CheckCircle2 aria-hidden="true" />}
                        />
                        <StatCard
                            title="Total canjeados"
                            value={stats.total_redemptions || 0}
                            trend={stats.redemption_trend}
                            trendLabel="vs mes anterior"
                            icon={<ShoppingBag aria-hidden="true" />}
                        />
                    </section>

                    {coupons.length > 0 ? (
                        <section
                            className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                            aria-label="Cupones"
                        >
                            {coupons.map((coupon) => (
                                <CouponCard
                                    key={coupon.id}
                                    coupon={coupon}
                                    onToggle={handleToggle}
                                />
                            ))}
                        </section>
                    ) : (
                        <Card>
                            <CardHeader className="items-center text-center">
                                <div className="mb-3 flex size-12 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                    <Ticket aria-hidden="true" />
                                </div>
                                <CardTitle>Sin cupones activos</CardTitle>
                                <CardDescription className="max-w-md">
                                    Crea tu primer cupon de descuento para atraer mas clientes y medir conversion.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex justify-center">
                                <Button onClick={() => setShowCreateDialog(true)}>
                                    <Plus data-icon="inline-start" />
                                    Crear mi primer cupon
                                </Button>
                            </CardContent>
                        </Card>
                    )}
                </main>

                <CreateCouponDialog
                    open={showCreateDialog}
                    onOpenChange={setShowCreateDialog}
                />
            </div>
        </TenantLayout>
    );
}
