const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../js/main.js'), 'utf8');

for (const deferredMenuLoad of [false, true]) {
    test('registers distinct BLAST actions with ' +
        (deferredMenuLoad ? 'deferred' : 'immediate') + ' menu dependencies', async () => {
        const widgets = new Map();
        const menus = new Map();
        const callbacks = [];
        const alerts = [];
        let milestone;
        let implementation;
        let submissions = 0;
        let oversized = false;

        function Widget(options) {
            assert(!widgets.has(options.id), 'Duplicate widget: ' + options.id);
            Object.assign(this, options);
            widgets.set(options.id, this);
        }

        function Deferred() {
            const promise = new Promise(resolve => { this.resolve = resolve; });
            this.then = promise.then.bind(promise);
        }

        const lang = { extend() {} };
        const browser = {
            config: {},
            menuBar: {},
            afterMilestone(name, callback) {
                assert.equal(name, 'initView');
                milestone = callback;
            },
            renderGlobalMenu() {},
            addGlobalMenuItem(name, item) {
                if (!menus.has(name)) menus.set(name, []);
                menus.get(name).push(item);
            }
        };
        vm.runInNewContext(source, {
            console,
            window: { location: { protocol: 'https:', hostname: 'example.test' } },
            fetch: async () => ({ json: async () => ({ blastService: 'php' }) }),
            alert(message) { alerts.push(message); },
            $() { return { insertBefore() {}, html() {} }; },
            dojo: { byId() {}, create() {}, hitch: (context, callback) => callback.bind(context) },
            define(dependencies, factory) {
                implementation = factory((base, methods) => methods, lang, Deferred,
                    {}, {}, Widget, Widget, Widget, {});
            },
            require(dependencies, callback) {
                if (dependencies[0] === 'dojo/dom-construct') {
                    if (deferredMenuLoad) callbacks.push(() => callback({}, Widget));
                    else callback({}, Widget);
                } else {
                    callback(lang, function() {});
                }
            }
        });
        const plugin = Object.assign({}, implementation, {
            browser,
            rightClickMenuInit() {},
            sendTo() { submissions++; }
        });
        implementation.constructor.call(plugin, {});
        browser.ggblast_plugin.isOversized = () => oversized;
        milestone();
        callbacks.forEach(callback => callback());
        await new Promise(resolve => setImmediate(resolve));

        assert.deepEqual(menus.get('analyze').map(item => item.id),
            ['menubar_analyze_submit_demo']);
        assert.deepEqual(menus.get('blast').map(item => item.id),
            ['menubar_blast_submit_demo', 'menubar_blast_jobs']);

        for (const name of ['analyze', 'blast']) {
            const item = menus.get(name)[0];
            assert.equal(item.label, 'BLAST highlighted region');
            delete browser._highlight;
            item.onClick();
            assert.equal(alerts.at(-1), 'no highlight region');
            browser._highlight = { start: 100, end: 200 };
            const before = submissions;
            oversized = true;
            item.onClick();
            assert.equal(submissions, before);
            oversized = false;
            item.onClick();
            assert.equal(submissions, before + 1);
        }
    });
}
