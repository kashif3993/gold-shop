// Traditional South Asian jeweller weight system:
//   100 point = 1 ratti
//     8 ratti = 1 masha
//    12 masha = 1 tola
//     1 tola  = 11.6638038 grams
export const TOLA_GRAMS = 11.6638038;

// Points contained in each higher unit (1 tola = 12 * 8 * 100 = 9600 point).
export const POINTS_PER_RATTI = 100;
export const POINTS_PER_MASHA = POINTS_PER_RATTI * 8; // 800
export const POINTS_PER_TOLA = POINTS_PER_MASHA * 12; // 9600

export const WEIGHT_FACTORS: Record<string, number> = {
    gram: 1,
    tola: TOLA_GRAMS,
    masha: TOLA_GRAMS / 12,
    ratti: TOLA_GRAMS / 12 / 8,
    point: TOLA_GRAMS / POINTS_PER_TOLA, // 1 point = 1/9600 tola
};

export function toGrams(value: number, unit: string): number {
    const factor = WEIGHT_FACTORS[unit] ?? 1;
    return value * factor;
}

export function fromGrams(grams: number, unit: string): number {
    const factor = WEIGHT_FACTORS[unit] ?? 1;
    return grams / factor;
}

export interface TraditionalUnits {
    tola: string;
    masha: string;
    ratti: string;
    point: string;
}

/**
 * Break a gram value into a carried tola / masha / ratti / point breakdown,
 * i.e. masha < 12, ratti < 8, point < 100. Empty string for zero parts so the
 * inputs stay clean.
 */
export function gramsToTraditional(grams: number | string): TraditionalUnits {
    const g = typeof grams === 'number' ? grams : parseFloat(String(grams));
    if (!g || !isFinite(g) || g <= 0) {
        return { tola: '', masha: '', ratti: '', point: '' };
    }

    let points = Math.round((g / WEIGHT_FACTORS.point) * 100) / 100; // total points, 2dp

    let tola = Math.floor(points / POINTS_PER_TOLA);
    points -= tola * POINTS_PER_TOLA;
    let masha = Math.floor(points / POINTS_PER_MASHA);
    points -= masha * POINTS_PER_MASHA;
    let ratti = Math.floor(points / POINTS_PER_RATTI);
    points -= ratti * POINTS_PER_RATTI;
    let point = Math.round(points * 100) / 100;

    // Guard rounding that pushes a part up to its rollover value.
    if (point >= POINTS_PER_RATTI) { point -= POINTS_PER_RATTI; ratti += 1; }
    if (ratti >= 8) { ratti -= 8; masha += 1; }
    if (masha >= 12) { masha -= 12; tola += 1; }

    return {
        tola: tola ? String(tola) : '',
        masha: masha ? String(masha) : '',
        ratti: ratti ? String(ratti) : '',
        point: point ? String(point) : '',
    };
}

/**
 * Carry overflow across a tola / masha / ratti / point breakdown:
 *   point >= 100 rolls into ratti, ratti >= 8 into masha, masha >= 12 into tola.
 * Values below their rollover threshold are left exactly as typed, so this only
 * rewrites the breakdown once a field actually overflows (e.g. entering 100 in
 * point moves it to 1 ratti / 0 point). Empty string for zero parts.
 */
export function carryTraditional(units: Partial<TraditionalUnits>): TraditionalUnits {
    let tola = parseFloat(units.tola ?? '') || 0;
    let masha = parseFloat(units.masha ?? '') || 0;
    let ratti = parseFloat(units.ratti ?? '') || 0;
    let point = parseFloat(units.point ?? '') || 0;

    const round2 = (n: number) => Math.round(n * 100) / 100;

    if (point >= POINTS_PER_RATTI) {
        ratti += Math.floor(point / POINTS_PER_RATTI);
        point = round2(point % POINTS_PER_RATTI);
    }
    if (ratti >= 8) {
        masha += Math.floor(ratti / 8);
        ratti = round2(ratti % 8);
    }
    if (masha >= 12) {
        tola += Math.floor(masha / 12);
        masha = round2(masha % 12);
    }

    return {
        tola: tola ? String(round2(tola)) : '',
        masha: masha ? String(round2(masha)) : '',
        ratti: ratti ? String(ratti) : '',
        point: point ? String(point) : '',
    };
}

/** Sum a tola / masha / ratti / point breakdown back into grams. */
export function traditionalToGrams(units: Partial<TraditionalUnits>): number {
    return (
        toGrams(parseFloat(units.tola ?? '') || 0, 'tola') +
        toGrams(parseFloat(units.masha ?? '') || 0, 'masha') +
        toGrams(parseFloat(units.ratti ?? '') || 0, 'ratti') +
        toGrams(parseFloat(units.point ?? '') || 0, 'point')
    );
}

export function formatNumber(n: number | string | null | undefined, decimals = 3): string {
    if (n === null || n === undefined || n === '') return (0).toFixed(decimals);
    const num = typeof n === 'number' ? n : parseFloat(String(n));
    return !isNaN(num) && isFinite(num) ? num.toFixed(decimals) : (0).toFixed(decimals);
}
