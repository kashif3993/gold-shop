export function useInitials() {
    const getInitials = (name?: string | null): string => {
        if (!name) return '';

        const parts = name.trim().split(/[\s._-]+/).filter(Boolean);

        if (parts.length === 0) return '';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();

        return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
    };

    return getInitials;
}
