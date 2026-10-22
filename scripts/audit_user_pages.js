/**
 * Walk every user-facing page once, in a real (headless) browser, and report what a
 * source read cannot see: help buttons wired to nothing, glossary terms that do not
 * open, a glossary popup hidden behind its modal, filter boxes that do not filter,
 * icon-only controls with no label, JS errors, failed requests, non-200 pages.
 *
 * Needs puppeteer, which is NOT a repo dependency (no-framework rule, CLAUDE.md §12):
 *   mkdir -p ~/moop-audit && cd ~/moop-audit && npm i puppeteer
 *   NODE_PATH=~/moop-audit/node_modules node scripts/audit_user_pages.js [base] [--out file.json]
 * If puppeteer's Chrome download fails, install with PUPPETEER_SKIP_DOWNLOAD=1 and set
 * PUPPETEER_EXECUTABLE_PATH, or rely on a Chrome already in ~/.cache/puppeteer.
 *
 * base: http://172.16.2.52/moop (default — IP_IN_RANGE auto-login, full data) or
 *       http://127.0.0.1/moop (outside the auto-login range = the anonymous PUBLIC view).
 * Exit 1 if any page is flagged.
 *
 * ⚠️ Lessons baked in: hover a glossary term on its FIRST line box (a wrapped term's centre
 * falls between lines); judge visibility with checkVisibility() (text in a closed <details>
 * has a box but no innerText). A "page-level (i) next to the title" check was tried and
 * dropped — pages put that button in different places, so it was wrong in both directions.
 */
const puppeteer = require('puppeteer');
const fs = require('fs');
const os = require('os');

const args = process.argv.slice(2);
const outIdx = args.indexOf('--out');
const OUT = outIdx >= 0 ? args.splice(outIdx, 2)[1] : null;
const BASE = args[0] || 'http://172.16.2.52/moop';
const O = 'Nematostella_vectensis', A = 'GCA_033964005.1', G = 'NV2';
const PAGES = [
  ['home', '/index.php'],
  ['search', '/tools/search.php'],
  ['organism', `/tools/organism.php?organism=${O}`],
  ['assembly', `/tools/assembly.php?organism=${O}&assembly=${A}`],
  ['gene_set', `/tools/gene_set.php?organism=${O}&assembly=${A}&gene_set=${G}`],
  ['groups', '/tools/groups.php?group=Cnidaria'],
  ['multi_organism', `/tools/multi_organism.php?organisms[]=${O}`],
  ['gene (parent)', `/tools/parent.php?organism=${O}&assembly=${A}&gene_set=${G}&uniquename=NV2g025931000.1`],
  ['blast', '/tools/blast.php'],
  ['data exporter', '/tools/moopmart.php'],
  ['downloads', '/tools/downloads.php'],
  ['retrieve_sequences', '/tools/retrieve_sequences.php'],
  ['retrieve_selected', `/tools/retrieve_selected_sequences.php?organism=${O}&assembly=${A}&gene_set=${G}&uniquenames=NV2g025931000.1`],
  ['primer_blast', '/tools/primer_blast.php'],
  ['primer_maker', '/tools/primer_maker.php'],
  ['jbrowse2', '/jbrowse2.php'],
  ['about', '/about.php'],
  ['help', '/help.php'],
  ['help: what to do', '/help.php?topic=finding-the-right-page'],
  ['help: data org', '/help.php?topic=data-organization'],
  ['help: sources', '/help.php?topic=data-sources'],
  ['help: access', '/help.php?topic=access'],
  ['help: glossary', '/help.php?topic=glossary'],
  ['login', '/login.php'],
];

function chromePath() {
  if (process.env.PUPPETEER_EXECUTABLE_PATH) return process.env.PUPPETEER_EXECUTABLE_PATH;
  const dir = os.homedir() + '/.cache/puppeteer/chrome';
  if (!fs.existsSync(dir)) return undefined;   // let puppeteer use its own download
  const v = fs.readdirSync(dir).sort().pop();
  return `${dir}/${v}/chrome-linux64/chrome`;
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: chromePath(), args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const results = [];
  for (const [name, path] of PAGES) {
    const page = await browser.newPage();
    await page.setCacheEnabled(false);
    await page.setViewport({ width: 1400, height: 900 });
    const errs = [], bad = [];
    page.on('pageerror', e => errs.push('pageerror: ' + String(e.message).split('\n')[0]));
    page.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 160)); });
    page.on('response', r => { if (r.status() >= 400) bad.push(r.status() + ' ' + r.url().replace(BASE, '')); });
    let status = 0;
    try {
      const resp = await page.goto(BASE + path, { waitUntil: 'networkidle2', timeout: 60000 });
      status = resp ? resp.status() : 0;
    } catch (e) { errs.push('goto: ' + e.message); }

    const r = await page.evaluate(() => {
      const bs = window.bootstrap;
      const vis = el => el.checkVisibility ? el.checkVisibility() : !!(el.offsetWidth || el.offsetHeight);
      const out = {};
      out.h1 = (document.querySelector('h1')?.innerText || '').trim().slice(0, 60);
      const purpose = document.querySelector('[data-page-purpose]');
      out.purpose = purpose ? (purpose.hidden ? 'hidden' : 'shown') : 'NONE';
      // Help near the top: a .field-help in the first heading's own row/header.
      const h = document.querySelector('h1, h2');
      const box = h && (h.closest('.card-header, .page-header, header, .d-flex') || h.parentElement);
      out.headerHelp = !!(box && box.querySelector('.field-help'));
      // Popovers / modals that are wired to nothing.
      const pops = [...document.querySelectorAll('[data-bs-toggle="popover"]')];
      out.popovers = pops.length;
      out.deadPopovers = pops.filter(el => !el.classList.contains('field-help') && !el.classList.contains('gloss')
          && bs && !bs.Popover.getInstance(el)).map(el => (el.getAttribute('aria-label') || el.className).slice(0, 40));
      const glosses = [...document.querySelectorAll('.gloss')];
      out.gloss = glosses.length;
      out.deadGloss = glosses.filter(el => !(bs && bs.Popover.getInstance(el))).map(el => el.innerText.trim());
      out.glossInModals = glosses.filter(el => el.closest('.modal')).length;
      const mtrig = [...document.querySelectorAll('[data-bs-toggle="modal"]')];
      out.modalTriggers = mtrig.length;
      out.brokenModalTriggers = mtrig.filter(el => {
        const t = el.getAttribute('data-bs-target') || el.getAttribute('href');
        return !t || !document.querySelector(t);
      }).map(el => el.getAttribute('data-bs-target'));
      // Icon-only controls with no accessible name.
      out.unlabelled = [...document.querySelectorAll('button, a[href], [role="button"]')].filter(el => {
        if (!vis(el)) return false;
        if (el.innerText.trim()) return false;
        if (el.getAttribute('aria-label') || el.getAttribute('title') || el.getAttribute('aria-labelledby')) return false;
        if (el.querySelector('img[alt]:not([alt=""])')) return false;
        return true;
      }).map(el => el.tagName + '.' + [...el.classList].slice(0, 3).join('.') + ' ' + (el.querySelector('i')?.className || '')).slice(0, 8);
      out.words = document.body.innerText.split(/\s+/).filter(Boolean).length;
      return out;
    }).catch(e => ({ evalError: e.message }));

    // Glossary popover inside a modal: open the modal, hover the term, see if the
    // popover is actually on top (elementFromPoint at its centre lands inside it).
    r.modalGloss = 'n/a';
    if (r.glossInModals) {
      r.modalGloss = await page.evaluate(async () => {
        const g = [...document.querySelectorAll('.modal .gloss')][0];
        const m = g.closest('.modal');
        bootstrap.Modal.getOrCreateInstance(m).show();
        await new Promise(res => setTimeout(res, 600));
        g.scrollIntoView({ block: 'center' });
        await new Promise(res => setTimeout(res, 200));
        const b = g.getClientRects()[0];
        return { x: b.x + b.width / 2, y: b.y + b.height / 2, term: g.innerText.trim(), inView: b.y > 0 && b.bottom < innerHeight };
      });
      if (r.modalGloss.inView) {
        const trig = await page.evaluate(() => document.querySelector('.modal .gloss').getAttribute('data-bs-trigger') || 'click');
        if (/hover/.test(trig)) await page.mouse.move(r.modalGloss.x, r.modalGloss.y);
        else await page.mouse.click(r.modalGloss.x, r.modalGloss.y);
        await new Promise(res => setTimeout(res, 500));
        r.modalGloss.result = await page.evaluate(() => {
          const p = document.querySelector('.popover');
          if (!p) return 'NO POPOVER';
          const b = p.getBoundingClientRect();
          const top = document.elementFromPoint(b.x + b.width / 2, b.y + b.height / 2);
          return p.contains(top) ? 'visible' : 'BEHIND (' + (top ? top.className.slice(0, 40) : 'null') + ')';
        });
      }
    }
    // Any help filter box: type a word from the first item, expect fewer items, then none for junk.
    r.filter = await page.evaluate(async () => {
      const inp = document.querySelector('input[data-help-filter]');
      if (!inp) return null;
      const items = [...document.querySelectorAll(inp.dataset.helpFilter)];
      const vis = () => items.filter(el => el.style.display !== 'none').length;
      const word = (items[items.length - 1].dataset.search || '').split(' ')[0];
      const set = v => { inp.value = v; inp.dispatchEvent(new Event('input')); };
      set(word); const some = vis();
      set('zzzqqq'); const none = vis();
      set(''); const all = vis();
      return (some > 0 && some < items.length && none === 0 && all === items.length) ? 'ok'
        : `broken (word=${word} some=${some} none=${none} all=${all}/${items.length})`;
    });
    results.push({ name, path, status, errs: [...new Set(errs)].slice(0, 5), bad: [...new Set(bad)].slice(0, 5), ...r });
    await page.close();
  }
  await browser.close();
  if (OUT) fs.writeFileSync(OUT, JSON.stringify(results, null, 1));
  let flagged = 0;
  for (const x of results) {
    const flags = [];
    if (x.status !== 200) flags.push('HTTP ' + x.status);
    // Help topics and login are not routes, so they declare no purpose by design.
    if (x.purpose === 'NONE' && !/^(help|about|login)/.test(x.name)) flags.push('no page purpose');
    if (x.filter && x.filter !== 'ok') flags.push('filter: ' + x.filter);
    if (x.deadPopovers?.length) flags.push('dead popovers ' + x.deadPopovers.length);
    if (x.deadGloss?.length) flags.push('dead gloss ' + x.deadGloss.join(','));
    if (x.brokenModalTriggers?.length) flags.push('modal trigger → nothing: ' + x.brokenModalTriggers.join(','));
    if (x.unlabelled?.length) flags.push('unlabelled controls ' + x.unlabelled.length);
    if (x.modalGloss?.result && x.modalGloss.result !== 'visible') flags.push('gloss in modal: ' + x.modalGloss.result);
    if (x.errs.length) flags.push('JS errors ' + x.errs.length);
    if (x.bad.length) flags.push('failed requests ' + x.bad.length);
    if (flags.length) flagged++;
    console.log(`${x.name.padEnd(20)} ${String(x.words).padStart(5)}w  pop=${x.popovers} modal=${x.modalTriggers} gloss=${x.gloss}  ${flags.join(' | ') || 'ok'}`);
  }
  process.exit(flagged ? 1 : 0);
})();
