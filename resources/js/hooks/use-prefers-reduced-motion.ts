import { useEffect, useState } from 'react';

/**
 * Tracks the user's reduced-motion preference so animated, purely decorative
 * behaviour (such as carousel auto-rotation) can be disabled.
 */
export function usePrefersReducedMotion(): boolean {
    const [prefersReducedMotion, setPrefersReducedMotion] = useState(false);

    useEffect(() => {
        const query = window.matchMedia('(prefers-reduced-motion: reduce)');
        const updatePreference = () => setPrefersReducedMotion(query.matches);

        updatePreference();
        query.addEventListener('change', updatePreference);

        return () => query.removeEventListener('change', updatePreference);
    }, []);

    return prefersReducedMotion;
}
