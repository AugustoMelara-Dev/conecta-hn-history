import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert";

export default function ErrorState({ title = "Ocurrio un error", message, actions, className }) {
    return (
        <Alert variant="destructive" className={className} role="alert">
            <AlertTitle>{title}</AlertTitle>
            {message ? <AlertDescription>{message}</AlertDescription> : null}
            {actions ? <div className="mt-3">{actions}</div> : null}
        </Alert>
    );
}
