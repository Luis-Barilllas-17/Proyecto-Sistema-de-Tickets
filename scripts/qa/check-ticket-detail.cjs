const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const fixture = JSON.parse(fs.readFileSync(path.join(root, 'storage/framework/testing/ticket-forms/detail-fixture.json')));
const output = path.join(root, 'docs/pruebas-tickets');
const base = 'http://127.0.0.1:8017';
const url = `${base}/tickets/${fixture.ticket}`;
const report = { date: new Date().toISOString(), ticket: fixture.ticket, profiles: [], checks: [], errors: [] };

async function login(browser, profile) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.on('pageerror', error => report.errors.push(`${profile}: ${error.message}`));
    await page.goto(`${base}/login`);
    await page.fill('#email', `qa.tickets.${profile}@zolicguate.com`);
    await page.fill('#password', 'PruebasTicket#2026');
    await Promise.all([page.waitForURL(value => !value.pathname.includes('login')), page.locator('button[type="submit"]').click()]);
    await page.goto(url);
    report.profiles.push(profile);
    return { context, page };
}

async function openFile(page, name) {
    await page.locator('.ticket-files [data-attachment-preview]').filter({ hasText: name }).click();
    await page.locator('[data-attachment-dialog][open]').waitFor();
    await page.waitForFunction(() => document.querySelector('[data-preview-body]').getAttribute('aria-busy') === 'false');
}

async function closeFile(page) {
    await page.locator('[data-preview-close]').click();
    assert.equal(await page.locator('dialog[open]').count(), 0);
}

async function noOverflow(page, width) {
    await page.setViewportSize({ width, height: 1000 });
    const details = await page.evaluate(() => {
        const form = document.querySelector('.ticket-supply-form');
        const box = form?.getBoundingClientRect();
        return {
            pageOverflow: document.documentElement.scrollWidth > innerWidth + 1,
            escapedControls: form ? [...form.querySelectorAll('input:not([type=hidden]), button')].filter(node => {
                const rect = node.getBoundingClientRect();
                return rect.width > 0 && (rect.left < box.left - 1 || rect.right > box.right + 1);
            }).map(node => node.name || node.textContent) : [],
        };
    });
    assert.equal(details.pageOverflow, false, `Overflow a ${width}px`);
    assert.deepEqual(details.escapedControls, [], `Controles fuera de la tarjeta a ${width}px`);
}

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const admin = await login(browser, 'admin');
        for (const width of [1440, 1280, 1024, 768, 390, 320]) await noOverflow(admin.page, width);
        await admin.page.setViewportSize({ width: 1440, height: 1000 });
        await admin.page.fill('#ticket-supply-search', 'zzzzz-sin-resultados');
        await admin.page.locator('#ticket-supply-results').getByText('No se encontraron coincidencias.').waitFor();
        await admin.page.fill('#ticket-supply-search', 'RJ45');
        await admin.page.locator('#ticket-supply-results [role=option]').filter({ hasText: 'INS-RJ45' }).click();
        assert.match(await admin.page.locator('[data-picker-selected]').innerText(), /\d+ disponibles/);
        assert.doesNotMatch(await admin.page.locator('[data-picker-selected]').innerText(), /\.00/);
        await admin.page.fill('#ticket-supply-quantity', '1.5');
        assert.equal(await admin.page.locator('#ticket-supply-quantity').evaluate(node => node.validity.stepMismatch), true);
        await admin.page.fill('#ticket-supply-quantity', '2');
        await admin.page.locator('.ticket-supply-form').screenshot({ path: path.join(output, 'detalle-insumos-desktop.png') });
        report.checks.push('Búsqueda por código, sin resultados, unidades enteras y formulario sin desbordamiento en seis anchos.');

        await openFile(admin.page, 'gastos-prueba.xlsx');
        assert.match(await admin.page.locator('[data-preview-body]').innerText(), /Conectores de red/);
        assert.equal(await admin.page.locator('[data-preview-body] img').count(), 0);
        await admin.page.locator('dialog').screenshot({ path: path.join(output, 'detalle-adjunto-xlsx.png') });
        await admin.page.setViewportSize({ width: 390, height: 844 });
        await admin.page.locator('dialog').screenshot({ path: path.join(output, 'detalle-adjunto-movil.png') });
        await admin.page.keyboard.press('Escape');
        assert.equal(await admin.page.locator('dialog[open]').count(), 0);
        await admin.page.setViewportSize({ width: 1440, height: 1000 });
        await openFile(admin.page, 'registro-prueba.txt');
        assert.match(await admin.page.locator('[data-preview-body] pre').innerText(), /<script>/);
        assert.equal(await admin.page.locator('[data-preview-body] script').count(), 0);
        await closeFile(admin.page);
        await openFile(admin.page, 'imagen-prueba.png');
        await admin.page.waitForFunction(() => document.querySelector('[data-preview-body] img')?.naturalWidth > 0);
        await closeFile(admin.page);
        await openFile(admin.page, 'evidencia-prueba.pdf');
        const pdfUrl = await admin.page.locator('[data-preview-body] iframe').getAttribute('src');
        const pdf = await admin.context.request.get(pdfUrl);
        assert.equal(pdf.status(), 200);
        assert.equal(pdf.headers()['content-type'], 'application/pdf');
        assert.equal(pdf.headers()['x-frame-options'], 'SAMEORIGIN');
        await closeFile(admin.page);
        await openFile(admin.page, 'archivo-no-compatible.zip');
        assert.match(await admin.page.locator('[data-preview-body]').innerText(), /no tiene vista previa/);
        assert.match(await admin.page.locator('[data-preview-download]').getAttribute('href'), /archivos/);
        await closeFile(admin.page);
        report.checks.push('Modal XLSX, texto sin ejecutar HTML, imagen, PDF, formato no compatible, Escape, descarga opcional y móvil.');

        const tech = await login(browser, 'tecnico');
        await tech.page.fill('#ticket-supply-search', 'Conector');
        await tech.page.locator('#ticket-supply-results [role=option]').filter({ hasText: 'RJ45' }).click();
        await tech.page.fill('#ticket-supply-quantity', '2');
        await tech.page.fill('#ticket-supply-notes', 'Dos conectores para reparación de punto de red QA.');
        await Promise.all([tech.page.waitForNavigation(), tech.page.locator('.ticket-supply-form button[type="submit"], .ticket-supply-form > button').click()]);
        assert.match(await tech.page.locator('.ticket-supply-list').innerText(), /Reservado/);
        assert.doesNotMatch(await tech.page.locator('.ticket-supply-list').innerText(), /2\.00/);
        const solution = tech.page.locator('[data-solution-editor]');
        await tech.page.fill('#ticket-solution', 'Se sustituyeron dos conectores, se certificó el punto de red y se confirmó conectividad estable.');
        await Promise.all([tech.page.waitForNavigation(), solution.getByRole('button', { name: 'Enviar solución' }).click()]);
        report.checks.push('Técnico reserva 2 unidades y registra solución mediante los formularios reales.');

        const user = await login(browser, 'usuario');
        assert.equal(await user.page.locator('.ticket-supply-form').count(), 0);
        assert.equal(await user.page.locator('.ticket-files a').filter({ hasText: 'nota-interna-prueba.txt' }).count(), 0);
        const internal = fixture.attachments['nota-interna-prueba.txt'];
        const denied = await user.context.request.get(`${url}/archivos/${internal}/vista-previa`, { headers: { Accept: 'application/json' } });
        assert.equal(denied.status(), 403);
        await openFile(user.page, 'gastos-prueba.xlsx');
        assert.match(await user.page.locator('[data-preview-body]').innerText(), /Concepto/);
        await closeFile(user.page);
        await user.page.getByRole('radio', { name: '5 estrellas de 5', exact: true }).check();
        await user.page.keyboard.press('ArrowLeft');
        assert.equal(await user.page.locator('[name=rating]:checked').inputValue(), '4');
        assert.equal(await user.page.locator('.ticket-rating__option.is-filled').count(), 4);
        await user.page.fill('#review_comment', 'La red funciona correctamente después de la reparación.');
        await user.page.locator('.ticket-review-card').screenshot({ path: path.join(output, 'detalle-calificacion-estrellas.png') });
        for (const width of [1440, 390, 320]) await noOverflow(user.page, width);
        await user.page.locator('.ticket-review-card').screenshot({ path: path.join(output, 'detalle-estrellas-movil.png') });
        await user.page.setViewportSize({ width: 1440, height: 1000 });
        await Promise.all([user.page.waitForNavigation(), user.page.getByRole('button', { name: 'Aceptar y cerrar', exact: true }).click()]);
        assert.equal(await user.page.locator('[name=rating]:checked').inputValue(), '4');
        await user.page.check('#service-confirmation');
        await Promise.all([user.page.waitForNavigation(), user.page.getByRole('button', { name: 'Aceptar y cerrar', exact: true }).click()]);
        assert.match(await user.page.locator('.ticket-supply-list').innerText(), /Consumido/);
        assert.equal(await user.page.locator('[data-star-rating]').count(), 0);
        report.checks.push('Usuario: nota interna prohibida, adjunto público legible, estrellas por teclado, valor conservado ante error, aceptación obligatoria y consumo final.');
        assert.deepEqual(report.errors, []);
        console.log(JSON.stringify(report, null, 2));
    } finally {
        fs.writeFileSync(path.join(output, 'resultado-detalle-ticket.json'), JSON.stringify(report, null, 2));
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
