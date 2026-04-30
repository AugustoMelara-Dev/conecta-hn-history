import React from 'react';
import { useAiGenerator } from '@/Hooks/useAiGenerator';
import { Wand2, Loader2 } from 'lucide-react';
import { toast } from '@/Components/Toast/index.js';

/**
 * AiGenerateButton - The Magic Wand
 *
 * A self-contained component that triggers AI optimization.
 * Uses Optimistic UI patterns to handle async processing.
 *
 * @param {Object} props
 * @param {Object} props.user - Authenticated user (required for private channel).
 * @param {Object} props.data - Current form data (must contain business_name, activity, location).
 * @param {Function} props.setData - Inertia useForm setter for reactive updates.
 */
export default function AiGenerateButton({ user, data, setData }) {
    // ═══════════════════════════════════════════════════════════════════════
    // HOOK INTEGRATION: The Brains
    // ═══════════════════════════════════════════════════════════════════════
    const { generate, isGenerating } = useAiGenerator(user.id, (aiData) => {
        // Reactive Mutation: Auto-fill form fields when AI finishes
        // Preserves existing data, only overwrites target fields
        setData(previousData => ({
            ...previousData,
            description: aiData.description,
            seo_title: aiData.seo_title,
            seo_description: aiData.seo_description,
            tags: aiData.tags,
            category_suggestion: aiData.category_suggestion
        }));
    });

    // ═══════════════════════════════════════════════════════════════════════
    // HANDLER with Client-Side Validation
    // ═══════════════════════════════════════════════════════════════════════
    const handleGenerate = (e) => {
        e.preventDefault(); // Prevent form submission

        // Client-side validation: Check required fields BEFORE API call
        const missingFields = [];
        if (!data.business_name?.trim()) missingFields.push('Nombre del negocio');
        if (!data.activity?.trim()) missingFields.push('Actividad');
        if (!data.location?.trim()) missingFields.push('Ubicación');

        if (missingFields.length > 0) {
            toast.warning(`Completa los campos: ${missingFields.join(', ')}`);
            return; // Abort - don't waste API call
        }

        const payload = {
            business_name: data.business_name.trim(),
            activity: data.activity.trim(),
            location: data.location.trim(),
            extra_details: data.description?.trim() || '' // Use current description as context
        };

        generate(payload);
    };

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER: Optimistic UI
    // ═══════════════════════════════════════════════════════════════════════
    return (
        <button
            type="button" // Important: Don't submit parent form
            onClick={handleGenerate}
            disabled={isGenerating || !data.business_name} // Disable if processing or missing key input
            className={`
                flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all
                ${isGenerating
                    ? 'bg-purple-100 text-purple-700 cursor-not-allowed opacity-80'
                    : 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white hover:shadow-lg hover:scale-105 active:scale-95'
                }
            `}
            title="Optimizar Perfil con IA (GPT-4o)"
        >
            {isGenerating ? (
                <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span>Analizando negocio...</span>
                </>
            ) : (
                <>
                    <Wand2 className="w-4 h-4" />
                    <span>Autocompletar con IA</span>
                </>
            )}
        </button>
    );
}
