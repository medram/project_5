import { router, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { WorkspaceContext } from '@/types/workspaces';
import { useSidebar } from '@/components/ui/sidebar';
export function WorkspaceSwitcher() {
    const { workspace } = usePage<{ workspace?: WorkspaceContext }>().props;
    const { isMobile, setOpenMobile } = useSidebar();
    const [open, setOpen] = useState(false);
    const [creating, setCreating] = useState(false);
    const form = useForm({ name: '' });
    if (!workspace?.current) return null;

    function switchWorkspace(id: number) {
        if (isMobile) {
            setOpenMobile(false);
        }

        router.post(`/workspaces/${id}/switch`, {}, { preserveScroll: true });
    }

    function createWorkspace(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/workspaces', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setCreating(false);
                setOpen(false);
            },
        });
    }

    return (
        <>
            <div className="space-y-2 px-2">
                <label htmlFor="workspace-switch" className="sr-only">
                    Current workspace
                </label>
                <div className="flex min-w-0 items-center gap-1">
                    <select
                        id="workspace-switch"
                        aria-label="Switch workspace"
                        value={workspace.current.id}
                        onChange={(event) =>
                            switchWorkspace(Number(event.target.value))
                        }
                        className="h-11 min-w-0 flex-1 rounded-md border border-sidebar-border bg-sidebar px-2 text-sm font-medium text-sidebar-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {workspace.memberships.map((membership) => (
                            <option key={membership.id} value={membership.id}>
                                {membership.name} · {membership.role}
                            </option>
                        ))}
                    </select>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="size-11 shrink-0"
                        aria-label="Manage workspaces"
                        onClick={() => setOpen(true)}
                    >
                        <ChevronsUpDown className="size-4" />
                    </Button>
                </div>
                <p className="truncate px-1 text-xs text-muted-foreground">
                    {workspace.current.role} access
                </p>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Workspaces</DialogTitle>
                        <DialogDescription>
                            Switch to a workspace or create a new one.
                        </DialogDescription>
                    </DialogHeader>
                    <ul className="space-y-1" aria-label="Your workspaces">
                        {workspace.memberships.map((membership) => (
                            <li key={membership.id}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        switchWorkspace(membership.id);
                                        setOpen(false);
                                    }}
                                    className="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-3 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span className="min-w-0">
                                        <span className="block truncate font-medium">
                                            {membership.name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {membership.role}
                                        </span>
                                    </span>
                                    {membership.id === workspace.current.id && (
                                        <Check
                                            className="size-4 shrink-0"
                                            aria-label="Current workspace"
                                        />
                                    )}
                                </button>
                            </li>
                        ))}
                    </ul>
                    {!creating ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 w-full"
                            onClick={() => setCreating(true)}
                        >
                            <Plus className="size-4" /> Create workspace
                        </Button>
                    ) : (
                        <form
                            onSubmit={createWorkspace}
                            className="space-y-3 border-t pt-4"
                        >
                            <div className="space-y-2">
                                <Label htmlFor="new-workspace-name">
                                    Workspace name
                                </Label>
                                <Input
                                    id="new-workspace-name"
                                    className="min-h-11"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    autoFocus
                                    aria-invalid={!!form.errors.name}
                                />
                                {form.errors.name && (
                                    <p
                                        className="text-sm text-destructive"
                                        role="alert"
                                    >
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="min-h-11"
                                    onClick={() => {
                                        setCreating(false);
                                        form.reset();
                                        form.clearErrors();
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    className="min-h-11"
                                    disabled={form.processing}
                                >
                                    {form.processing
                                        ? 'Creating…'
                                        : 'Create workspace'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
