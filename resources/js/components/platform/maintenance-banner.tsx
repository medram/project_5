import { usePage } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export default function MaintenanceBanner() {
    const banner = usePage().props.platform?.maintenanceBanner;

    if (!banner) {
        return null;
    }

    return (
        <div className="relative z-50 px-3 pt-3">
            <Alert className="border-warning/30 bg-warning-soft text-warning-soft-foreground">
                <AlertDescription className="text-warning-soft-foreground">
                    <AlertTitle>Platform notice</AlertTitle>
                    {banner}
                </AlertDescription>
            </Alert>
        </div>
    );
}
