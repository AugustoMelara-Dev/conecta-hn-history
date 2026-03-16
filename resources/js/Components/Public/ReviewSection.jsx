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
            <div className="bg-white rounded border border-gray-200 shadow-sm p-6 md:p-8">
                <h3 className="text-xl font-black uppercase tracking-wider text-black mb-6">Deja tu reseña</h3>

                {auth.user ? (
                    <form onSubmit={submit} className="space-y-6">
                        {recentlySuccessful && (
                            <div className="p-4 bg-gray-50 border border-black text-black font-bold tracking-wide rounded text-sm uppercase">
                                Tu reseña ha sido enviada y está pendiente de moderación. Gracias.
                            </div>
                        )}

                        {errors.error && (
                            <div className="p-4 bg-gray-50 border border-red-500 text-red-600 font-bold tracking-wide rounded text-sm uppercase">
                                {errors.error}
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-bold uppercase tracking-wider text-black mb-2">Calificación</label>
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
                                                    ? 'fill-black text-black'
                                                    : 'fill-gray-200 text-gray-200'
                                            } transition-colors`}
                                        />
                                    </button>
                                ))}
                            </div>
                            {errors.rating && <p className="text-red-600 font-bold uppercase text-xs mt-1">{errors.rating}</p>}
                        </div>

                        <div>
                            <label htmlFor="comment" className="block text-sm font-bold uppercase tracking-wider text-black mb-2">
                                Comentario <span className="text-gray-500 font-normal">(Opcional)</span>
                            </label>
                            <textarea
                                id="comment"
                                rows="4"
                                value={data.comment}
                                onChange={(e) => setData('comment', e.target.value)}
                                className="w-full bg-white border border-gray-300 rounded p-4 text-black placeholder:text-gray-400 focus:ring-2 focus:ring-black focus:border-transparent transition-all resize-none"
                                placeholder="¿Cómo fue tu experiencia con este negocio?"
                            />
                            {errors.comment && <p className="text-red-600 font-bold uppercase text-xs mt-1">{errors.comment}</p>}
                            {errors.tenant_id && <p className="text-red-600 font-bold uppercase text-xs mt-1">{errors.tenant_id}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-black text-white hover:bg-gray-800 px-6 py-3 rounded font-bold uppercase tracking-wider text-sm transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                            {processing ? 'Enviando...' : 'Publicar Reseña'}
                        </button>
                    </form>
                ) : (
                    <div className="text-center py-10 bg-gray-50 rounded border border-gray-200">
                        <MessageCircle className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                        <p className="text-gray-600 mb-6 font-medium">Debes iniciar sesión para compartir tu experiencia.</p>
                        <Link
                            href={route('login')}
                            className="inline-flex items-center justify-center bg-black text-white hover:bg-gray-800 px-6 py-3 rounded font-bold uppercase tracking-wider text-sm transition-colors"
                        >
                            Iniciar Sesión
                        </Link>
                    </div>
                )}
            </div>

            <div>
                <h3 className="text-xl font-black uppercase tracking-wider text-black mb-6">
                    Reseñas de Clientes <span className="text-gray-400 font-normal text-base">({safeReviews.length})</span>
                </h3>

                {safeReviews.length > 0 ? (
                    <div className="grid gap-6">
                        {safeReviews.map((review, idx) => (
                            <div key={review.id ?? idx} className="bg-white p-6 rounded border border-gray-200 shadow-sm">
                                <div className="flex items-start justify-between mb-4">
                                    <div className="flex items-center gap-4">
                                        <div className="w-12 h-12 bg-black rounded-full flex items-center justify-center text-white font-bold text-lg uppercase">
                                            {review.user?.name?.charAt(0) || 'U'}
                                        </div>
                                        <div>
                                            <p className="text-black font-bold tracking-tight">{review.user?.name || 'Usuario'}</p>
                                            <div className="flex gap-0.5 mt-1">
                                                {[1, 2, 3, 4, 5].map((star) => (
                                                    <Star
                                                        key={star}
                                                        className={`w-4 h-4 ${
                                                            star <= review.rating
                                                                ? 'fill-black text-black'
                                                                : 'fill-gray-200 text-gray-200'
                                                        }`}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                    <span className="text-xs font-bold uppercase tracking-wider text-gray-400">
                                        {getReviewDate(review)}
                                    </span>
                                </div>
                                {review.comment && (
                                    <p className="text-gray-600 leading-relaxed whitespace-pre-line mt-4">
                                        {review.comment}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12 bg-gray-50 border border-dashed border-gray-300 rounded">
                        <p className="text-gray-500 font-medium">Aún no hay reseñas publicadas. Sé el primero en opinar.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
