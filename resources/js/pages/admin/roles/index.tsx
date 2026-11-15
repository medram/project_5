import { Head, Link } from '@inertiajs/react';
import { Check, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import users from '@/routes/admin/users';
import type { AdminRolesPageProps } from '@/types/admin';

export default function AdminRolesIndex({ roles }: AdminRolesPageProps) {
    return (
        <>
            <Head title="Roles — Administration" />
            <div className="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-5 px-5 pt-4 pb-10">
                <header className="space-y-1">
                    <h1 className="text-2xl leading-8 font-semibold tracking-[-0.02em]">
                        Roles and permissions
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Both platform roles are seeded and immutable. This page
                        is a read-only permission and member matrix; assign or
                        remove a role from an account’s detail page.
                    </p>
                </header>

                <div className="grid gap-4 xl:grid-cols-2">
                    {roles.map((role) => (
                        <Card key={role.code} className="overflow-hidden">
                            <CardHeader className="gap-2">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>{role.name}</CardTitle>
                                        <CardDescription>
                                            <code className="text-xs">
                                                {role.code}
                                            </code>
                                        </CardDescription>
                                    </div>
                                    <Badge variant="outline">Read only</Badge>
                                </div>
                                <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                                    <span className="inline-flex items-center gap-1">
                                        <Check
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {role.permissions.length} permissions
                                    </span>
                                    <span className="inline-flex items-center gap-1">
                                        <Users
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {role.usersCount ?? 0}{' '}
                                        {role.usersCount === 1
                                            ? 'member'
                                            : 'members'}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                <section>
                                    <h2 className="text-sm font-medium">
                                        Permission matrix
                                    </h2>
                                    <ul className="mt-2 grid gap-1.5 text-sm sm:grid-cols-2">
                                        {role.permissions.map((permission) => (
                                            <li
                                                key={permission.value}
                                                className="flex items-start gap-2"
                                            >
                                                <Check
                                                    className="mt-0.5 size-4 shrink-0 text-success"
                                                    aria-hidden="true"
                                                />
                                                <span>{permission.label}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>

                                <section>
                                    <div className="flex items-center justify-between gap-2">
                                        <h2 className="text-sm font-medium">
                                            Members
                                        </h2>
                                        <Link
                                            href={
                                                users.index({
                                                    query: { role: role.code },
                                                }).url
                                            }
                                            className="text-sm underline-offset-4 hover:underline"
                                        >
                                            View all
                                        </Link>
                                    </div>
                                    {(role.members ?? []).length === 0 ? (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            No accounts currently hold this
                                            role.
                                        </p>
                                    ) : (
                                        <ul className="mt-2 divide-y rounded-md border">
                                            {(role.members ?? []).map(
                                                (member) => (
                                                    <li
                                                        key={member.id}
                                                        className="flex min-w-0 flex-col gap-0.5 px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
                                                    >
                                                        <Link
                                                            href={
                                                                users.show(
                                                                    member.id,
                                                                ).url
                                                            }
                                                            className="truncate font-medium hover:underline"
                                                        >
                                                            {member.name}
                                                        </Link>
                                                        <span className="truncate text-xs text-muted-foreground">
                                                            {member.email}
                                                        </span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    )}
                                </section>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
