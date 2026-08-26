export const WEIGHT_FACTORS: Record<string, number> = {
    gram: 1,
    tola: 11.6638038, // 1 tola = 11.6638038 grams
    masha: 11.6638038 / 12, // 1 masha = 1/12 tola
    ratti: (11.6638038 / 12) / 8, // 1 ratti = 1/8 masha
    point: 11.6638038 / 100, // assume 1 tola = 100 points
};

export function toGrams(value: number, unit: string): number {
    const factor = WEIGHT_FACTORS[unit] ?? 1;
    return value * factor;
}

export function fromGrams(grams: number, unit: string): number {
    const factor = WEIGHT_FACTORS[unit] ?? 1;
    return grams / factor;
}

export function formatNumber(n: number | string | null | undefined, decimals = 3): string {
    if (n === null || n === undefined || n === '') return (0).toFixed(decimals);
    const num = typeof n === 'number' ? n : parseFloat(String(n));
    return !isNaN(num) && isFinite(num) ? num.toFixed(decimals) : (0).toFixed(decimals);
}
