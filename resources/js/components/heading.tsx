export default function Heading({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <header className="space-y-0.5">
            <h2 className="mb-0.5 text-base leading-6 font-semibold">{title}</h2>
            {description && (
                <p className="max-w-2xl text-sm leading-5 text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
