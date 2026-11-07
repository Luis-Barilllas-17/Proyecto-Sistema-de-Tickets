// Revisión visual sobre el servidor QA aislado, sin modificar roles o estados.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const base = 'http://127.0.0.1:8017';
const output = path.resolve(__dirname, '../../docs/ajustes-interfaz');
const report = { date: new Date().toISOString(), checks: [], errors: [], httpErrors: [] };
fs.mkdirSync(output, { recursive: true });

async function login(page, profile) {
    await page.goto(`${base}/login`);
    await page.fill('#email', `qa.tickets.${profile}@zolicguate.com`);
    await page.fill('#password', 'PruebasTicket#2026');
    await Promise.all([page.waitForURL(url => !url.pathname.includes('login')), page.locator('.auth-form button[type="submit"]').click()]);
}

async function shell(page) {
    assert.equal(await page.locator('.sidebar__security').count(), 0);
    assert.equal(await page.getByText('Sesión protegida', { exact: true }).count(), 0);
    assert.equal(await page.locator('.topbar-notifications svg.notification-bell').count(), 1);
    assert.equal(await page.locator('.sidebar a[href$="/notificaciones"] svg.notification-bell').count(), 1);
    assert.equal(await page.locator('#logout-form').getAttribute('method'), 'POST');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `Desbordamiento de página: ${page.url()}`);
}

async function main() {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        const page = await context.newPage();
        page.on('pageerror', error => report.errors.push(error.message));
        page.on('response', response => {
            if (response.status() >= 400) report.httpErrors.push({ url: response.url(), status: response.status() });
        });
        await login(page, 'admin');
        for (const [name, route] of [
            ['inventario', '/inventario'], ['accesos', '/seguridad'],
            ['crear-rol', '/administracion/roles/crear'], ['estados', '/configuracion/niveles-servicio'],
        ]) {
            await page.goto(base + route);
            if (name === 'crear-rol') {
                await page.fill('#name', 'QA Coordinación de soporte institucional');
                await page.fill('#description', 'Perfil de prueba para revisar la distribución visual de los campos y los permisos de atención.');
                const active = page.locator('.form-grid--role input[type="checkbox"][name="is_active"]');
                await page.locator('.form-grid--role .switch-field').click();
                assert.equal(await active.isChecked(), false);
                await page.locator('.form-grid--role .switch-field').click();
                assert.equal(await active.isChecked(), true);
            }
            if (name === 'estados') await page.fill('#status-name', 'Estado de prueba visual QA');
            for (const width of [1600, 1440, 1280, 1024, 768, 390, 320]) {
                await page.setViewportSize({ width, height: 1000 });
                await page.waitForTimeout(120);
                await shell(page);
                const check = { page: name, width };
                if (name === 'inventario') {
                    const sizes = await page.locator('[data-horizontal-scroll]').evaluate(container => {
                        const body = container.querySelector('[data-scroll-body]');
                        const controls = container.querySelector('[data-scroll-controls]');
                        const filter = document.querySelector('.inventory-filter').getBoundingClientRect();
                        const filterFits = [...document.querySelectorAll('.inventory-filter .input-shell, .inventory-filter select, .inventory-filter .button')]
                            .every(control => { const r = control.getBoundingClientRect(); return r.left >= filter.left && r.right <= filter.right + 1; });
                        return {
                            overflow: body.scrollWidth > body.clientWidth + 1,
                            controlsVisible: !controls.hidden,
                            filterFits,
                            allColumnsVisible: [...body.querySelectorAll('th')].every(cell => getComputedStyle(cell).display !== 'none'),
                        };
                    });
                    assert.equal(sizes.controlsVisible, sizes.overflow);
                    assert.equal(sizes.filterFits, true);
                    assert.equal(sizes.allColumnsVisible, true);
                    Object.assign(check, sizes);
                    if (sizes.overflow) {
                        await page.locator('[data-scroll-direction="1"]').click();
                        await page.waitForTimeout(80);
                        let positions = await page.locator('[data-horizontal-scroll]').evaluate(el => ({
                            top: el.querySelector('[data-scroll-top]').scrollLeft, body: el.querySelector('[data-scroll-body]').scrollLeft,
                        }));
                        assert.ok(positions.body > 0);
                        assert.ok(Math.abs(positions.top - positions.body) <= 1);
                        await page.locator('[data-scroll-top]').evaluate(el => { el.scrollLeft = 0; });
                        await page.waitForTimeout(80);
                        assert.equal(await page.locator('[data-scroll-body]').evaluate(el => el.scrollLeft), 0);
                        check.twoWayScroll = true;
                    }
                }
                if (name === 'accesos') {
                    const positions = await page.locator('.security-users-table tbody tr').evaluateAll(rows => rows.map(row => ({
                        password: row.querySelector('.table-actions > a').getBoundingClientRect().left,
                        status: row.querySelector('.status-form select').getBoundingClientRect().left,
                    })));
                    assert.ok(positions.length > 1);
                    assert.ok(positions.every(item => Math.abs(item.password - positions[0].password) < 1 && Math.abs(item.status - positions[0].status) < 1));
                    check.actionsAligned = true;
                }
                if (name === 'crear-rol') {
                    const geometry = await page.locator('.role-identity-panel').evaluate(panel => {
                        const r = panel.getBoundingClientRect();
                        const fields = [...panel.querySelectorAll('.field')];
                        return {
                            fieldsFit: fields.every(field => { const b = field.getBoundingClientRect(); return b.left > r.left && b.right < r.right; }),
                            labelsAligned: Math.abs(fields[0].getBoundingClientRect().top - fields[1].getBoundingClientRect().top) < 1,
                            activeBelow: panel.querySelector('.switch-field').getBoundingClientRect().top >= Math.max(...fields.map(field => field.getBoundingClientRect().bottom)) - 1,
                        };
                    });
                    assert.equal(geometry.fieldsFit, true);
                    if (width >= 768) assert.equal(geometry.labelsAligned, true);
                    assert.equal(geometry.activeBelow, true);
                    Object.assign(check, geometry);
                }
                if (name === 'estados') {
                    const fits = await page.locator('.status-editor-form').evaluate(form => {
                        const panel = form.closest('.panel').getBoundingClientRect();
                        const button = form.querySelector('button[type="submit"]').getBoundingClientRect();
                        return button.left >= panel.left + 12 && button.right <= panel.right - 12 && button.bottom < panel.bottom;
                    });
                    assert.equal(fits, true);
                    check.buttonFitsPanel = true;
                }
                if ([1280, 390].includes(width)) {
                    const target = name === 'inventario' ? page.locator('.inventory-catalog')
                        : name === 'accesos' ? page.locator('#usuarios')
                            : name === 'crear-rol' ? page.locator('.role-identity-panel') : page.locator('#estados');
                    await target.screenshot({ path: path.join(output, `${name}-${width}.png`) });
                }
                report.checks.push(check);
            }
        }
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto(base + '/inventario');
        await page.fill('#inventory-search', 'ZOLIC-PC-001');
        await Promise.all([page.waitForNavigation(), page.locator('.inventory-filter .button--primary').click()]);
        assert.equal(await page.locator('.inventory-table tbody tr').count(), 1);
        assert.ok((await page.locator('.inventory-table').innerText()).includes('ZOLIC-PC-001'));
        await Promise.all([page.waitForNavigation(), page.getByRole('link', { name: 'Limpiar', exact: true }).click()]);
        assert.equal(await page.locator('.inventory-table tbody tr').count(), 5);
        await page.locator('.topbar-notifications').click();
        await page.waitForURL('**/notificaciones**');
        assert.ok((await page.locator('h1').innerText()).includes('Centro de notificaciones'));
        await page.screenshot({ path: path.join(output, 'campana-y-navegacion.png'), fullPage: false });
        await context.close();

        for (const profile of ['tecnico', 'usuario']) {
            const ctx = await browser.newContext();
            const tab = await ctx.newPage();
            await login(tab, profile);
            await tab.goto(base + '/tickets/crear');
            await shell(tab);
            report.checks.push({ page: 'menu-y-campana', profile, passed: true });
            await ctx.close();
        }
        assert.deepEqual(report.errors, []);
        assert.deepEqual(report.httpErrors, []);
        report.passed = true;
    } finally {
        fs.writeFileSync(path.join(output, 'resultado.json'), JSON.stringify(report, null, 2) + '\n');
        await browser.close();
    }
    console.log(JSON.stringify({ passed: true, checks: report.checks.length, output }));
}

main().catch(error => { console.error(error); process.exitCode = 1; });
