const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const fixture = JSON.parse(fs.readFileSync(path.join(root, 'storage/framework/testing/ticket-forms/detail-fixture.json')));
const output = path.join(root, 'docs/pruebas-tickets');
const base = 'http://127.0.0.1:8017';
const url = `${base}/tickets/${fixture.ticket}`;
const report = { ticket: fixture.ticket, date: new Date().toISOString(), checks: [], centering: [], errors: [], nativeDialogs: [] };

async function login(browser, profile) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.on('pageerror', error => report.errors.push(error.message));
    page.on('dialog', async dialog => { report.nativeDialogs.push(dialog.message()); await dialog.dismiss(); });
    await page.goto(`${base}/login`);
    await page.fill('#email', `qa.tickets.${profile}@zolicguate.com`);
    await page.fill('#password', 'PruebasTicket#2026');
    await Promise.all([page.waitForURL(value => !value.pathname.includes('login')), page.locator('button[type=submit]').click()]);
    await page.goto(url);
    return { context, page };
}
async function submit(page, button) { await Promise.all([page.waitForNavigation(), button.click()]); }
async function centerMatrix(page, selector, name) {
    for (const [width, height] of [[1440, 1000], [1280, 720], [768, 1024], [390, 844], [320, 568]]) {
        await page.setViewportSize({ width, height });
        const rect = await page.locator(selector).evaluate(element => {
            const box = element.getBoundingClientRect();
            return { left: box.left, top: box.top, width: box.width, height: box.height,
                viewport: innerWidth, client: document.documentElement.clientWidth, viewportHeight: innerHeight };
        });
        const horizontal = Math.min(Math.abs(rect.left + rect.width / 2 - rect.viewport / 2), Math.abs(rect.left + rect.width / 2 - rect.client / 2));
        assert.ok(horizontal <= 2, `Modal ${name} sin centrar horizontalmente: ${JSON.stringify(rect)}`);
        assert.ok(Math.abs(rect.top + rect.height / 2 - height / 2) <= 2, `Modal ${name} sin centrar verticalmente: ${JSON.stringify(rect)}`);
        assert.ok(rect.top >= 0 && rect.left >= 0 && rect.width <= width && rect.height <= height);
        report.centering.push({ name, width, height, ...rect });
        if (width === 1440 || width === 390) await page.screenshot({ path: path.join(output, `revision-modal-${name}-${width}.png`) });
    }
    await page.setViewportSize({ width: 1440, height: 1000 });
}
async function solve(page, text) {
    if (await page.locator('[data-solution-editor]').isHidden()) await page.locator('[data-edit-solution]').click();
    await page.fill('#ticket-solution', text);
    await submit(page, page.locator('[data-solution-editor] .button--primary'));
}
async function close(user) {
    await user.goto(url);
    await user.getByRole('radio', { name: '4 estrellas de 5', exact: true }).check();
    await user.check('#service-confirmation');
    await submit(user, user.getByRole('button', { name: 'Aceptar y cerrar', exact: true }));
}
async function reopen(page, action, matrix) {
    await page.goto(url);
    const form = page.locator('form[data-reopen-consumed]');
    await form.locator('..').locator('summary').click();
    await page.fill('#ticket-reopen-reason', 'La incidencia reapareció y se requiere actualizar el diagnóstico.');
    await form.getByRole('button', { name: 'Reabrir atención' }).click();
    const modal = '[data-confirm-dialog][open]';
    await page.locator(modal).waitFor();
    if (matrix) await centerMatrix(page, modal, 'reabrir');
    await page.locator(`${modal} [name=confirm-stock-action][value=${action}]`).check();
    await submit(page, page.locator(`${modal} [data-confirm-accept]`));
    await page.waitForFunction(() => document.activeElement?.id === 'ticket-solution');
    assert.match(page.url(), /#ticket-resolution$/);
    assert.equal(await page.locator('[data-solution-editor]').isVisible(), true);
    assert.equal(await page.locator('[data-star-rating]').count(), 0);
    assert.match(await page.locator('#ticket-resolution').innerText(), /pendiente de actualizar/);
}

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const admin = await login(browser, 'admin');
        const page = admin.page;
        const button = page.locator('#ticket-assignment summary');
        await page.locator('#ticket-assignment').screenshot({ path: path.join(output, 'revision-boton-reasignar.png') });
        await button.click();
        assert.equal(await page.locator('[data-assignment-form]').isVisible(), true);
        await button.click();
        await page.locator('.ticket-files a').filter({ hasText: 'gastos-prueba.xlsx' }).click();
        await page.waitForFunction(() => document.querySelector('[data-preview-body]').getAttribute('aria-busy') === 'false');
        await centerMatrix(page, '[data-attachment-dialog][open]', 'adjunto');
        await page.locator('[data-preview-close]').click();
        await page.fill('#ticket-supply-search', 'RJ45');
        await page.locator('#ticket-supply-results [role=option]').filter({ hasText: 'INS-RJ45' }).click();
        await page.fill('#ticket-supply-quantity', '2');
        await submit(page, page.locator('.ticket-supply-form > button'));
        await solve(page, 'Se revisó el enlace, se sustituyeron conectores y se documentó la solución inicial.');
        await page.locator('[data-delete-solution] button').click();
        await centerMatrix(page, '[data-confirm-dialog][open]', 'eliminar');
        await page.locator('[data-confirm-cancel]').click();
        assert.equal(await page.locator('[data-delete-solution]').count(), 1);
        await page.locator('[data-delete-solution] button').click();
        await submit(page, page.locator('[data-confirm-accept]'));
        assert.equal(await page.locator('[data-delete-solution]').count(), 0);
        assert.equal(await page.locator('[data-star-rating]').count(), 0);
        assert.match(await page.locator('.ticket-supply-list').innerText(), /Reservado/);
        assert.ok(await page.locator('.ticket-files a').count() > 0);
        report.checks.push('Visor y confirmación centrados en cinco tamaños; botón de reasignación; cancelar/eliminar solución sin perder adjuntos ni reservas.');

        await solve(page, 'Se completó la revisión del punto de red y se confirmó el funcionamiento del servicio.');
        const user = await login(browser, 'usuario');
        await close(user.page);
        await page.goto(url);
        assert.equal(await page.locator('[data-delete-solution]').count(), 0);
        await reopen(page, 'return', true);
        assert.match(await page.locator('.ticket-supply-list').innerText(), /Devuelto al inventario/);
        await page.locator('#ticket-resolution').screenshot({ path: path.join(output, 'revision-reabierto-editor.png') });
        await page.locator('[data-reserve-returned]').click();
        await page.waitForFunction(() => Boolean(document.querySelector('[name=supply_id]')?.value));
        assert.equal(await page.locator('#ticket-supply-quantity').inputValue(), '2');
        // This shortcut preselects, but never silently reserves/consumes stock.
        assert.match(await page.locator('.ticket-supply-list').innerText(), /Devuelto al inventario/);
        await page.fill('#ticket-supply-quantity', '1');
        await page.locator('.ticket-supplies-card').screenshot({ path: path.join(output, 'revision-reservar-devueltos.png') });
        await submit(page, page.locator('.ticket-supply-form > button'));
        await solve(page, 'Nueva intervención: se utilizó un conector y se validó nuevamente el enlace de red.');
        assert.match(await page.locator('.ticket-supply-list').innerText(), /Reservado/);
        await close(user.page);
        await page.goto(url);
        assert.match(await page.locator('.ticket-supply-list').innerText(), /Consumido/);
        assert.match(await page.locator('.ticket-supply-list').innerText(), /1 Unidad/);
        await reopen(page, 'keep', false);
        assert.match(await page.locator('.ticket-supply-list').innerText(), /Consumido/);
        report.checks.push('Reabrir devuelve a atención y enfoca el editor; material devuelto se reserva expresamente; solución actualizada permite un nuevo cierre y consume solo una unidad; mantener consumidos no duplica movimientos.');
        assert.deepEqual(report.errors, []);
        assert.deepEqual(report.nativeDialogs, []);
        console.log(JSON.stringify(report, null, 2));
    } finally {
        fs.writeFileSync(path.join(output, 'resultado-reapertura-solucion.json'), JSON.stringify(report, null, 2));
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
