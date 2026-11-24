import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { CustomDomain } from '@/types/custom-domains';

export function CustomDomainEditor({ domain, onClose }: { domain: CustomDomain; onClose: () => void }) {
    const form = useForm({ hostname: domain.hostname, confirmation: '' });
    const [checking, setChecking] = useState(false);
    const [reissuing, setReissuing] = useState(false);
    const baseUrl = `/admin/custom-domains/${domain.id}`;

    function submit(event: FormEvent) {
        event.preventDefault();
        form.patch(baseUrl, { preserveScroll: true });
    }

    function action(name: 'verify' | 'reissue') {
        const setWorking = name === 'verify' ? setChecking : setReissuing;
        router.post(`${baseUrl}/${name}`, {}, {
            preserveScroll: true,
            onStart: () => setWorking(true),
            onFinish: () => setWorking(false),
        });
    }

    return (
        <Dialog open onOpenChange={(open) => { if (!open) onClose(); }}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Edit custom domain</DialogTitle>
                    <DialogDescription>
                        Changing {domain.hostname} disables its links until the new hostname passes DNS and HTTPS verification.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-3 text-sm">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">Verification status</span>
                        <Badge variant="secondary">{domain.status.replaceAll('_', ' ')}</Badge>
                    </div>
                    {domain.failureReason && <p className="text-destructive">{domain.failureReason}</p>}
                    <div className="rounded-lg border bg-muted/20 p-3">
                        <p className="font-medium">DNS TXT record</p>
                        <p className="mt-1 break-all font-mono text-xs">{domain.txtHost}</p>
                        <p className="mt-1 break-all font-mono text-xs">{domain.txtValue}</p>
                    </div>
                    <div className="rounded-lg border bg-muted/20 p-3">
                        <p className="font-medium">HTTPS challenge</p>
                        <p className="mt-1 break-all text-xs">Serve <span className="font-mono">{domain.verificationToken}</span> at <span className="font-mono">{domain.challengeUrl}</span></p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" disabled={checking || reissuing || form.processing} onClick={() => action('verify')}>
                            {checking ? 'Checking…' : 'Check verification'}
                        </Button>
                        <Button type="button" variant="outline" disabled={checking || reissuing || form.processing} onClick={() => action('reissue')}>
                            {reissuing ? 'Reissuing…' : 'Reissue token'}
                        </Button>
                    </div>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <FieldGroup>
                        <Field data-invalid={!!form.errors.hostname}>
                            <FieldLabel htmlFor="domain-hostname">Hostname</FieldLabel>
                            <Input id="domain-hostname" value={form.data.hostname} onChange={(event) => form.setData('hostname', event.target.value)} aria-invalid={!!form.errors.hostname} />
                            <FieldError>{form.errors.hostname}</FieldError>
                        </Field>
                        <Field data-invalid={!!form.errors.confirmation}>
                            <FieldLabel htmlFor="domain-confirmation">Confirm current hostname</FieldLabel>
                            <FieldDescription>Type {domain.hostname} to confirm this change.</FieldDescription>
                            <Input id="domain-confirmation" value={form.data.confirmation} onChange={(event) => form.setData('confirmation', event.target.value)} aria-invalid={!!form.errors.confirmation} />
                            <FieldError>{form.errors.confirmation}</FieldError>
                        </Field>
                    </FieldGroup>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
                        <Button type="submit" disabled={form.processing || checking || reissuing}>{form.processing ? 'Saving…' : 'Save hostname'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
