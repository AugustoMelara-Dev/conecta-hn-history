import { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { MessageCircle, Star } from 'lucide-react';

export default function ReviewSection({ tenant, reviews = [] }) {
    const { auth } = usePage().props;
    const [hoveredStar, setHoveredStar] = useState(0);
    const safeReviews = Array.isArray(reviews) ? reviews : [];

    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        tenant_id: tenant.id,
        product_id: '',
        rating: 5,
        comment: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('reviews.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset('comment');
                setData('rating', 5);
            },
        });
    };

    const getReviewDate = (review) => {
        if (!review.created_at) {
            return '';
        }

        const parsed = new Date(review.created_at);
        if (Number.isNaN(parsed.getTime())) {
            return '';
        }

        return parsed.toLocaleDateString('es-HN', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <div className="space-y-12">
            <div className="bg-slate-900/50 rounded-2xl border border-white/10 p-6 md:p-8">
                <h3 className="text-xl font-bold text-white mb-6">Deja tu resena</h3>

                {auth.user ? (
                    <form onSubmit={submit} className="space-y-6">
                        {recentlySuccessful && (
                            <div className="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-sm">
                                Tu resena ha sido enviada y esta pendiente de moderacion. Gracias.
                            </div>
                        )}

                        {errors.error && (
                            <div className="p-4 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl text-sm">
                                {errors.error}
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-slate-300 mb-2">Calificacion</label>
                            <div className="flex gap-1">
                                {[1, 2, 3, 4, 5].map((star) => (
                                    <button
                                        key={star}
                                        type="button"
                                        onClick={() => setData('rating', star)}
                                        onMouseEnter={() => setHoveredStar(star)}
                                        onMouseLeave={() => setHoveredStar(0)}
                                        className="focus:outline-none transition-transform hover:scale-110"
                                    >
                                        <Star
                                            className={`w-8 h-8 ${
                                                star <= (hoveredStar || data.rating)
                                                    ? 'fill-amber-400 text-amber-400'
                                                    : 'fill-slate-800 text-slate-700'
                                            } transition-colors`}
                                        />
                                    </button>
                                ))}
                            </div>
                            {errors.rating && <p className="text-red-400 text-xs mt-1">{errors.rating}</p>}
                        </div>

                        <div>
                            <label htmlFor="comment" className="block text-sm font-medium text-slate-300 mb-2">
                                Comentario <span className="text-slate-500 font-normal">(Opcional)</span>
                            </label>
                            <textarea
                                id="comment"
                                rows="4"
                                value={data.comment}
                                onChange={(e) => setData('comment', e.target.value)}
                                className="w-full bg-slate-900 border border-white/10 rounded-xl p-4 text-white placeholder:text-slate-600 focus:ring-2 focus:ring-white/20 focus:border-white/20 transition-all resize-none"
                                placeholder="Como fue tu experiencia con este negocio?"
                            />
                            {errors.comment && <p className="text-red-400 text-xs mt-1">{errors.comment}</p>}
                            {errors.tenant_id && <p className="text-red-400 text-xs mt-1">{errors.tenant_id}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-white text-black hover:bg-slate-200 px-6 py-3 rounded-xl font-semibold transition-colors disabled:opacity-50 flex items-center gap-2"
                        >
                            {processing ? 'Enviando...' : 'Publicar Resena'}
                        </button>
                    </form>
                ) : (
                    <div className="text-center py-8 bg-slate-900 rounded-xl border border-white/5">
                        <MessageCircle className="w-10 h-10 text-slate-600 mx-auto mb-3" />
                        <p className="text-slate-400 mb-4">Debes iniciar sesion para compartir tu experiencia.</p>
                        <Link
                            href={route('login')}
                            className="inline-flex items-center justify-center bg-white text-black hover:bg-slate-200 px-6 py-2.5 rounded-lg font-medium transition-colors"
                        >
                            Iniciar Sesion
                        </Link>
                    </div>
                )}
            </div>

            <div>
                <h3 className="text-xl font-bold text-white mb-6">
                    Resenas de Clientes <span className="text-slate-500 font-normal text-base">({safeReviews.length})</span>
                </h3>

                {safeReviews.length > 0 ? (
                    <div className="grid gap-4">
                        {safeReviews.map((review, idx) => (
                            <div key={review.id ?? idx} className="bg-slate-900/50 p-6 rounded-2xl border border-white/5">
                                <div className="flex items-start justify-between mb-4">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 bg-slate-800 rounded-full flex items-center justify-center text-slate-300 font-bold">
                                            {review.user?.name?.charAt(0) || 'U'}
                                        </div>
                                        <div>
                                            <p className="text-white font-medium">{review.user?.name || 'Usuario'}</p>
                                            <div className="flex gap-0.5 mt-1">
                                                {[1, 2, 3, 4, 5].map((star) => (
                                                    <Star
                                                        key={star}
                                                        className={`w-3.5 h-3.5 ${
                                                            star <= review.rating
                                                                ? 'fill-amber-400 text-amber-400'
                                                                : 'fill-slate-800 text-slate-700'
                                                        }`}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                    <span className="text-xs text-slate-500">
                                        {getReviewDate(review)}
                                    </span>
                                </div>
                                {review.comment && (
                                    <p className="text-slate-300 text-sm leading-relaxed whitespace-pre-line">
                                        {review.comment}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12 border border-dashed border-white/10 rounded-2xl">
                        <p className="text-slate-500">Aun no hay resenas publicadas. Se el primero en opinar.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
