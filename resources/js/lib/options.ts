import type { IdName, Option } from '@/types';

/**
 * Turn {id, name} records into select options.
 */
export function idOptions(items: IdName[]): Option<number>[] {
    return items.map((item) => ({ value: item.id, label: item.name }));
}

/**
 * Find the label of an option value.
 */
export function optionLabel<T extends string | number>(
    options: Option<T>[],
    value: T | null | undefined,
): string | null {
    return options.find((option) => option.value === value)?.label ?? null;
}
