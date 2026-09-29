#!/usr/bin/env node
/**
 * Chromium CDP print-to-PDF with reliable page numbers for Admin list exports.
 *
 * Usage:
 *   node chrome-print-pdf.mjs <chromePath> <htmlPath> <pdfPath> [landscape=1]
 */
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { writeFile } from 'node:fs/promises';
import { setTimeout as sleep } from 'node:timers/promises';
import { pathToFileURL } from 'node:url';

const [chromePath, htmlPath, pdfPath, landscapeArg = '1'] = process.argv.slice(2);

if (!chromePath || !htmlPath || !pdfPath) {
    console.error('Usage: chrome-print-pdf.mjs <chromePath> <htmlPath> <pdfPath> [landscape=1]');
    process.exit(2);
}

const landscape = landscapeArg !== '0' && landscapeArg !== 'false';
const port = 9200 + Math.floor(Math.random() * 700);
const useSandboxBypass = process.env.CHROME_NO_SANDBOX === '1'
    || process.env.CHROME_NO_SANDBOX === 'true'
    || process.env.CHROME_NO_SANDBOX === 'TRUE'
    || existsSync('/.dockerenv');

const chrome = spawn(chromePath, [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-extensions',
    '--disable-translate',
    '--hide-scrollbars',
    ...(useSandboxBypass
        ? ['--no-sandbox', '--disable-dev-shm-usage', '--disable-setuid-sandbox']
        : []),
    'about:blank',
], {
    stdio: ['ignore', 'ignore', 'pipe'],
});

let chromeErr = '';
chrome.stderr.on('data', (chunk) => {
    chromeErr += chunk.toString();
});

async function waitForDebugger(timeoutMs = 20000) {
    const started = Date.now();
    while (Date.now() - started < timeoutMs) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/json/version`);
            if (response.ok) {
                return response.json();
            }
        } catch {
            // retry until Chrome is ready
        }
        await sleep(100);
    }
    throw new Error(`Chrome debugger did not start on port ${port}. ${chromeErr}`);
}

function createCdp(wsUrl) {
    const ws = new WebSocket(wsUrl);
    let nextId = 1;
    const pending = new Map();
    const eventWaiters = new Map();

    const ready = new Promise((resolve, reject) => {
        ws.addEventListener('open', resolve, { once: true });
        ws.addEventListener('error', (error) => reject(error), { once: true });
    });

    ws.addEventListener('message', (event) => {
        const message = JSON.parse(String(event.data));

        if (message.id && pending.has(message.id)) {
            const { resolve, reject } = pending.get(message.id);
            pending.delete(message.id);
            if (message.error) {
                reject(new Error(message.error.message || JSON.stringify(message.error)));
            } else {
                resolve(message.result);
            }
            return;
        }

        if (message.method && eventWaiters.has(message.method)) {
            const waiters = eventWaiters.get(message.method);
            eventWaiters.delete(message.method);
            for (const resolve of waiters) {
                resolve(message.params || {});
            }
        }
    });

    function waitForEvent(method, timeoutMs = 30000) {
        return new Promise((resolve, reject) => {
            const timeout = setTimeout(() => {
                reject(new Error(`Timed out waiting for CDP event ${method}`));
            }, timeoutMs);

            if (!eventWaiters.has(method)) {
                eventWaiters.set(method, []);
            }

            eventWaiters.get(method).push((params) => {
                clearTimeout(timeout);
                resolve(params);
            });
        });
    }

    async function send(method, params = {}, sessionId) {
        await ready;
        const id = nextId++;
        const payload = { id, method, params };
        if (sessionId) {
            payload.sessionId = sessionId;
        }

        return new Promise((resolve, reject) => {
            pending.set(id, { resolve, reject });
            ws.send(JSON.stringify(payload));
        });
    }

    function close() {
        try {
            ws.close();
        } catch {
            // ignore
        }
    }

    return { send, waitForEvent, close, ready };
}

let exitCode = 1;

try {
    const version = await waitForDebugger();
    const browser = createCdp(version.webSocketDebuggerUrl);
    await browser.ready;

    const { targetId } = await browser.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await browser.send('Target.attachToTarget', {
        targetId,
        flatten: true,
    });

    const send = (method, params = {}) => browser.send(method, params, sessionId);

    await send('Page.enable');

    const fileUrl = pathToFileURL(htmlPath).href;
    const loadPromise = browser.waitForEvent('Page.loadEventFired');
    await send('Page.navigate', { url: fileUrl });
    await loadPromise;
    await sleep(200);

    const footerTemplate = `
      <div style="width:100%;font-size:8px;color:#6b6560;padding:0 10mm;box-sizing:border-box;
                  font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
                  display:flex;justify-content:space-between;align-items:center;">
        <span style="text-transform:uppercase;letter-spacing:0.08em;">Confidential</span>
        <span>Page <span class="pageNumber"></span></span>
        <span>Rosewood Royale</span>
      </div>
    `;

    const result = await send('Page.printToPDF', {
        landscape,
        printBackground: true,
        preferCSSPageSize: true,
        displayHeaderFooter: true,
        headerTemplate: '<div></div>',
        footerTemplate,
        marginTop: 0,
        marginBottom: 0,
        marginLeft: 0,
        marginRight: 0,
    });

    await writeFile(pdfPath, Buffer.from(result.data, 'base64'));
    await browser.send('Target.closeTarget', { targetId }).catch(() => null);
    browser.close();
    exitCode = 0;
} catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    exitCode = 1;
} finally {
    try {
        chrome.kill('SIGTERM');
    } catch {
        // ignore
    }
    // Ensure Chrome exits even if SIGTERM is ignored.
    await sleep(300);
    try {
        chrome.kill('SIGKILL');
    } catch {
        // ignore
    }
    process.exit(exitCode);
}
