/**
 * Components/Reports/ReportActions.jsx - PDF Trigger System
 *
 * PURPOSE: Action components that trigger server-side PDF generation.
 * DO NOT render PDFs in React - use window.open for backend rendering.
 *
 * EXPORTS:
 * - DownloadReceiptButton: Triggers order receipt download
 * - DownloadSalesReportButton: Triggers filtered sales summary PDF
 */

import { useState } from 'react';

// ═══════════════════════════════════════════════════════════════════════════
// DOWNLOAD RECEIPT BUTTON
// ═══════════════════════════════════════════════════════════════════════════
/**
 * @param {Object} props
 * @param {number} props.orderId - Order ID to generate receipt for
 * @param {string} props.className - Additional CSS classes
 */
export function DownloadReceiptButton({ orderId, className = '' }) {
    const [isLoading, setIsLoading] = useState(false);

    const handleDownload = () => {
        setIsLoading(true);

        // Open in new tab - Laravel will stream the PDF
        window.open(
            route('receipt.download', orderId),
            '_blank'
        );

        // Reset loading state after brief delay
        setTimeout(() => setIsLoading(false), 1000);
    };

    return (
        <button
            onClick={handleDownload}
            disabled={isLoading}
            className={`inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-xl transition-colors disabled:opacity-50 ${className}`}
        >
            {isLoading ? (
                <>
                    <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Generando...
                </>
            ) : (
                <>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Imprimir Recibo
                </>
            )}
        </button>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// DOWNLOAD SALES REPORT BUTTON
// ═══════════════════════════════════════════════════════════════════════════
/**
 * @param {Object} props
 * @param {string} props.startDate - Start date (YYYY-MM-DD)
 * @param {string} props.endDate - End date (YYYY-MM-DD)
 * @param {string} props.className - Additional CSS classes
 */
export function DownloadSalesReportButton({ startDate, endDate, className = '' }) {
    const [isLoading, setIsLoading] = useState(false);

    const handleDownload = () => {
        if (!startDate || !endDate) {
            alert('Selecciona un rango de fechas primero.');
            return;
        }

        setIsLoading(true);

        // Build query string from active filters
        const queryParams = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
        }).toString();

        // Open in new tab - server generates PDF
        window.open(
            `${route('reports.sales-pdf')}?${queryParams}`,
            '_blank'
        );

        setTimeout(() => setIsLoading(false), 1000);
    };

    return (
        <button
            onClick={handleDownload}
            disabled={isLoading || !startDate || !endDate}
            className={`inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed ${className}`}
        >
            {isLoading ? (
                <>
                    <svg className="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Generando...
                </>
            ) : (
                <>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Descargar PDF
                </>
            )}
        </button>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// PRINT ORDER TICKET (Thermal Printer)
// ═══════════════════════════════════════════════════════════════════════════
/**
 * @param {Object} props
 * @param {number} props.orderId - Order ID
 * @param {string} props.variant - 'icon' | 'button'
 */
export function PrintOrderTicket({ orderId, variant = 'button' }) {
    const [isLoading, setIsLoading] = useState(false);

    const handlePrint = () => {
        setIsLoading(true);

        // Open thermal-optimized layout in new window
        const printWindow = window.open(
            route('orders.print-ticket', orderId),
            'print_ticket',
            'width=300,height=500'
        );

        // Trigger print when loaded
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
            <button
                onClick={handlePrint}
                disabled={isLoading}
                className="p-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors disabled:opacity-50"
                title="Imprimir ticket"
            >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z" />
                </svg>
            </button>
        );
    }

    return (
        <button
            onClick={handlePrint}
            disabled={isLoading}
            className="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
        >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z" />
            </svg>
            Ticket
        </button>
    );
}

// Default export for backwards compatibility
export default {
    DownloadReceiptButton,
    DownloadSalesReportButton,
    PrintOrderTicket,
};
