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
    return (
        <label className="flex flex-col gap-1 text-sm">
            <span>Sort by</span>
            <select
                name="sort"
                defaultValue={value}
                className="min-h-11 rounded-md border bg-background px-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}
