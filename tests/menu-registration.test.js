const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../js/main.js'), 'utf8');

test('registers BLAST actions without creating an Analyze dropdown', async () => {
    const widgets = new Map();
    const menus = new Map();
    const renderedMenus = [];
    const alerts = [];
    const milestones = [];
    const menuOrder = ['file', 'view', 'help'];
    const menuNodes = new Map(menuOrder.map(name => [name, { name }]));
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
            milestones.push(callback);
        },
        renderGlobalMenu(name) {
            renderedMenus.push(name);
            this.afterMilestone('initView', () => {
                menuNodes.set(name, { name });
                menuOrder.push(name);
            });
        },
        addGlobalMenuItem(name, item) {
            if (!menus.has(name)) menus.set(name, []);
            menus.get(name).push(item);
        }
    };
    vm.runInNewContext(source, {
        console,
        window: { location: { protocol: 'https:', hostname: 'example.test' } },
        fetch: async () => ({ ok: true, json: async () => ({ blastService: 'php' }) }),
        alert(message) { alerts.push(message); },
        dojo: { byId() {}, create() {}, hitch: (context, callback) => callback.bind(context) },
        define(dependencies, factory) {
            const dom = {
                place(node, reference, position) {
                    assert.equal(position, 'before');
                    menuOrder.splice(menuOrder.indexOf(node.name), 1);
                    menuOrder.splice(menuOrder.indexOf(reference.name), 0, node.name);
                }
            };
            const query = (selector, root) => {
                assert.equal(root, browser.menuBar);
                const name = selector.match(/dropdownbutton_(\w+)/)[1];
                return menuNodes.has(name) ? [menuNodes.get(name)] : [];
            };
            implementation = factory((base, methods) => methods, lang, Deferred,
                dom, query, {}, {}, Widget, Widget, Widget, {});
        },
        require(dependencies, callback) {
            callback(lang, function() {});
        }
    });
    const plugin = Object.assign({}, implementation, {
        browser,
        rightClickMenuInit() {},
        sendTo() { submissions++; }
    });
    implementation.constructor.call(plugin, {});
    browser.ggblast_plugin.isOversized = () => oversized;
    while (milestones.length) milestones.shift()();
    await new Promise(resolve => setImmediate(resolve));

    assert.deepEqual(renderedMenus, ['blast']);
    assert.deepEqual(menuOrder, ['file', 'view', 'blast', 'help']);
    assert.deepEqual(menus.get('blast').map(item => item.id),
        ['menubar_blast_submit_demo', 'menubar_blast_jobs']);

    const item = menus.get('blast')[0];
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
});
