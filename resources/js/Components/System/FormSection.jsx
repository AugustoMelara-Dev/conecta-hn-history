import { FieldGroup } from "@/Components/ui/field";
import SectionShell from "@/Components/System/SectionShell";
import { cn } from "@/lib/utils";

export default function FormSection({ title, description, children, className, fieldGroupClassName }) {
    return (
        <SectionShell title={title} description={description} className={className}>
            <FieldGroup className={cn("gap-4", fieldGroupClassName)}>{children}</FieldGroup>
        </SectionShell>
    );
}
