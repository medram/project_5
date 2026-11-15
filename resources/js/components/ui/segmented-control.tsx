import { useRef } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type SegmentedOption<T extends string> = {
    value: T;
    label: ReactNode;
};

type SegmentedControlProps<T extends string> = {
    value: T;
    onValueChange: (value: T) => void;
    options: ReadonlyArray<SegmentedOption<T>>;
    ariaLabel: string;
    className?: string;
};

export function SegmentedControl<T extends string>({
    value,
    onValueChange,
    options,
    ariaLabel,
    className,
}: SegmentedControlProps<T>) {
    const buttons = useRef<Record<string, HTMLButtonElement | null>>({});

    function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        const index = options.findIndex((option) => option.value === value);
        let next = index;

        switch (event.key) {
            case 'ArrowRight':
                next = (index + 1) % options.length;
                break;
            case 'ArrowLeft':
                next = (index - 1 + options.length) % options.length;
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = options.length - 1;
                break;
            default:
                return;
        }

        event.preventDefault();
        const option = options[next];

        if (option) {
            onValueChange(option.value);
            buttons.current[option.value]?.focus();
        }
    }

    return (
        <div
            role="radiogroup"
            aria-label={ariaLabel}
            onKeyDown={handleKeyDown}
            className={cn(
                'inline-flex h-9 items-center gap-[3px] rounded-2xl bg-default p-[3px]',
                className,
            )}
        >
            {options.map((option) => {
                const selected = option.value === value;

                return (
                    <button
                        key={option.value}
                        ref={(element) => {
                            buttons.current[option.value] = element;
                        }}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        tabIndex={selected ? 0 : -1}
                        onClick={() => onValueChange(option.value)}
                        className={cn(
                            'inline-flex h-8 items-center justify-center rounded-[13px] px-3 text-sm font-medium whitespace-nowrap transition-[color,background-color,box-shadow] duration-150 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none',
                            selected
                                ? 'bg-card text-foreground shadow-[0_1px_2px_rgba(0,0,0,0.06)]'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}