import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const source = ts.transpileModule(
    readFileSync('resources/js/hooks/use-activity.ts', 'utf8'),
    {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            target: ts.ScriptTarget.ES2022,
        },
    },
).outputText;

function setup(enabled = true) {
    let now = 0;
    let cleanup;
    let interval;
    let intersection;
    let mutation;
    const listeners = new Map();
    const sent = [];
    const observed = new Set();
    const sections = [{ dataset: { section: 'terms' }, isConnected: true }];
    const target = (prefix) => ({
        addEventListener: (name, handler) =>
            listeners.set(prefix + name, handler),
        removeEventListener: (name) => listeners.delete(prefix + name),
    });
    const document = {
        ...target('doc:'),
        hidden: false,
        cookie: '',
        body: {},
        documentElement: { scrollHeight: 1000 },
        querySelectorAll: () => sections,
    };
    const context = {
        exports: {},
        document,
        performance: { now: () => now },
        crypto: { randomUUID: () => 'event-' + Math.random() },
        require: () => ({
            useEffect: (effect) => {
                cleanup = effect();
            },
        }),
        window: {
            ...target('win:'),
            setInterval: (handler) => {
                interval = handler;
                return 1;
            },
        },
        clearInterval: () => {},
        scrollY: 0,
        innerHeight: 800,
        fetch: (_url, options) => {
            sent.push(...JSON.parse(options.body).events);
            return Promise.resolve({ ok: true });
        },
        IntersectionObserver: class {
            constructor(callback) {
                intersection = callback;
            }
            observe(element) {
                observed.add(element);
            }
            unobserve(element) {
                observed.delete(element);
            }
            disconnect() {
                observed.clear();
            }
        },
        MutationObserver: class {
            constructor(callback) {
                mutation = callback;
            }
            observe() {}
            disconnect() {}
        },
    };
    vm.runInNewContext(source, context);
    context.exports.useActivity(96, 2, enabled);
    return {
        sent,
        document,
        sections,
        observed,
        listeners,
        tick: (time) => {
            now = time;
            interval();
        },
        visible: (element = sections[0]) =>
            intersection([{ target: element, isIntersecting: true }]),
        mutate: () => mutation(),
        cleanup: () => cleanup?.(),
    };
}

test('batches reading duration with case and version, and caps inactivity', () => {
    const page = setup();
    page.visible();
    page.tick(10000);
    assert.equal(
        page.sent.filter(
            (event) => event.type === 'ui.section_visibility_duration',
        ).length,
        0,
    );
    page.tick(30000);
    page.tick(60000);
    page.tick(90000);
    const reading = page.sent.filter(
        (event) => event.type === 'ui.section_visibility_duration',
    );
    assert.equal(reading.length, 2);
    assert.equal(reading[0].data.duration_ms, 30000);
    assert.ok(
        page.sent.every(
            (event) => event.case_id === 96 && event.data.version === 2,
        ),
    );
    page.cleanup();
});

test('does not count hidden time and flushes partial reading on exit', () => {
    const page = setup();
    page.visible();
    page.tick(10000);
    page.document.hidden = true;
    page.listeners.get('doc:visibilitychange')();
    page.tick(40000);
    page.document.hidden = false;
    page.listeners.get('doc:visibilitychange')();
    page.tick(50000);
    page.listeners.get('win:pagehide')();
    const total = page.sent
        .filter((event) => event.type === 'ui.section_visibility_duration')
        .reduce((sum, event) => sum + event.data.duration_ms, 0);
    assert.equal(total, 20000);
    page.listeners.get('win:pageshow')();
    page.tick(60000);
    page.listeners.get('win:pagehide')();
    assert.equal(
        page.sent
            .filter((event) => event.type === 'ui.section_visibility_duration')
            .reduce((sum, event) => sum + event.data.duration_ms, 0),
        30000,
    );
    page.cleanup();
});

test('observes sections introduced by a partial reload and avoids duplicate views', () => {
    const page = setup();
    page.visible();
    page.visible();
    const products = { dataset: { section: 'products' }, isConnected: true };
    page.sections.push(products);
    page.mutate();
    assert.ok(page.observed.has(products));
    page.visible(products);
    page.tick(10000);
    assert.equal(
        page.sent.filter((event) => event.type === 'ui.section_visible').length,
        2,
    );
    page.cleanup();
    assert.equal(page.listeners.size, 0);
});

test('staff pages do not install or send buyer activity', () => {
    const page = setup(false);
    assert.equal(page.listeners.size, 0);
    assert.equal(page.observed.size, 0);
    assert.equal(page.sent.length, 0);
});
