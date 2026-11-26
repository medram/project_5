import { router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronsUpDown,
    Plus,
    Settings2,
} from 'lucide-react';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSidebar } from '@/components/ui/sidebar';
export function WorkspaceSwitcher() {
    const { workspace } = usePage().props;
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
            <div className="px-1 group-data-[collapsible=icon]:hidden">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            aria-label={`Switch workspace. Current workspace: ${workspace.current.name}`}
                            title={`Switch workspace: ${workspace.current.name}`}
                            className="flex h-8 w-full min-w-0 items-center gap-2 rounded-lg px-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring/40 focus-visible:outline-none"
                        >
                            <span className="truncate">
                                {workspace.current.name}
                            </span>
                            <ChevronsUpDown
                                className="ml-auto size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        side="bottom"
                        className="w-60 rounded-xl"
                    >
                        <DropdownMenuLabel className="text-xs font-medium text-muted-foreground">
                            Workspace
                        </DropdownMenuLabel>
                        {workspace.memberships.map((membership) => (
                            <DropdownMenuItem
                                key={membership.id}
                                className="gap-2"
                                onSelect={() => switchWorkspace(membership.id)}
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {membership.name}
                                </span>
                                {membership.id === workspace.current.id && (
                                    <Check
                                        className="size-4 shrink-0"
                                        aria-label="Current workspace"
                                    />
                                )}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => setOpen(true)}>
                            <Settings2 aria-hidden="true" />
                            Manage workspaces
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
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
