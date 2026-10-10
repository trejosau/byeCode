import { useEffect, useState } from 'react';

type Activity = {
    event_id: string;
    type: string;
    case_id?: number;
    data: Record<string, string | number>;
};
let pending: Activity[] = [];
let allowed = false;
export function track(
    type: string,
    data: Activity['data'] = {},
    caseId?: number,
) {
    if (allowed)
        pending.push({
            event_id: crypto.randomUUID(),
            type,
            data,
            case_id: caseId,
        });
    if (pending.length > 100) pending = pending.slice(-100);
}
function flush() {
    if (!allowed || !pending.length) return;
    const events = pending.splice(0, 50);
    const csrf = document.cookie
        .split('; ')
        .find((s) => s.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');
    fetch('/activity', {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(csrf ?? ''),
        },
        body: JSON.stringify({ consent: true, events }),
    })
        .then((r) => {
            if (!r.ok && r.status >= 500) pending.unshift(...events);
        })
        .catch(() => pending.unshift(...events));
}
export function useActivity(caseId?: number) {
    const [consent, setConsent] = useState(
        () =>
            typeof window !== 'undefined' &&
            localStorage.getItem('byecode-analytics') === 'yes',
    );
    useEffect(() => {
        allowed = consent;
        if (!consent) {
            pending = [];
            return;
        }
        track('ui.session_started', {}, caseId);
        const visible = new Set<string>();
        let last = performance.now();
        let lastInteraction = last;
        const interact = () => {
            lastInteraction = performance.now();
        };
        const observer = new IntersectionObserver(
            (entries) =>
                entries.forEach((entry) => {
                    const section = (entry.target as HTMLElement).dataset
                        .section!;
                    if (entry.isIntersecting) {
                        visible.add(section);
                        track('ui.section_visible', { section }, caseId);
                    } else visible.delete(section);
                }),
            { threshold: 0.4 },
        );
        document
            .querySelectorAll('[data-section]')
            .forEach((element) => observer.observe(element));
        const timer = window.setInterval(() => {
            const now = performance.now();
            const duration = Math.min(10000, Math.round(now - last));
            last = now;
            if (!document.hidden && now - lastInteraction < 60000)
                visible.forEach((section) =>
                    track(
                        'ui.section_visibility_duration',
                        { section, duration_ms: duration },
                        caseId,
                    ),
                );
            flush();
        }, 10000);
        const visibility = () => {
            last = performance.now();
            flush();
        };
        let depth = 0;
        const scroll = () => {
            const next =
                Math.floor(
                    ((scrollY + innerHeight) /
                        Math.max(document.documentElement.scrollHeight, 1)) *
                        4,
                ) * 25;
            if (next > depth) {
                depth = Math.min(next, 100);
                track('ui.scroll_depth_reached', { depth }, caseId);
            }
        };
        document.addEventListener('visibilitychange', visibility);
        window.addEventListener('pointerdown', interact, { passive: true });
        window.addEventListener('pointermove', interact, { passive: true });
        window.addEventListener('scroll', scroll, { passive: true });
        return () => {
            clearInterval(timer);
            observer.disconnect();
            document.removeEventListener('visibilitychange', visibility);
            window.removeEventListener('pointerdown', interact);
            window.removeEventListener('pointermove', interact);
            window.removeEventListener('scroll', scroll);
            flush();
        };
    }, [consent, caseId]);
    return {
        consent,
        choose: (yes: boolean) => {
            localStorage.setItem('byecode-analytics', yes ? 'yes' : 'no');
            setConsent(yes);
        },
    };
}
