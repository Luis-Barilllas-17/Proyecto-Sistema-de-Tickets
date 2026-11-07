const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const fixture = JSON.parse(fs.readFileSync(path.join(root, 'storage/framework/testing/ticket-forms/detail-fixture.json')));
const output = path.join(root, 'docs/pruebas-tickets');
const base = 'http://127.0.0.1:8017';
const url = `${base}/tickets/${fixture.ticket}`;
const report = { date: new Date().toISOString(), ticket: fixture.ticket, checks: [], errors: [], nativeDialogs: [] };

async function login(browser, profile) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    page.on('pageerror', error => report.errors.push(`${profile}: ${error.message}`));
    page.on('dialog', async dialog => { report.nativeDialogs.push(dialog.message()); await dialog.dismiss(); });
    await page.goto(`${base}/login`);
    await page.fill('#email', `qa.tickets.${profile}@zolicguate.com`);
    await page.fill('#password', 'PruebasTicket#2026');
    await Promise.all([page.waitForURL(value => !value.pathname.includes('login')), page.locator('button[type="submit"]').click()]);
    await page.goto(url);
    return { context, page };
}
async function navigateClick(page, button) { await Promise.all([page.waitForNavigation(), button.click()]); }
async function selectByText(page, selector, text) {
    const value = await page.locator(`${selector} option`).evaluateAll((options, prefix) => options.find(item => item.textContent.trim().startsWith(prefix))?.value, text);
    assert.ok(value, `Opción no encontrada: ${text}`);
    await page.selectOption(selector, value);
}
async function lookup(page, id, term, match) {
    await page.fill(`#${id}-search`, term);
    await page.locator(`#${id}-results [role=option]`).filter({ hasText: match }).first().click();
}
async function postMessage(page, body, internal) {
    const toggle = page.locator('[data-message-private]');
    await toggle.setChecked(internal);
    await page.fill('#comment_body', body);
    await navigateClick(page, page.locator('[data-message-submit]'));
}
async function solve(page, text) {
    if (await page.locator('[data-solution-editor]').isHidden()) await page.locator('[data-edit-solution]').click();
    await page.fill('#ticket-solution', text);
    await navigateClick(page, page.locator('[data-solution-editor] button[type=submit], [data-solution-editor] .button--primary'));
}
async function closeByUser(page) {
    await page.goto(url);
    await page.getByRole('radio', { name: '4 estrellas de 5', exact: true }).check();
    await page.check('#service-confirmation');
    await navigateClick(page, page.getByRole('button', { name: 'Aceptar y cerrar', exact: true }));
    assert.equal(await page.locator('[data-star-rating]').count(), 0);
}
async function reopen(page, action, cancelFirst = false) {
    await page.goto(url);
    const form = page.locator('form[data-reopen-consumed]');
    await form.locator('..').locator('summary').click();
    await page.fill('#ticket-reopen-reason', 'Revisión QA: se recibió el material y se verificó la incidencia.');
    await form.getByRole('button', { name: 'Reabrir atención' }).click();
    const modal = page.locator('[data-confirm-dialog][open]');
    await modal.waitFor();
    if (cancelFirst) {
        await modal.locator('[data-confirm-cancel]').click();
        assert.equal(await page.locator('.ticket-readonly').count(), 1);
        await form.getByRole('button', { name: 'Reabrir atención' }).click();
    }
    await modal.locator(`[name=confirm-stock-action][value=${action}]`).check();
    await modal.screenshot({ path: path.join(output, `flujo-reabrir-${action}.png`) });
    await navigateClick(page, modal.locator('[data-confirm-accept]'));
    assert.equal(await page.locator('.ticket-readonly').count(), 0);
    assert.equal(await page.locator('[data-star-rating]').count(), 0);
}

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const admin = await login(browser, 'admin');
        const page = admin.page;
        assert.equal(await page.locator('[data-star-rating]').count(), 0);
        assert.equal(await page.locator('[data-reopen-consumed]').count(), 0);
        assert.equal(await page.locator('[data-solution-editor]').isVisible(), true);
        assert.match(await page.locator('#ticket-assignment').innerText(), /Sin técnico asignado/);
        assert.equal(await page.locator('[data-confirm-dialog]').count(), 1);
        const assignment = page.locator('#ticket-assignment details');
        await assignment.locator('summary').click();
        await selectByText(page, '#assignment-area', 'Soporte Técnico');
        await selectByText(page, '#assignment-group', 'Mesa de Ayuda');
        await selectByText(page, '#assignment-assignee', 'Técnico QA Tickets');
        await page.fill('#assignment-transfer-reason', 'Atención manual del punto de red por técnico QA.');
        await navigateClick(page, assignment.getByRole('button', { name: 'Confirmar asignación', exact: true }));
        assert.match(await page.locator('#ticket-assignment').innerText(), /Técnico QA Tickets/);
        assert.match(await page.locator('#ticket-assignment').innerText(), /Reasignar técnico/);
        assert.equal(await page.locator('[data-assignment-form]').count(), 1);
        await page.locator('#ticket-assignment').screenshot({ path: path.join(output, 'flujo-asignacion-unificada.png') });
        report.checks.push('Sin calificación ni reapertura antes de solución; asignación manual a técnico, sin confundir solicitante ni grupo.');

        for (const width of [1440, 1280, 1024, 768, 390, 320]) {
            await page.setViewportSize({ width, height: 1000 });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `Overflow a ${width}`);
        }
        await page.setViewportSize({ width: 1440, height: 1000 });
        await lookup(page, 'ticket-supply', 'RJ45', 'INS-RJ45');
        await page.fill('#ticket-supply-quantity', '2');
        await page.fill('#ticket-supply-notes', 'Conectores de red para la prueba del ciclo.');
        await page.locator('.ticket-supplies-card').screenshot({ path: path.join(output, 'flujo-insumos-compactos.png') });
        const height = await page.locator('.ticket-supply-form').evaluate(form => form.getBoundingClientRect().height);
        assert.ok(height < 260, `Formulario de insumos demasiado alto: ${height}`);
        await navigateClick(page, page.locator('.ticket-supply-form > button'));
        await postMessage(page, 'QA-COMENTARIO-PUBLICO: se verificará el punto de red.', false);
        await postMessage(page, 'QA-NOTA-PRIVADA: revisar cableado y conectores.', true);
        await page.locator('#conversation').screenshot({ path: path.join(output, 'flujo-comentario-nota-switch.png') });
        assert.equal(await page.locator('#internal-note-body').count(), 0);
        const relate = page.locator('details').filter({ has: page.locator('#related-ticket-search') });
        await relate.locator('summary').click();
        await page.fill('#related-ticket-search', 'QA-DET');
        await page.locator('#related-ticket-results [role=option]').first().waitFor();
        await relate.screenshot({ path: path.join(output, 'flujo-busqueda-tickets.png') });
        const order = await page.locator('.ticket-workspace__side').evaluate(side => [...side.children].map(child => child.id || child.className));
        assert.ok(order.indexOf('ticket-files') < order.findIndex(value => value.includes('third-party-card')));
        report.checks.push('Insumos compactos sin desbordamientos en seis anchos; un solo editor público/privado; búsqueda de tickets y archivos antes de terceros.');

        const tech = await login(browser, 'tecnico');
        const response = tech.page.locator('.ticket-assignment-response');
        await navigateClick(tech.page, response.getByRole('button', { name: 'Aceptar', exact: true }));
        await solve(tech.page, 'Se reemplazaron dos conectores y se comprobó la conectividad estable del equipo.');
        assert.equal(await tech.page.locator('[data-solution-editor]').isHidden(), true);
        await tech.page.locator('[data-edit-solution]').click();
        await tech.page.locator('#ticket-resolution').screenshot({ path: path.join(output, 'flujo-editar-solucion.png') });
        await solve(tech.page, 'Se reemplazaron dos conectores, se certificó el enlace y se validó el servicio con el usuario.');
        const user = await login(browser, 'usuario');
        assert.equal(await user.page.locator('#conversation').getByText('QA-NOTA-PRIVADA', { exact: false }).count(), 0);
        assert.match(await user.page.locator('#conversation').innerText(), /QA-COMENTARIO-PUBLICO/);
        assert.equal(await user.page.locator('[data-message-private]').count(), 0);
        assert.equal(await user.page.locator('[data-star-rating]').count(), 1);
        assert.equal(await user.page.locator('[data-edit-solution]').count(), 0);
        await closeByUser(user.page);
        await page.goto(url);
        assert.equal(await page.locator('[data-message-composer], [data-assignment-form], [data-solution-editor], .ticket-supply-form').count(), 0);
        await page.screenshot({ path: path.join(output, 'flujo-cerrado-solo-lectura.png'), fullPage: true });
        report.checks.push('Técnico acepta, registra y edita solución; solicitante no ve nota interna; califica solo tras solución; cierre bloquea formularios incluso del administrador.');

        await reopen(page, 'return', true);
        assert.match(await page.locator('.ticket-supply-list').innerText(), /Devuelto al inventario/);
        await lookup(page, 'ticket-supply', 'RJ45', 'INS-RJ45');
        await page.fill('#ticket-supply-quantity', '1');
        await navigateClick(page, page.locator('.ticket-supply-form > button'));
        await tech.page.goto(url);
        await solve(tech.page, 'Se verificó nuevamente la conexión y se realizó una reparación adicional con un conector.');
        await closeByUser(user.page);
        await reopen(page, 'keep');
        assert.match(await page.locator('.ticket-supply-list').innerText(), /Consumido/);
        await page.setViewportSize({ width: 390, height: 844 });
        await page.locator('#conversation').screenshot({ path: path.join(output, 'flujo-comentarios-movil.png') });
        report.checks.push('Modal propio: cancelar conserva el cierre; devolver restituyó el material; segunda reserva/cierre y reapertura manteniendo consumidos; ningún diálogo nativo.');
        assert.deepEqual(report.errors, []);
        assert.deepEqual(report.nativeDialogs, []);
        console.log(JSON.stringify(report, null, 2));
    } finally {
        fs.writeFileSync(path.join(output, 'resultado-flujo-ticket.json'), JSON.stringify(report, null, 2));
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
