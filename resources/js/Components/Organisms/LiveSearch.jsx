import { useState, useEffect, useRef } from 'react';
import { Input } from '@/Components/ui/input';
import { Search, Loader2, Store, ShoppingBag } from 'lucide-react';
import { Link, router } from '@inertiajs/react';
import { useDebounce } from '@/Hooks/useDebounce'; // Assuming you have this or I'll implement simple debounce
import axios from 'axios';

export default function LiveSearch() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState({ tenants: [], products: [] });
    const [loading, setLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const wrapperRef = useRef(null);

    // Simple debounce implementation inside effect if hook not available
    useEffect(() => {
        const delayDebounceFn = setTimeout(async () => {
            if (query.length >= 2) {
                setLoading(true);
                try {
                    const response = await axios.get(route('api.search'), { params: { query } });
                    setResults(response.data);
                    setIsOpen(true);
                } catch (error) {
                    console.error("Search failed", error);
                } finally {
                    setLoading(false);
                }
            } else {
                setResults({ tenants: [], products: [] });
                setIsOpen(false);
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [query]);

    // Close on click outside
    useEffect(() => {
        function handleClickOutside(event) {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () => {
            document.removeEventListener("mousedown", handleClickOutside);
        };
    }, [wrapperRef]);

    const handleSearchSubmit = (e) => {
        e.preventDefault();
        if (query) {
            window.location.href = route('directory.index', { search: query });
            setIsOpen(false);
        }
    };

    return (
        <div ref={wrapperRef} className="relative w-full">
            <form onSubmit={handleSearchSubmit} className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    type="search"
                    placeholder="Buscar negocios, productos..."
                    className="pl-10 w-full h-10 bg-muted/50 border-muted-foreground/20 focus:bg-background transition-colors"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onFocus={() => { if (query.length >= 2) setIsOpen(true); }}
                />
                {loading && (
                    <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground animate-spin" />
                )}
            </form>

            {isOpen && (results.tenants.length > 0 || results.products.length > 0) && (
                <div className="absolute top-full left-0 right-0 mt-2 bg-popover border border-border rounded-xl shadow-2xl overflow-hidden z-50 animate-in fade-in zoom-in-95 duration-200">
                    <div className="max-h-[80vh] overflow-y-auto">
                        {results.tenants.length > 0 && (
                            <div className="p-2">
                                <h3 className="text-xs font-semibold text-muted-foreground px-2 py-1 mb-1 uppercase tracking-wider">Negocios</h3>
                                {results.tenants.map((tenant) => (
                                    <Link
                                        key={tenant.id}
                                        href={route('tenant.show', tenant.slug)}
                                        className="flex items-center gap-3 p-2 rounded-lg hover:bg-muted/50 transition-colors group"
                                        onClick={() => setIsOpen(false)}
                                    >
                                        <div className="w-10 h-10 rounded-md bg-muted overflow-hidden shrink-0 border border-border">
                                            {tenant.logo_path ? (
                                                <img src={`/storage/${tenant.logo_path}`} alt={tenant.name} className="w-full h-full object-cover" />
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center bg-primary/10 text-primary font-bold text-xs">
                                                    {tenant.name.substring(0, 2).toUpperCase()}
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">{tenant.name}</p>
                                            <p className="text-xs text-muted-foreground truncate">{tenant.category?.name}</p>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}

                        {results.tenants.length > 0 && results.products.length > 0 && <div className="h-px bg-border mx-2 my-1"></div>}

                        {results.products.length > 0 && (
                            <div className="p-2">
                                <h3 className="text-xs font-semibold text-muted-foreground px-2 py-1 mb-1 uppercase tracking-wider">Productos</h3>
                                {results.products.map((product) => (
                                    <Link
                                        key={product.id}
                                        href={route('tenant.show', product.tenant.slug)}
                                        className="flex items-center gap-3 p-2 rounded-lg hover:bg-muted/50 transition-colors group"
                                        onClick={() => setIsOpen(false)}
                                    >
                                        <div className="w-10 h-10 rounded-md bg-muted overflow-hidden shrink-0 border border-border">
                                            {product.image_path ? (
                                                <img src={`/storage/${product.image_path}`} alt={product.name} className="w-full h-full object-cover" />
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center bg-secondary text-secondary-foreground">
                                                    <ShoppingBag className="w-5 h-5" />
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-foreground truncate group-hover:text-primary transition-colors">{product.name}</p>
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs text-muted-foreground truncate">{product.tenant.name}</span>
                                                <span className="text-xs font-bold text-primary">L. {product.price}</span>
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="p-2 bg-muted/30 border-t border-border text-center">
                        <button
                            onClick={handleSearchSubmit}
                            className="text-xs text-primary hover:underline font-medium"
                        >
                            Ver todos los resultados para "{query}"
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
