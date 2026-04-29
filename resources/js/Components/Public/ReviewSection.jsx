import { useState } from 'react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { MessageCircle, Star } from 'lucide-react';
import { PUBLIC_DISPLAY_FONT, PUBLIC_LABEL_FONT } from '@/lib/publicTypography';

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

    const submit = (event) => {
        event.preventDefault();
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
            <div className="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 md:p-8">
                <h3 className="mb-6 text-2xl font-bold tracking-tight text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                    Deja tu resena
                </h3>

                {auth.user ? (
                    <form onSubmit={submit} className="space-y-6">
                        {recentlySuccessful ? (
                            <div className="rounded-[1.2rem] bg-slate-100 p-4 text-sm font-semibold text-slate-950">
                                Tu resena fue enviada y esta pendiente de moderacion. Gracias.
                            </div>
                        ) : null}

                        {errors.error ? (
                            <div className="rounded-[1.2rem] border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">
                                {errors.error}
                            </div>
                        ) : null}

                        <div>
                            <label className="mb-2 block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                Calificacion
                            </label>
                            <div className="flex gap-1">
                                {[1, 2, 3, 4, 5].map((star) => (
                                    <button
                                        key={star}
                                        type="button"
                                        onClick={() => setData('rating', star)}
                                        onMouseEnter={() => setHoveredStar(star)}
                                        onMouseLeave={() => setHoveredStar(0)}
                                        className="transition-transform hover:scale-110 focus:outline-none"
                                    >
                                        <Star
                                            className={`h-8 w-8 ${
                                                star <= (hoveredStar || data.rating)
                                                    ? 'fill-slate-950 text-slate-950'
                                                    : 'fill-slate-200 text-slate-200'
                                            } transition-colors`}
                                        />
                                    </button>
                                ))}
                            </div>
                            {errors.rating ? <p className="mt-1 text-xs font-semibold text-rose-600">{errors.rating}</p> : null}
                        </div>

                        <div>
                            <label htmlFor="comment" className="mb-2 block text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                Comentario <span className="font-medium normal-case tracking-normal text-slate-400">(opcional)</span>
                            </label>
                            <textarea
                                id="comment"
                                rows="4"
                                value={data.comment}
                                onChange={(event) => setData('comment', event.target.value)}
                                className="w-full resize-none rounded-[1.2rem] border border-slate-200 bg-slate-50 p-4 text-slate-950 placeholder:text-slate-400 transition-all focus:border-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-200"
                                placeholder="Como fue tu experiencia con este negocio?"
                            />
                            {errors.comment ? <p className="mt-1 text-xs font-semibold text-rose-600">{errors.comment}</p> : null}
                            {errors.tenant_id ? <p className="mt-1 text-xs font-semibold text-rose-600">{errors.tenant_id}</p> : null}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="flex items-center justify-center gap-2 rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-slate-800 disabled:opacity-50"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            {processing ? 'Enviando...' : 'Publicar resena'}
                        </button>
                    </form>
                ) : (
                    <div className="rounded-[1.4rem] border border-slate-200 bg-slate-50 py-10 text-center">
                        <MessageCircle className="mx-auto mb-4 h-12 w-12 text-slate-300" />
                        <p className="mb-6 font-medium text-slate-600">Debes iniciar sesion para compartir tu experiencia.</p>
                        <Link
                            href={route('login')}
                            className="inline-flex items-center justify-center rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold uppercase tracking-[0.16em] text-white transition-colors hover:bg-slate-800"
                            style={{ fontFamily: PUBLIC_LABEL_FONT }}
                        >
                            Iniciar sesión
                        </Link>
                    </div>
                )}
            </div>

            <div>
                <h3 className="mb-6 text-2xl font-bold tracking-tight text-slate-950" style={{ fontFamily: PUBLIC_DISPLAY_FONT }}>
                    Resenas de clientes <span className="text-base font-medium text-slate-400">({safeReviews.length})</span>
                </h3>

                {safeReviews.length > 0 ? (
                    <div className="grid gap-6">
                        {safeReviews.map((review, index) => (
                            <div key={review.id ?? index} className="rounded-[1.5rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5">
                                <div className="mb-4 flex items-start justify-between">
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-slate-950 text-lg font-bold uppercase text-white">
                                            {review.user?.name?.charAt(0) || 'U'}
                                        </div>
                                        <div>
                                            <p className="font-bold tracking-tight text-slate-950">{review.user?.name || 'Usuario'}</p>
                                            <div className="mt-1 flex gap-0.5">
                                                {[1, 2, 3, 4, 5].map((star) => (
                                                    <Star
                                                        key={star}
                                                        className={`h-4 w-4 ${
                                                            star <= review.rating
                                                                ? 'fill-slate-950 text-slate-950'
                                                                : 'fill-slate-200 text-slate-200'
                                                        }`}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    </div>

                                    <span className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400" style={{ fontFamily: PUBLIC_LABEL_FONT }}>
                                        {getReviewDate(review)}
                                    </span>
                                </div>

                                {review.comment ? (
                                    <p className="mt-4 whitespace-pre-line leading-relaxed text-slate-600">
                                        {review.comment}
                                    </p>
                                ) : null}
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-[1.4rem] border border-dashed border-slate-300 bg-slate-50 py-12 text-center">
                        <p className="font-medium text-slate-500">Aun no hay resenas publicadas. Se el primero en opinar.</p>
                    </div>
                )}
            </div>
        </div>
    );
}
