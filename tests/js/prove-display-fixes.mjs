/**
 * Browser proof for the footer image script and the series date link.
 *
 * Run: node tests/js/prove-display-fixes.mjs assets/js/events-display.js .cp-sync-events cp-sync-event__placeholder
 *
 * Prints one JSON object and exits 0 only when every case passes.
 */

import { spawn } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const scriptArg = process.argv[2] || 'assets/js/events-display.js';
const rootSelector = process.argv[3] || '.cp-sync-events';
const placeholderClass = process.argv[4] || 'cp-sync-event__placeholder';
const imagesOnly = process.argv[5] === 'images-only';
const scriptPath = path.resolve(root, scriptArg);
const cssPath = path.resolve(root, 'assets/css/events.css');
const port = 8765 + Math.floor(Math.random() * 1000);
const debugPort = 9400 + Math.floor(Math.random() * 200);
const gif = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');

const server = http.createServer((request, response) => {
	if (request.url && request.url.startsWith('/slow-ok')) {
		setTimeout(() => {
			response.writeHead(200, {
				'Content-Type': 'image/gif',
				'Cache-Control': 'no-store',
			});
			response.end(gif);
		}, 1200);
		return;
	}

	response.writeHead(404, { 'Cache-Control': 'no-store' });
	response.end('missing');
});

await new Promise((resolve) => server.listen(port, '127.0.0.1', resolve));

const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'cp-sync-chrome-'));
const chrome = spawn('google-chrome', [
	'--headless=new',
	'--no-sandbox',
	'--disable-gpu',
	'--disable-dev-shm-usage',
	`--remote-debugging-port=${debugPort}`,
	`--user-data-dir=${profile}`,
	'about:blank',
], { stdio: 'ignore' });

function delay(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

async function browserVersion() {
	for (let attempt = 0; attempt < 40; attempt += 1) {
		try {
			const response = await fetch(`http://127.0.0.1:${debugPort}/json/version`);
			if (response.ok) {
				return response.json();
			}
		} catch (error) {
			await delay(100);
		}
	}

	throw new Error('Chrome debugging port did not open');
}

const version = await browserVersion();
const listResponse = await fetch(`http://127.0.0.1:${debugPort}/json/list`);
const targets = await listResponse.json();
const page = targets.find((target) => target.type === 'page');
const socket = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
	socket.addEventListener('open', resolve);
	socket.addEventListener('error', reject);
});

let messageId = 0;
const pending = new Map();

socket.addEventListener('message', (event) => {
	const message = JSON.parse(event.data);
	if (message.id && pending.has(message.id)) {
		const waiter = pending.get(message.id);
		pending.delete(message.id);
		if (message.error) {
			waiter.reject(new Error(JSON.stringify(message.error)));
		} else {
			waiter.resolve(message.result);
		}
	}
});

function send(method, params = {}) {
	const id = ++messageId;
	socket.send(JSON.stringify({ id, method, params }));
	return new Promise((resolve, reject) => {
		pending.set(id, { resolve, reject });
	});
}

await send('Runtime.enable');
await send('Page.enable');

const html = `<!doctype html>
<meta charset="utf-8">
<title>display proof</title>
<link rel="stylesheet" href="${pathToFileURL(cssPath).href}">
<style>
  body { margin: 0; font-family: Georgia, serif; }
  #narrow { width: 320px; }
  .media { width: 160px; }
</style>
<main class="${rootSelector.slice(1)} cp-sync-display">
  <div id="bad-media" class="media"></div>
  <div id="good-media" class="media"></div>
  <div id="slow-media" class="media"></div>
  <div id="later-media" class="media"></div>
  <div id="narrow">
    <h3 class="cp-sync-series__date-title" id="short"><a href="#short">December 6</a></h3>
    <h3 class="cp-sync-series__date-title" id="long"><a href="#long">December 13 service with a long title that wraps instead of being clipped on a narrow screen</a></h3>
  </div>
</main>
<script>
const rootSelector = ${JSON.stringify(rootSelector)};
const placeholderClass = ${JSON.stringify(placeholderClass)};
const scriptUrl = ${JSON.stringify(pathToFileURL(scriptPath).href)};
const slowUrl = ${JSON.stringify(`http://127.0.0.1:${port}/slow-ok.gif?${Date.now()}`)};
const goodUrl = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
const badUrl = 'data:image/gif;base64,AAAA';

function media(id) {
  return document.getElementById(id);
}

function addImage(id, src) {
  const image = document.createElement('img');
  image.alt = id;
  image.src = src;
  media(id).appendChild(image);
  return image;
}

function until(image, ready) {
  return new Promise((resolve) => {
    const check = () => {
      if (ready()) {
        resolve();
      }
    };
    image.addEventListener('load', check);
    image.addEventListener('error', check);
    check();
  });
}

const bad = addImage('bad-media', badUrl);
const good = addImage('good-media', goodUrl);
const slow = addImage('slow-media', slowUrl);

Promise.all([
  until(bad, () => bad.complete && bad.naturalWidth === 0),
  until(good, () => good.complete && good.naturalWidth > 0),
]).then(() => {
  if (slow.complete) {
    throw new Error('slow image finished before the footer script');
  }
  const script = document.createElement('script');
  script.src = scriptUrl;
  script.onload = () => {
    const immediate = {
      badReplaced: !media('bad-media').querySelector('img') && !!media('bad-media').querySelector('.' + placeholderClass),
      goodKept: !!media('good-media').querySelector('img') && !media('good-media').querySelector('.' + placeholderClass) && media('good-media').querySelector('img').naturalWidth > 0,
      slowKept: !!media('slow-media').querySelector('img') && !media('slow-media').querySelector('.' + placeholderClass) && slow.complete === false,
    };
    until(slow, () => slow.complete && slow.naturalWidth > 0).then(() => {
      const afterSuccess = {
        slowStillImage: !!media('slow-media').querySelector('img') && !media('slow-media').querySelector('.' + placeholderClass),
      };
      const later = addImage('later-media', badUrl);
      until(later, () => !later.isConnected || (later.complete && later.naturalWidth === 0)).then(() => {
        const laterReplaced = !media('later-media').querySelector('img') && !!media('later-media').querySelector('.' + placeholderClass);
        const shortLink = document.querySelector('#short a');
        const longLink = document.querySelector('#long a');
        shortLink.focus();
        const focused = getComputedStyle(shortLink);
        const shortBox = shortLink.getBoundingClientRect();
        const longBox = longLink.getBoundingClientRect();
        const narrow = document.getElementById('narrow').getBoundingClientRect();
        document.documentElement.dataset.proof = JSON.stringify({
          immediate,
          afterSuccess,
          laterReplaced,
          link: {
            display: getComputedStyle(longLink).display,
            shortHeight: shortBox.height,
            shortWidth: shortBox.width,
            longHeight: longBox.height,
            longRight: longBox.right,
            narrowRight: narrow.right,
            outlineWidth: focused.outlineWidth,
            outlineStyle: focused.outlineStyle,
            pageOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
          },
        });
      });
    });
  };
  document.body.appendChild(script);
});
</script>`;

const htmlFile = path.join(profile, 'proof.html');
fs.writeFileSync(htmlFile, html);
await send('Page.navigate', { url: pathToFileURL(htmlFile).href });

let proof = null;
for (let attempt = 0; attempt < 40; attempt += 1) {
	await delay(250);
	const evaluated = await send('Runtime.evaluate', {
		expression: 'document.documentElement.dataset.proof || ""',
		returnByValue: true,
	});
	if (evaluated.result && evaluated.result.value) {
		proof = JSON.parse(evaluated.result.value);
		break;
	}
}

if (!proof) {
	chrome.kill('SIGKILL');
	server.close();
	console.error(JSON.stringify({ ok: false, error: 'proof did not finish' }));
	process.exit(1);
}

try {
	await send('Emulation.setFocusEmulationEnabled', { enabled: true });
} catch (error) {
	proof.focusEmulation = String(error);
}

const focusEval = await send('Runtime.evaluate', {
	expression: `(() => {
		const link = document.querySelector('#short a');
		link.focus();
		const style = getComputedStyle(link);
		return {
			active: document.activeElement === link,
			matchesFocus: link.matches(':focus'),
			matchesVisible: link.matches(':focus-visible'),
			outlineStyle: style.outlineStyle,
			outlineWidth: style.outlineWidth,
			outlineColor: style.outlineColor,
		};
	})()`,
	returnByValue: true,
});
proof.focus = focusEval.result.value;
chrome.kill('SIGKILL');
server.close();

const link = proof.link;
const focus = proof.focus || {};
const failures = [];

if (!proof.immediate.badReplaced) failures.push('fast-fail before footer script was not replaced');
if (!proof.immediate.goodKept) failures.push('loaded image was replaced or missing');
if (!proof.immediate.slowKept) failures.push('in-flight image was replaced before it loaded');
if (!proof.afterSuccess.slowStillImage) failures.push('image that finished loading gained a fallback');
if (!proof.laterReplaced) failures.push('error after the script started was not replaced');
if (!imagesOnly && link.display !== 'inline-flex' && link.display !== 'inline-block') failures.push('date link display is ' + link.display);
if (!imagesOnly && link.shortHeight < 44) failures.push('short link height ' + link.shortHeight);
if (!imagesOnly && link.shortWidth < 44) failures.push('short link width ' + link.shortWidth);
if (!imagesOnly && link.longHeight < 44) failures.push('long link height ' + link.longHeight);
if (!imagesOnly && link.longHeight < link.shortHeight + 10) failures.push('long title did not wrap: height ' + link.longHeight);
if (!imagesOnly && link.longRight > link.narrowRight + 1) failures.push('long link overflowed the narrow column');
if (!imagesOnly && link.pageOverflow) failures.push('page overflowed horizontally');
if (!imagesOnly && !focus.matchesFocus) failures.push('date link did not take keyboard focus');
if (!imagesOnly && (focus.outlineStyle === 'none' || parseFloat(focus.outlineWidth) < 3)) failures.push('focus outline ' + focus.outlineStyle + ' ' + focus.outlineWidth);

const report = { ok: failures.length === 0, script: scriptArg, failures, proof };
console.log(JSON.stringify(report, null, 2));
process.exit(report.ok ? 0 : 1);
