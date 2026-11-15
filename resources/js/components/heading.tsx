export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small' | 'page';
}) {
    const Element = variant === 'page' ? 'h1' : 'h2';
    const headingClass =
        variant === 'page'
            ? 'text-2xl leading-8 font-semibold tracking-[-0.02em] text-balance'
            : variant === 'small'
              ? 'mb-0.5 text-base leading-6 font-semibold'
              : 'text-xl font-semibold tracking-[-0.02em]';

    return (
        <header
            className={
                variant === 'page'
                    ? 'space-y-2'
                    : variant === 'small'
                      ? ''
                      : 'mb-8 space-y-0.5'
            }
        >
            <Element className={headingClass}>{title}</Element>
            {description && (
                <p className="max-w-2xl text-sm leading-5 text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
