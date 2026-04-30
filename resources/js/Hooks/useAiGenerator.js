import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { toast } from '@/Components/Toast/index.js';

/**
 * useAiGenerator - Optimistic UI & WebSocket Orchestrator
 *
 * Encapsulates the complexity of "Fire & Forget" async processing.
 * Manages the HTTP handshake (202 Accepted) and the Event Listener (WebSocket).
 *
 * @param {number} userId - The owner ID for the private channel subscription.
 * @param {function} onSuccess - Callback executed with the hydrated payload (AI Data).
 * @returns {object} { generate, isGenerating }
 */
export function useAiGenerator(userId, onSuccess) {
    const [isGenerating, setIsGenerating] = useState(false);

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 1: HTTP Trigger (The Handshake)
    // ═══════════════════════════════════════════════════════════════════════
    const generate = useCallback(async (payload) => {
        if (isGenerating) return; // Prevent double-clicks

        setIsGenerating(true);
        toast.info("La IA está trabajando en tu perfil...");

        try {
            // Non-blocking interaction (Expects 202 Accepted)
            const response = await axios.post('/api/ai/generate', payload);

            if (response.status === 202) {
                // Handshake success: Now we wait for the socket...
                // State remains true until socket event arrives.
            } else {
                // Unexpected success code? Just resolve.
                setIsGenerating(false);
            }
        } catch (error) {
            setIsGenerating(false);

            const status = error.response?.status;

            if (status === 429) {
                toast.error("Límite excedido. Por favor espera antes de intentar de nuevo.");
            } else if (status === 422) {
                // Validation Error - Extract first error message
                const errors = error.response?.data?.errors;
                const firstError = errors ? Object.values(errors)[0]?.[0] : null;
                toast.warning(firstError || "Por favor verifica los datos ingresados.");
            } else if (status === 403) {
                toast.error("No tienes permiso para realizar esta acción.");
            } else {
                toast.error("Error al conectar con la IA. Intenta más tarde.");
                console.error("AI Generation Error:", error);
            }
        }
    }, [isGenerating, userId]);

    // ═══════════════════════════════════════════════════════════════════════
    // PHASE 2: WebSocket Listener (The Confirmation)
    // ═══════════════════════════════════════════════════════════════════════
    useEffect(() => {
        // Efficiency Rule: Only listen if we are actually waiting for data.
        if (!isGenerating || !userId) return;

        console.log(`[useAiGenerator] Listening on private-user.${userId} for result...`);

        // Subscription
        const channel = window.Echo.private(`user.${userId}`);

        channel.listen('.business.profile.updated', (e) => {
            console.log("[useAiGenerator] Payload Received!", e);

            // 1. Execute Domain Logic (Mutation)
            // Defensive coding: Accept 'business' wrapper or direct payload
            const payload = e.business || e.tenant || e;

            onSuccess(payload);

            // 2. Resolve UI State
            setIsGenerating(false);
            toast.success("Contenido generado correctamente");
        });

        // Failure Handler (Zombie State Protection)
        channel.listen('.business.profile.failed', (e) => {
            console.error("[useAiGenerator] Failure Received", e);
            setIsGenerating(false);
            toast.error(e.error || "Ocurrió un error al procesar tu solicitud.");
        });

        // ═══════════════════════════════════════════════════════════════════════
        // PHASE 3: Lifecycle Cleanup (Memory Leak Prevention)
        // ═══════════════════════════════════════════════════════════════════════
        return () => {
            console.log(`[useAiGenerator] Cleaning up listener...`);
            // Stop listening to the specific event to avoid detached listeners
            channel.stopListening('.business.profile.updated');
            channel.stopListening('.business.profile.failed');
            // Optimally, we might not want to leave the whole user channel if utilized elsewhere,
            // but stopListening is safe.
        };
    }, [isGenerating, userId, onSuccess]);

    return { generate, isGenerating };
}
