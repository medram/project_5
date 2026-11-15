import { useId } from 'react';
import { Field, FieldLabel } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
export type ResourceListSortOption = {
    value: string;
    label: string;
};

export function ResourceListSortMenu({
    value,
    options,
}: {
    value: string;
    options: ResourceListSortOption[];
}) {
    const id = useId();

    return (
        <Field className="gap-1">
            <FieldLabel htmlFor={id} className="text-sm">
                Sort by
            </FieldLabel>
            <Select name="sort" defaultValue={value}>
                <SelectTrigger id={id} className="min-h-11">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}
