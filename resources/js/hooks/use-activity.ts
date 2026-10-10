import { useEffect } from 'react';

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
        body: JSON.stringify({ events }),
    })
        .then((r) => {
            if (!r.ok && r.status >= 500) pending.unshift(...events);
        })
        .catch(() => pending.unshift(...events));
}
export function useActivity(caseId?: number, version?: number, enabled = true) {
    useEffect(() => {
        if (!enabled) return;
        allowed = true;
        const context: Activity['data'] = version ? { version } : {};
        track('ui.session_started', context, caseId);
        const visible = new Set<Element>();
        const observed = new Set<Element>();
        const reported = new Set<string>();
        const durations = new Map<string, number>();
        let last = performance.now();
        let lastInteraction = last;
        let foreground = !document.hidden;
        const sample = () => {
            const now = performance.now();
            const duration = Math.max(
                0,
                Math.round(Math.min(now, lastInteraction + 60000) - last),
            );
            last = now;
            if (foreground && duration > 0) {
                for (const element of visible) {
                    const section = (element as HTMLElement).dataset.section!;
                    durations.set(
                        section,
                        (durations.get(section) ?? 0) + duration,
                    );
                }
            }
        };
        const reportDurations = (all = false) => {
            durations.forEach((duration, section) => {
                while (duration >= 30000 || (all && duration > 0)) {
                    const chunk = Math.min(duration, 30000);
                    track(
                        'ui.section_visibility_duration',
                        { ...context, section, duration_ms: chunk },
                        caseId,
                    );
                    duration -= chunk;
                }
                durations.set(section, duration);
            });
        };
        const interact = () => {
            sample();
            lastInteraction = performance.now();
        };
        const observer = new IntersectionObserver(
            (entries) => {
                sample();
                entries.forEach((entry) => {
                    const section = (entry.target as HTMLElement).dataset
                        .section!;
                    if (entry.isIntersecting) {
                        visible.add(entry.target);
                        if (!reported.has(section) && !document.hidden) {
                            reported.add(section);
                            track(
                                'ui.section_visible',
                                { ...context, section },
                                caseId,
                            );
                        }
                    } else visible.delete(entry.target);
                });
            },
            { threshold: 0 },
        );
        const observeSections = () => {
            sample();
            for (const element of observed) {
                if (!element.isConnected) {
                    observer.unobserve(element);
                    observed.delete(element);
                    visible.delete(element);
                }
            }
            document.querySelectorAll('[data-section]').forEach((element) => {
                if (!observed.has(element)) {
                    observed.add(element);
                    observer.observe(element);
                }
            });
        };
        observeSections();
        // New versions/sections can arrive through an Inertia partial reload.
        const mutations = new MutationObserver(observeSections);
        mutations.observe(document.body, { childList: true, subtree: true });
        const timer = window.setInterval(() => {
            sample();
            reportDurations();
            flush();
        }, 10000);
        const visibility = () => {
            sample();
            foreground = !document.hidden;
            if (foreground) lastInteraction = performance.now();
            reportDurations(true);
            flush();
        };
        const pagehide = () => {
            sample();
            reportDurations(true);
            foreground = false;
            flush();
        };
        let depth = 0;
        const scroll = () => {
            interact();
            const next = Math.min(
                100,
                Math.floor(
                    ((scrollY + innerHeight) /
                        Math.max(document.documentElement.scrollHeight, 1)) *
                        4,
                ) * 25,
            );
            if (next > depth) {
                depth = next;
                track('ui.scroll_depth_reached', { ...context, depth }, caseId);
            }
        };
        document.addEventListener('visibilitychange', visibility);
        window.addEventListener('pagehide', pagehide);
        window.addEventListener('pageshow', visibility);
        window.addEventListener('pointerdown', interact, { passive: true });
        window.addEventListener('pointermove', interact, { passive: true });
        window.addEventListener('keydown', interact);
        window.addEventListener('scroll', scroll, { passive: true });
        return () => {
            sample();
            reportDurations(true);
            clearInterval(timer);
            observer.disconnect();
            mutations.disconnect();
            document.removeEventListener('visibilitychange', visibility);
            window.removeEventListener('pagehide', pagehide);
            window.removeEventListener('pageshow', visibility);
            window.removeEventListener('pointerdown', interact);
            window.removeEventListener('pointermove', interact);
            window.removeEventListener('keydown', interact);
            window.removeEventListener('scroll', scroll);
            flush();
            allowed = false;
        };
    }, [caseId, version, enabled]);
}
