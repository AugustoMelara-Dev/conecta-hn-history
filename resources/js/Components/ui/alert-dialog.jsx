import {
    Dialog as AlertDialog,
    DialogClose as AlertDialogCancel,
    DialogContent,
    DialogDescription as AlertDialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle as AlertDialogTitle,
    DialogTrigger as AlertDialogTrigger,
} from "@/Components/ui/dialog"
import { Button } from "@/Components/ui/button"
import { cn } from "@/lib/utils"

function AlertDialogContent({ className, ...props }) {
    return <DialogContent role="alertdialog" className={cn("sm:max-w-md", className)} {...props} />
}

function AlertDialogHeader(props) {
    return <DialogHeader {...props} />
}

function AlertDialogFooter(props) {
    return <DialogFooter {...props} />
}

function AlertDialogAction({ className, ...props }) {
    return <Button className={className} {...props} />
}

export {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
}
