import React from 'react';
import { Loader2, Wand2 } from 'lucide-react';
import { useAiGenerator } from '@/Hooks/useAiGenerator';
import { toast } from '@/Components/Toast';
import { Button } from '@/Components/ui/button';

export default function AiGenerateButton({ user, data, setData }) {
    const { generate, isGenerating } = useAiGenerator(user.id, (aiData) => {
        setData((previousData) => ({
            ...previousData,
            description: aiData.description,
            seo_title: aiData.seo_title,
            seo_description: aiData.seo_description,
            tags: aiData.tags,
            category_suggestion: aiData.category_suggestion,
        }));
    });

    const handleGenerate = (event) => {
        event.preventDefault();

        const missingFields = [];
        if (!data.business_name?.trim()) missingFields.push('Nombre del negocio');
        if (!data.activity?.trim()) missingFields.push('Actividad');
        if (!data.location?.trim()) missingFields.push('Ubicación');

        if (missingFields.length > 0) {
            toast.warning(`Completa los campos: ${missingFields.join(', ')}`);
            return;
        }

        generate({
            business_name: data.business_name.trim(),
            activity: data.activity.trim(),
            location: data.location.trim(),
            extra_details: data.description?.trim() || '',
        });
    };

    return (
        <Button
            type="button"
            variant="secondary"
            onClick={handleGenerate}
            disabled={isGenerating || !data.business_name}
            title="Optimizar perfil con IA"
        >
            {isGenerating ? (
                <>
                    <Loader2 data-icon="inline-start" className="animate-spin" />
                    <span>Analizando negocio...</span>
                </>
            ) : (
                <>
                    <Wand2 data-icon="inline-start" />
                    <span>Autocompletar con IA</span>
                </>
            )}
        </Button>
    );
}
