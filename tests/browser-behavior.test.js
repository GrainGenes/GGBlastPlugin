const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../js/main.js'), 'utf8');

function setup(response = { ok: true, json: async () => ({ bpSizeLimit: 100 }) }) {
    let implementation;
    const extensions = {};
    const alerts = [];
    const warnings = [];
    const animations = [];
    const parameters = [{ getAttribute: () => 'evalue', value: '1e-5' }];
    const textarea = { value: '>feature\nACTG' };
    const container = {};
    const browser = {
        config: {},
        _highlight: { ref: 'chr1', start: 10, end: 14 },
        afterMilestone() {},
        publish(topic, args) { this.published = { topic, args }; },
        getStore(name, callback) {
            assert.equal(name, 'refseqs');
            callback({
                getReferenceSequence(region, callback) { callback('ACTG'); }
            });
        },
        jblastHiliteMenu: { bindDomNode(node) { this.boundNode = node; } }
    };
    function Widget(options) {
        Object.assign(this, options);
        this.domNode = { style: {} };
    }
    function Deferred() {
        const promise = new Promise(resolve => { this.resolve = resolve; });
        this.then = promise.then.bind(promise);
    }
    const lang = {
        extend(type, methods) { Object.assign(type.prototype, methods); },
    };
    const baseFx = {
        fadeIn(options) { return { type: 'in', ...options }; },
        fadeOut(options) { return { type: 'out', ...options }; }
    };
    const fx = {
        chain(steps) {
            const animation = {
                steps,
                play() { this.played = true; },
                stop() { this.stopped = true; }
            };
            animations.push(animation);
            return animation;
        }
    };
    function query(selector, root) {
        if (selector === '.s-params .s-data') return parameters;
        assert.equal(selector, 'textarea.fasta');
        assert.equal(root, container);
        return [textarea];
    }
    vm.runInNewContext(source, {
        console: { log() {}, warn(...args) { warnings.push(args); } },
        window: { location: { protocol: 'https:', hostname: 'example.test' } },
        fetch: async () => response,
        JBrowse: browser,
        alert(message) { alerts.push(message); },
        dojo: { byId() {}, create() {}, hitch: (context, callback) => callback.bind(context) },
        define(dependencies, factory) {
            implementation = factory((base, methods) => methods, lang, Deferred,
                {}, query, baseFx, fx, Widget, Widget, Widget, {});
        },
        require(dependencies, callback) {
            if (dependencies[0] === 'JBrowse/View/FASTA') {
                callback(function() { this.renderText = () => '>chr1\nACTG'; });
            } else {
                const type = function() {};
                extensions[dependencies[1]] = type.prototype;
                callback(lang, type);
            }
        }
    });
    const plugin = Object.assign({}, implementation, {
        browser,
        rightClickMenuInit() {}
    });
    implementation.constructor.call(plugin, {});
    return { implementation, browser, extensions, alerts, warnings, animations, container };
}

test('highlights and clears regions without a global jQuery', () => {
    const { implementation, browser, extensions, animations } = setup();
    const node = {};
    implementation.BlockBased_postRenderHighlight(node);
    assert.equal(browser.jblastHiliteMenu.boundNode, node);
    const button = browser.ggblast_plugin.blastButton.domNode;
    assert.equal(button.style.display, '');
    assert.equal(animations[0].played, true);
    assert.deepEqual(Array.from(animations[0].steps, step => step.type),
        ['in', 'out', 'in', 'out', 'in', 'out', 'in', 'out', 'in']);
    assert(animations[0].steps.every(step => step.node === button && step.duration === 100));
    implementation.BlockBased_postRenderHighlight(node);
    assert.equal(animations[0].stopped, true);
    extensions['JBrowse/Browser'].clearHighlight.call(browser);
    assert.equal(animations[1].stopped, true);
    assert.equal(button.style.display, 'none');
    assert.equal(browser._highlight, undefined);
    assert.equal(browser.published.topic, '/jbrowse/v1/n/globalHighlightChanged');
});

test('reads highlighted sequence parameters without a global jQuery', () => {
    const { browser } = setup();
    let result;
    browser.ggblast_plugin.processInput(data => { result = data; });
    assert.equal(result.region, '>chr1\nACTG');
    assert.equal(result.bpSize, 4);
    assert.equal(result.params.evalue, '1e-5');
});

test('submits the displayed feature sequence without a global jQuery', () => {
    const { implementation, browser, container } = setup();
    let button;
    let submitted;
    browser.ggblast_plugin.sendTo = data => { submitted = data; };
    implementation.FASTA_addButtons.call({ renderText: () => '>feature\nACTG' },
        { start: 10, end: 14 }, 'ACTG', {
            domNode: { parentNode: container },
            addChild(widget) { button = widget; }
        });
    button.onClick();
    assert.equal(submitted.region, '>feature\nACTG');
    assert.equal(submitted.bpSize, 4);
});

test('loads configuration, preserves dataset overrides, and checks size boundaries', async () => {
    const { browser, alerts } = setup();
    browser.config.bpSizeLimit = 200;
    browser.config.blastApp = '/dataset-blast/';
    await browser.ggblast_plugin._configLoaded;
    assert.equal(browser.ggblast_plugin.bpSizeLimit, 200);
    assert.equal(browser.ggblast_plugin.blastApp, '/dataset-blast/');
    assert.equal(browser.ggblast_plugin.isOversized(200), false);
    assert.equal(browser.ggblast_plugin.isOversized(201), true);
    assert.match(alerts[0], /201 bp.*200 bp/);
});

test('reports configuration HTTP failures before attempting JSON parsing', async () => {
    const { browser, warnings } = setup({
        ok: false,
        status: 403,
        json() { assert.fail('must not parse the HTML error response as JSON'); }
    });
    browser.config.blastApp = '/dataset-blast/';
    await browser.ggblast_plugin._configLoaded;
    assert.equal(warnings.length, 1);
    assert.match(warnings[0][1].message, /config.json: HTTP 403/);
    assert.equal(browser.ggblast_plugin.blastApp, '/dataset-blast/');
});
