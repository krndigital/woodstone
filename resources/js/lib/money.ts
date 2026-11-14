/**
 * Display helpers only. All money maths that matters is done on the server in paisa.
 */
export function toPaisa(value: string | number | null | undefined): number {
    if (value === null || value === undefined || value === '') return 0;
    const [whole, frac = ''] = String(value).split('.');
    const sign = whole.startsWith('-') ? -1 : 1;
    return (
        sign *
        (Math.abs(parseInt(whole, 10) || 0) * 100 +
            parseInt((frac + '00').slice(0, 2), 10))
    );
}

export function rs(value: string | number | null | undefined): string {
    const p = typeof value === 'number' ? value : toPaisa(value);
    return (
        'Rs ' +
        (p / 100).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
    );
}

export function minutesSince(iso: string | null): string {
    if (iso === null) return '';
    const m = Math.max(
        0,
        Math.round((Date.now() - new Date(iso).getTime()) / 60000),
    );
    return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h ${m % 60} min`;
}
