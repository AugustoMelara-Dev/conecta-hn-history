import { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import DOMPurify from 'dompurify';
import GuestLayout from '@/Layouts/GuestLayout';
import SupportDiagnosticCard from '@/Components/SupportDiagnosticCard';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { t } from '@/lib/i18n';

function normalizePaymentStatus(rawStatus) {
    if (rawStatus === 'approved' || rawStatus === 'completed' || rawStatus === 'paid') {
        return 'approved';
    }

    if (rawStatus === 'rejected' || rawStatus === 'failed' || rawStatus === 'error') {
        return 'rejected';
    }

    return 'pending';
}

export default function PaymentPending({ payment, bankInstructions, whatsappUrl, onboardingNextAction = null, diagnostic = null }) {
    const normalizedStatus = normalizePaymentStatus(payment?.status);
    const [liveMessage, setLiveMessage] = useState(t('billing.paymentPending.initialLiveMessage'));

    const hasWhatsAppSupport = typeof whatsappUrl === 'string' && whatsappUrl.startsWith('https://wa.me/');
    const sanitizedBankInstructions = bankInstructions
        ? DOMPurify.sanitize(bankInstructions, {
            ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'a'],
            ALLOWED_ATTR: ['href', 'target', 'rel'],
        })
        : '';

    useEffect(() => {
        if (normalizedStatus === 'approved') {
            setLiveMessage(t('billing.paymentPending.approvedLiveMessage'));
            return;
        }

        if (normalizedStatus === 'rejected') {
            setLiveMessage(t('billing.paymentPending.rejectedLiveMessage'));
            return;
        }

        setLiveMessage(t('billing.paymentPending.updatedLiveMessage'));
    }, [normalizedStatus]);

    return (
        <GuestLayout>
            <Head title={t('billing.paymentPending.headTitle')} />

            <div className="min-h-screen bg-slate-50 px-4 py-10">
                <div className="mx-auto max-w-2xl space-y-4">
                    <div className="sr-only" aria-live="polite" aria-atomic="true">{liveMessage}</div>

                    <Card className="border-slate-200 shadow-sm">
                        <CardHeader>
                            <div className="flex items-center justify-between gap-3">
                                <CardTitle className="text-xl">{t('billing.paymentPending.title')}</CardTitle>
                                <Badge
                                    variant="outline"
                                    className={
                                        normalizedStatus === 'approved'
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-900'
                                            : normalizedStatus === 'rejected'
                                                ? 'border-red-300 bg-red-50 text-red-900'
                                                : 'border-amber-300 bg-amber-50 text-amber-900'
                                    }
                                >
                                    {normalizedStatus === 'approved' ? t('billing.paymentPending.approvedBadge') : normalizedStatus === 'rejected' ? t('billing.paymentPending.rejectedBadge') : t('billing.paymentPending.badge')}
                                </Badge>
                            </div>
                            <p className="text-sm text-slate-600">
                                {t('billing.paymentPending.intro', { tenantName: payment.tenant_name })}
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                                <div className="flex justify-between py-1"><span className="text-slate-500">{t('billing.paymentPending.reference')}</span><span className="font-mono font-semibold text-slate-900">{payment.reference_number}</span></div>
                                <div className="flex justify-between py-1"><span className="text-slate-500">{t('billing.paymentPending.amount')}</span><span className="font-semibold text-slate-900">L. {payment.amount?.toLocaleString('es-HN', { minimumFractionDigits: 2 })}</span></div>
                                <div className="flex justify-between py-1"><span className="text-slate-500">{t('billing.paymentPending.status')}</span><span className="font-medium text-slate-800">{normalizedStatus === 'approved' ? t('billing.paymentPending.approvedBadge') : normalizedStatus === 'rejected' ? t('billing.paymentPending.rejectedBadge') : t('billing.paymentPending.statusPendingReview')}</span></div>
                            </div>

                            {sanitizedBankInstructions && (
                                <Alert variant="warning" role="note">
                                    <AlertTitle>{t('billing.paymentPending.paymentInstructions')}</AlertTitle>
                                    <AlertDescription>
                                        <div className="prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: sanitizedBankInstructions }} />
                                    </AlertDescription>
                                </Alert>
                            )}

                            {hasWhatsAppSupport && normalizedStatus !== 'approved' ? (
                                <Button asChild className="w-full">
                                    <a href={whatsappUrl} target="_blank" rel="noopener noreferrer">
                                        {t('billing.paymentPending.whatsappAction')}
                                    </a>
                                </Button>
                            ) : (
                                <Alert variant="warning" role="alert">
                                    <AlertTitle>{t('billing.paymentPending.whatsappUnavailableTitle')}</AlertTitle>
                                    <AlertDescription>
                                        {t('billing.paymentPending.whatsappUnavailableDescription', { reference: payment.reference_number })}
                                    </AlertDescription>
                                </Alert>
                            )}

                            <p className="text-sm text-slate-600" role="status" aria-live="polite">
                                {normalizedStatus === 'approved'
                                    ? t('billing.paymentPending.approvedStatus')
                                    : normalizedStatus === 'rejected'
                                        ? t('billing.paymentPending.rejectedStatus')
                                        : t('billing.paymentPending.noFakeApproval')}
                            </p>

                            {onboardingNextAction ? (
                                <div className="rounded-lg border border-slate-200 bg-white p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t('billing.paymentPending.nextActionEyebrow')}</p>
                                    <p className="mt-1 font-semibold text-slate-900">{onboardingNextAction.title}</p>
                                    <p className="mt-1 text-sm text-slate-600">{onboardingNextAction.copy}</p>
                                    {typeof onboardingNextAction.progress === 'number' ? (
                                        <p className="mt-2 text-xs text-slate-500">{t('billing.paymentPending.progressLabel', { progress: onboardingNextAction.progress })}</p>
                                    ) : null}
                                    {onboardingNextAction.href ? (
                                        <Button asChild variant="outline" className="mt-3">
                                            <Link href={onboardingNextAction.href}>{t('billing.paymentPending.nextActionCta')}</Link>
                                        </Button>
                                    ) : null}
                                </div>
                            ) : null}

                            <SupportDiagnosticCard diagnostic={diagnostic} />

                            <div className="grid gap-3 sm:grid-cols-2">
                                <Button asChild variant="outline">
                                    <Link href={route('dashboard')}>Volver al panel</Link>
                                </Button>
                                <Button asChild variant="secondary">
                                    <Link href={route('payment.pending', { payment: payment.id })}>Actualizar estado</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </GuestLayout>
    );
}

