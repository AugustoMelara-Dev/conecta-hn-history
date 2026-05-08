import { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { toast } from '@/Components/Toast';

const realtimeEnabled = String(import.meta.env.VITE_REVERB_ENABLED ?? '').toLowerCase().trim() === 'true';

export function useAiGenerator(userId, onSuccess) {
    const [isGenerating, setIsGenerating] = useState(false);

    const generate = useCallback(async (payload) => {
        if (isGenerating) return;

        if (!realtimeEnabled || typeof window === 'undefined' || typeof window.Echo === 'undefined') {
            toast.warning('La generación en tiempo real no está disponible en este entorno.');
            return;
        }

        setIsGenerating(true);
        toast.info('La IA está trabajando en tu perfil...');

        try {
            const response = await axios.post('/api/ai/generate', payload);

            if (response.status !== 202) {
                setIsGenerating(false);
            }
        } catch (error) {
            setIsGenerating(false);

            const status = error.response?.status;

            if (status === 429) {
                toast.error('Límite excedido. Por favor espera antes de intentar de nuevo.');
            } else if (status === 422) {
                const errors = error.response?.data?.errors;
                const firstError = errors ? Object.values(errors)[0]?.[0] : null;
                toast.warning(firstError || 'Por favor verifica los datos ingresados.');
            } else if (status === 403) {
                toast.error('No tienes permiso para realizar esta acción.');
            } else {
                toast.error('Error al conectar con la IA. Intenta más tarde.');
                console.error('AI Generation Error:', error);
            }
        }
    }, [isGenerating]);

    useEffect(() => {
        if (!isGenerating || !userId) return;
        if (!realtimeEnabled || typeof window === 'undefined' || typeof window.Echo === 'undefined') {
            setIsGenerating(false);
            return;
        }

        console.log(`[useAiGenerator] Listening on private-user.${userId} for result...`);

        const channel = window.Echo.private(`user.${userId}`);

        channel.listen('.business.profile.updated', (event) => {
            console.log('[useAiGenerator] Payload received', event);

            const payload = event.business || event.tenant || event;

            onSuccess(payload);
            setIsGenerating(false);
            toast.success('Contenido generado correctamente');
        });

        channel.listen('.business.profile.failed', (event) => {
            console.error('[useAiGenerator] Failure received', event);
            setIsGenerating(false);
            toast.error(event.error || 'Ocurrió un error al procesar tu solicitud.');
        });

        return () => {
            console.log('[useAiGenerator] Cleaning up listener...');
            channel.stopListening('.business.profile.updated');
            channel.stopListening('.business.profile.failed');
        };
    }, [isGenerating, userId, onSuccess]);

    return { generate, isGenerating };
}
