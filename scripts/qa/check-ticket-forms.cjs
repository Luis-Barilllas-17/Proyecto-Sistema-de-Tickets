// QA del formulario real en un navegador. Requiere el servidor aislado en :8017.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const output = path.join(root, 'docs/pruebas-tickets');
const base = 'http://127.0.0.1:8017';
const evidence = path.join(__dirname, 'fixtures/evidencia-ticket.txt');
const report = { date: new Date().toISOString(), base, profiles: [], errors: [], failedRequests: [] };
fs.mkdirSync(output, { recursive: true });

async function inspect(page, profile, stage) {
    const details = await page.locator('[data-ticket-form]').evaluate((form) => {
        const controls = [...form.querySelectorAll('input:not([type="hidden"]), select, textarea')];
        const ids = [...form.querySelectorAll('[id]')].map(node => node.id);
        const lastPanel = [...form.querySelectorAll('.ticket-form-card')].at(-1);
        return {
            mode: form.dataset.ticketForm,
            subjects: controls.filter(node => node.name === 'title').length,
            descriptions: controls.filter(node => node.name === 'description').length,
            files: controls.filter(node => node.type === 'file').length,
            dynamicFields: controls.filter(node => node.name.startsWith('fields[')).length,
            duplicateIds: ids.filter((id, index) => ids.indexOf(id) !== index),
            brokenLabels: [...form.querySelectorAll('label[for]')].filter(label => !document.getElementById(label.htmlFor)).map(label => label.htmlFor),
            overflow: document.documentElement.scrollWidth > innerWidth + 1,
            footerOverlapsFields: form.querySelector('.ticket-submit-bar').getBoundingClientRect().top < lastPanel.getBoundingClientRect().bottom - 1,
        };
    });
    assert.equal(details.mode, profile === 'usuario' ? 'requester' : 'staff');
    assert.equal(details.subjects, 1);
    assert.equal(details.descriptions, 1);
    assert.equal(details.files, 1);
    assert.deepEqual(details.duplicateIds, []);
    assert.deepEqual(details.brokenLabels, []);
    assert.equal(details.overflow, false, `Desbordamiento en ${profile}/${stage}`);
    assert.equal(details.footerOverlapsFields, false, `La barra de envío tapa campos en ${profile}/${stage}`);
    assert.equal(details.dynamicFields === 0, profile !== 'usuario');
    return details;
}

async function selectLevel(page, selector, text) {
    const value = await page.locator(`${selector} option`).evaluateAll((options, match) => options.find(option => option.textContent.trim().startsWith(match)).value, text);
    await page.selectOption(selector, value);
}

async function selectLookup(page, id, query, label) {
    await page.fill(`#${id}-search`, query);
    await page.locator(`#${id}-results [role="option"]`).filter({ hasText: label }).first().click();
}

async function main() {
    const browser = await chromium.launch({
        headless: true,
        ...(process.env.EDGE_EXECUTABLE ? { executablePath: process.env.EDGE_EXECUTABLE } : { channel: 'msedge' }),
    });
    try {
        for (const profile of ['admin', 'tecnico', 'usuario']) {
            const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
            const page = await context.newPage();
            page.on('pageerror', error => report.errors.push({ profile, message: error.message }));
            page.on('response', response => {
                if (response.status() >= 400) report.failedRequests.push({ profile, status: response.status(), url: response.url() });
            });
            await page.goto(`${base}/login`);
            await page.fill('#email', `qa.tickets.${profile}@zolicguate.com`);
            await page.fill('#password', 'PruebasTicket#2026');
            await Promise.all([
                page.waitForURL(url => !url.pathname.includes('login')),
                page.getByRole('button', { name: 'Ingresar al sistema' }).click(),
            ]);
            await page.goto(`${base}/tickets/crear`);
            await page.locator('.ticket-category-option').filter({ hasText: 'Soporte técnico' }).click();
            await page.selectOption('[data-subcategory-select]', { label: 'Software institucional' });
            await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Continuar' }).click()]);
            const result = { profile, checks: [] };
            result.checks.push(await inspect(page, profile, 'escritorio'));

            if (profile !== 'usuario') {
                await selectLookup(page, 'requester', 'qa.tickets.usuario', 'qa.tickets.usuario@zolicguate.com');
                assert.equal(await page.locator('#requester_area_id option:checked').innerText(), 'Operaciones');
                assert.equal(await page.locator('#requester_department_id option:checked').innerText(), 'Operaciones Portuarias');
                result.requesterProfileSynchronized = true;
            }
            await page.selectOption('#requester_area_id', { label: 'Soporte Técnico' });
            await page.selectOption('#requester_department_id', { label: 'Mesa de Ayuda' });
            const chosenArea = await page.inputValue('#requester_area_id');
            const chosenDepartment = await page.inputValue('#requester_department_id');
            result.areaAndDepartmentEditable = true;

            const title = `QA FORM ${profile.toUpperCase()} - validación de software`;
            const description = 'PRUEBA FICTICIA: la aplicación de operaciones muestra un error al guardar un registro. Ocurre en una estación de trabajo desde las 09:15. Se verificó la conexión y se solicita revisar la configuración del cliente.';
            await page.fill('#description', description);
            await page.fill('#location', 'Edificio de Operaciones, oficina de pruebas QA');
            await page.selectOption('#attention_type', 'remote');
            await selectLevel(page, '#impact_level_id', 'Medio');
            await selectLevel(page, '#urgency_level_id', 'Media');
            await selectLookup(page, 'equipment', 'ZOLIC-PC-001', 'ZOLIC-PC-001');
            await selectLookup(page, 'equipment', 'ZOLIC-PRN-003', 'ZOLIC-PRN-003');
            assert.equal(await page.locator('input[name="equipment_ids[]"]').count(), 2);
            result.multipleEquipmentSelected = true;
            if (profile === 'usuario') {
                await page.getByLabel('Cantidad de usuarios afectados').fill('3');
                await page.getByLabel('Correo', { exact: true }).check();
            }
            await page.setInputFiles('#attachments', evidence);

            await page.fill('#title', title);
            await page.setInputFiles('#attachments', evidence);
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.screenshot({ path: path.join(output, `${profile}-escritorio.png`), fullPage: true });
            for (const width of [390, 320]) {
                await page.setViewportSize({ width, height: 844 });
                result.checks.push(await inspect(page, profile, `movil-${width}`));
                if (width === 390) await page.screenshot({ path: path.join(output, `${profile}-movil.png`), fullPage: true });
            }
            await page.setViewportSize({ width: 1440, height: 1000 });
            // El envío incompleto debe regresar al mismo formulario y conservar los datos.
            await page.fill('#title', '');
            await Promise.all([page.waitForNavigation(), page.locator('button[value="submit"]').click()]);
            assert.match(page.url(), /tickets\/crear/);
            assert.equal(await page.inputValue('#description'), description);
            assert.equal(await page.locator('[data-ticket-form]').count(), 1);
            assert.equal(await page.locator('input[name="equipment_ids[]"]').count(), 2);
            assert.equal(await page.inputValue('#requester_area_id'), chosenArea);
            assert.equal(await page.inputValue('#requester_department_id'), chosenDepartment);
            assert.ok((await page.locator('body').innerText()).includes('Ingresa el asunto del ticket.'));
            result.invalidSubmissionPreservedData = true;
            await page.fill('#title', title);
            await page.setInputFiles('#attachments', evidence);
            await Promise.all([page.waitForNavigation(), page.locator('button[value="draft"]').click()]);
            assert.match(page.url(), /\/tickets\/\d+$/);
            result.ticketUrl = page.url();
            result.number = (await page.locator('body').innerText()).match(/ZT-\d{4}-\d{6}/)?.[0];
            assert.ok(result.number);
            await page.goto(`${result.ticketUrl}/editar`);
            assert.equal(await page.inputValue('#title'), title);
            assert.equal(await page.inputValue('#requester_area_id'), chosenArea);
            assert.equal(await page.inputValue('#requester_department_id'), chosenDepartment);
            assert.equal(await page.locator('input[name="equipment_ids[]"]').count(), 2);
            result.checks.push(await inspect(page, profile, 'editar-borrador'));
            await Promise.all([page.waitForNavigation(), page.locator('button[value="submit"]').click()]);
            assert.equal(page.url(), result.ticketUrl);
            result.draftEditedAndSubmitted = true;
            report.profiles.push(result);
            await context.close();
        }
        assert.deepEqual(report.errors, []);
        assert.deepEqual(report.failedRequests, []);
        report.passed = true;
    } finally {
        fs.writeFileSync(path.join(output, 'resultado-navegador.json'), JSON.stringify(report, null, 2) + '\n');
        await browser.close();
    }
    console.log(JSON.stringify(report, null, 2));
}

main().catch(error => { console.error(error); process.exitCode = 1; });
