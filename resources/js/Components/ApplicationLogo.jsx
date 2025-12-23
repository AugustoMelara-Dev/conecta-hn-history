import { Link } from '@inertiajs/react';

export default function ApplicationLogo({ className = '', showText = true }) {
    return (
        <Link href="/" className="flex items-center gap-2 group">
            {/* Logo Icon: "C" in rounded square */}
            <div className="w-10 h-10 bg-sky-600 rounded-xl flex items-center justify-center text-white shadow-lg group-hover:scale-105 transition-transform">
                <span className="font-bold text-xl">C</span>
            </div>
            {/* Logo Text (optional) */}
            {showText && (
                <span className={`font-bold text-xl tracking-tight text-slate-900 dark:text-white ${className}`}>
                    ConectaHN
                </span>
            )}
        </Link>
    );
}
