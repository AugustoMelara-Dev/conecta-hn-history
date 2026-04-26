export default function SupportDiagnosticCard({ diagnostic, className = '' }) {
    if (!diagnostic) {
        return null;
    }

    return (
        <div className={`rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left text-sm text-slate-700 ${className}`}>
            <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                Estado del sistema
            </p>
            <div className="mt-3 grid gap-3 md:grid-cols-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Estado</p>
                    <p className="mt-1 font-semibold text-slate-950">{diagnostic.status}</p>
                </div>
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Causa probable</p>
                    <p className="mt-1">{diagnostic.probable_cause}</p>
                </div>
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Accion recomendada</p>
                    <p className="mt-1">{diagnostic.recommended_action}</p>
                </div>
            </div>
            {diagnostic.trace_id ? (
                <p className="mt-3 rounded-full bg-white px-3 py-2 font-mono text-xs text-slate-500">
                    REF soporte: {diagnostic.trace_id}
                </p>
            ) : null}
        </div>
    );
}
