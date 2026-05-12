import { useState } from 'react';
import { Download, Printer } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Spinner } from '@/Components/ui/spinner';
import { cn } from '@/lib/utils';

function safeNamedRoute(name, fallback, params) {
    try {
        if (route().has(name)) {
            return route(name, params);
        }
    } catch {
        return fallback;
    }

    return fallback;
}

function resetSoon(callback) {
    window.setTimeout(callback, 1000);
}

export function DownloadReceiptButton({ orderId, className = '' }) {
    const [isLoading, setIsLoading] = useState(false);

    const handleDownload = () => {
        const downloadUrl = safeNamedRoute('receipt.download', null, orderId);

        if (!downloadUrl) {
            return;
        }

        setIsLoading(true);

        window.open(
            downloadUrl,
            '_blank'
        );

        resetSoon(() => setIsLoading(false));
    };

    return (
        <Button
            type="button"
            variant="outline"
            onClick={handleDownload}
            disabled={isLoading}
            className={cn(className)}
        >
            {isLoading ? <Spinner data-icon="inline-start" /> : <Printer data-icon="inline-start" aria-hidden="true" />}
            {isLoading ? 'Generando' : 'Imprimir recibo'}
        </Button>
    );
}

export function DownloadSalesReportButton({ startDate, endDate, className = '', ...props }) {
    const [isLoading, setIsLoading] = useState(false);
    const baseUrl = safeNamedRoute('reports.sales-pdf', null);
    const disabled = isLoading || !startDate || !endDate || !baseUrl;
    const queryParams = new URLSearchParams({
        start_date: startDate || '',
        end_date: endDate || '',
    }).toString();
    const downloadUrl = baseUrl ? `${baseUrl}?${queryParams}` : '';

    const handleDownload = () => {
        if (disabled) {
            return;
        }

        setIsLoading(true);

        window.open(downloadUrl, '_blank');

        resetSoon(() => setIsLoading(false));
    };

    return (
        <Button
            type="button"
            onClick={handleDownload}
            disabled={disabled}
            className={cn(className)}
            aria-disabled={disabled}
            data-download-url={downloadUrl}
            {...props}
        >
            {isLoading ? <Spinner data-icon="inline-start" /> : <Download data-icon="inline-start" aria-hidden="true" />}
            {isLoading ? 'Generando' : 'Descargar PDF'}
        </Button>
    );
}

export function PrintOrderTicket({ orderId, variant = 'button' }) {
    const [isLoading, setIsLoading] = useState(false);

    const handlePrint = () => {
        const ticketUrl = safeNamedRoute('orders.print-ticket', null, orderId);

        if (!ticketUrl) {
            return;
        }

        setIsLoading(true);

        const printWindow = window.open(
            ticketUrl,
            'print_ticket',
            'width=300,height=500'
        );

        if (printWindow) {
            printWindow.onload = () => {
                printWindow.print();
                setIsLoading(false);
            };
        } else {
            setIsLoading(false);
        }
    };

    if (variant === 'icon') {
        return (
            <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={handlePrint}
                disabled={isLoading}
                aria-label="Imprimir ticket"
            >
                {isLoading ? <Spinner /> : <Printer aria-hidden="true" />}
            </Button>
        );
    }

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={handlePrint}
            disabled={isLoading}
        >
            {isLoading ? <Spinner data-icon="inline-start" /> : <Printer data-icon="inline-start" aria-hidden="true" />}
            Ticket
        </Button>
    );
}

export default {
    DownloadReceiptButton,
    DownloadSalesReportButton,
    PrintOrderTicket,
};
