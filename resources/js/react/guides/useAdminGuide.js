import { useEffect, useRef, useState } from 'react';
import { adminGuideFor, adminGuideLabels, findGuideTarget } from './admin';

export default function useAdminGuide({ enabled, url, locale }) {
    const instance = useRef(null);
    const generation = useRef(0);
    const busy = useRef(false);
    const [status, setStatus] = useState('idle');
    const labels = adminGuideLabels[locale] || adminGuideLabels.ru;

    useEffect(() => {
        setStatus('idle');
        return () => {
            generation.current += 1;
            busy.current = false;
            instance.current?.destroy();
            instance.current = null;
        };
    }, [enabled, url, locale]);

    async function start(event) {
        if (!enabled || busy.current) return;
        const guide = adminGuideFor(url, locale);
        if (!guide) return;
        busy.current = true;
        setStatus('preparing');
        const version = generation.current;
        const trigger = event.currentTarget;
        try {
            const { driver } = await import('driver.js');
            await import('driver.js/dist/driver.css');
            // Wait for the current page's API data without navigating or submitting forms.
            for (let attempt = 0; attempt < 60; attempt += 1) {
                if (version !== generation.current) return;
                if (!document.querySelector('[data-guide-busy="true"]')) break;
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            if (version !== generation.current) return;
            const steps = guide.steps.flatMap(item => {
                const target = findGuideTarget(item.target);
                if (!target) return [];
                // Long sections cannot fit in a phone viewport; point to their heading instead.
                const heading = target.querySelector('h2, h3');
                const element = heading && target.getBoundingClientRect().height > window.innerHeight * 0.75 ? heading : target;
                return [{ element, popover: { title: item.title, description: item.description } }];
            });
            if (!steps.length) throw new Error('No visible guide targets');
            const tour = driver({
                steps,
                animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                smoothScroll: false,
                overlayColor: '#182132',
                overlayOpacity: 0.58,
                overlayClickBehavior: 'none',
                disableActiveInteraction: true,
                allowClose: true,
                allowKeyboardControl: true,
                skipMissingElement: true,
                stagePadding: 6,
                stageRadius: 6,
                popoverClass: 'vacancy-admin-guide',
                showProgress: true,
                progressText: labels.progress,
                nextBtnText: labels.next,
                prevBtnText: labels.previous,
                doneBtnText: labels.done,
                closeBtnLabel: labels.close,
                onPopoverRender(popover, { driver: current }) {
                    popover.wrapper.insertBefore(popover.progress, popover.title);
                    const skip = document.createElement('button');
                    skip.type = 'button';
                    skip.className = 'admin-guide-skip';
                    skip.textContent = labels.skip;
                    skip.addEventListener('click', () => current.destroy());
                    popover.footer.prepend(skip);
                },
                onDestroyed() {
                    instance.current = null;
                    if (version !== generation.current) return;
                    busy.current = false;
                    setStatus('idle');
                    requestAnimationFrame(() => {
                        if (version === generation.current && trigger.isConnected) trigger.focus({ preventScroll: true });
                    });
                },
            });
            instance.current = tour;
            setStatus('active');
            tour.drive();
        } catch {
            if (version !== generation.current) return;
            instance.current?.destroy();
            instance.current = null;
            busy.current = false;
            setStatus('error');
        }
    }

    return { start, labels, status, disabled: status === 'preparing' || status === 'active' };
}
