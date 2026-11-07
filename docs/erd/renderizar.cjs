// Renderizador de documentación; no carga Laravel ni consulta la base instalada.
// node docs/erd/renderizar.cjs [ruta al paquete @viz-js/viz]
// Vista esencial por defecto; añadir --complete para recuperar el modelo completo.
const fs = require('node:fs');
const path = require('node:path');
const { instance } = require(process.argv.slice(2).find(value => !value.startsWith('--')) || '@viz-js/viz');
const complete = process.argv.includes('--complete');
const data = JSON.parse(fs.readFileSync(path.join(__dirname, 'esquema.json'), 'utf8'));
const tables = data.tables;
const esc = text => String(text).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
const quote = value => JSON.stringify(value);
const primary = table => table.indexes.find(index => index.primary)?.columns || [];
const unique = (table, columns) => table.indexes.some(index => index.unique && [...index.columns].sort().join(',') === [...columns].sort().join(','));
const keys = (table, column) => [
  primary(table).includes(column) && 'PK',
  table.foreign_keys.some(fk => fk.columns.includes(column)) && 'FK',
  !primary(table).includes(column) && unique(table, [column]) && 'UK',
].filter(Boolean).join(', ');

function graph(owned, edges, compact = false, allFields = false) {
  const names = [...new Set([...owned, ...edges.map(edge => edge.fk.foreign_table)])];
  const dot = [
    'digraph er {',
    'graph [rankdir=LR, bgcolor="white", pad="0.35", nodesep="0.45", ranksep="1.25", splines=polyline, outputorder=edgesfirst];',
    'node [shape=plain, fontname="Arial", fontsize=13];',
    'edge [fontname="Arial", fontsize=11, color="#73849A", fontcolor="#294866", arrowsize=0.65, arrowhead=vee];',
  ];
  for (const name of names) {
    const table = tables[name];
    const local = owned.includes(name);
    let columns = allFields ? table.columns : compact ? [] : table.columns.filter(column => local
      ? keys(table, column.name) || ['name', 'number', 'code', 'title', 'status', 'notifiable_id', 'notifiable_type'].includes(column.name)
      : primary(table).includes(column.name));
    // Cada nodo externo conserva su PK; no se inventan atributos de otros módulos.
    const rows = columns.map(column => {
      const badge = keys(table, column.name);
      const optional = column.nullable && !primary(table).includes(column.name);
      return `<TR><TD PORT="${esc(column.name)}" ALIGN="LEFT"><FONT COLOR="#263B50">${esc(column.name)}${optional ? ' ?' : ''}${allFields ? ' : ' + esc(column.type) : ''}</FONT></TD><TD ALIGN="RIGHT"><FONT COLOR="#007777">${badge ? esc(badge) : '-'}</FONT></TD></TR>`;
    });
    const color = local ? '#003061' : '#576E83';
    const header = `<TR><TD COLSPAN="2" BGCOLOR="${color}" CELLPADDING="10"><FONT COLOR="white"><B>${esc(name)}</B></FONT></TD></TR>`;
    dot.push(`${quote(name)} [label=<<TABLE BORDER="1" COLOR="#CED8E3" CELLBORDER="0" CELLSPACING="0" CELLPADDING="6" BGCOLOR="white">${header}${rows.join('')}</TABLE>>];`);
  }
  for (const { child, fk } of edges) {
    const childTable = tables[child];
    const nullable = fk.columns.some(name => childTable.columns.find(column => column.name === name).nullable);
    const identifying = fk.columns.every(column => primary(childTable).includes(column));
    const from = quote(fk.foreign_table);
    const to = quote(child);
    const label = fk.columns.join(', ') + '\n' + (nullable ? '0..1' : '1') + ' : ' + (unique(childTable, fk.columns) ? '0..1' : '0..N');
    dot.push(`${from} -> ${to} [label=${quote(label)}, style=${quote(identifying ? 'solid' : 'dashed')}];`);
  }
  dot.push('}');
  return dot.join('\n');
}

const essential = {
  roles: ['Roles', ['id', 'name']],
  users: ['Usuarios', ['id', 'name', 'email']],
  ticket_categories: ['Categorias', ['id', 'name']],
  ticket_statuses: ['Estados', ['id', 'name']],
  ticket_priorities: ['Prioridades', ['id', 'name']],
  tickets: ['Tickets', ['id', 'number', 'title', 'requester_id', 'current_assignee_id']],
  ticket_comments: ['Comentarios', ['id', 'ticket_id', 'body']],
  ticket_approvals: ['Aprobaciones', ['id', 'ticket_id', 'status']],
  development_requests: ['Desarrollo', ['id', 'ticket_id', 'project_name']],
  equipment: ['Equipos', ['id', 'inventory_code', 'name']],
  supplies: ['Suministros', ['id', 'name', 'stock_quantity']],
  maintenance_orders: ['Mantenimiento', ['id', 'equipment_id', 'scheduled_for', 'status']],
};
const essentialRelations = [
  ['users', 'tickets', 'requester_id', 'solicita'],
  ['users', 'tickets', 'current_assignee_id', 'atiende'],
  ['ticket_categories', 'tickets', 'ticket_category_id', 'clasifica'],
  ['ticket_statuses', 'tickets', 'ticket_status_id', 'estado'],
  ['ticket_priorities', 'tickets', 'ticket_priority_id', 'prioridad'],
  ['tickets', 'ticket_comments', 'ticket_id', 'comentarios'],
  ['tickets', 'ticket_approvals', 'ticket_id', 'aprobaciones'],
  ['tickets', 'development_requests', 'ticket_id', 'desarrollo'],
  ['tickets', 'maintenance_orders', 'ticket_id', 'vincula'],
  ['equipment', 'maintenance_orders', 'equipment_id', 'recibe'],
];
// Relaciones conceptuales N:M. Cada una se verifica contra su tabla puente real.
const bridges = [
  ['roles', 'users', 'role_user', 'role_id', 'user_id', 'asigna roles'],
  ['tickets', 'equipment', 'ticket_equipment_links', 'ticket_id', 'equipment_id', 'equipos afectados'],
  ['tickets', 'supplies', 'ticket_supply_usages', 'ticket_id', 'supply_id', 'utiliza'],
];

function summaryGraph() {
  const dot = ['digraph esencial {',
    'graph [rankdir=LR, bgcolor="white", pad=0.3, nodesep=0.35, ranksep=0.55, splines=polyline, outputorder=edgesfirst];',
    'node [shape=plain, fontname="Arial", fontsize=14];',
    'edge [fontname="Arial", fontsize=12, color="#6C8298", fontcolor="#294866", arrowsize=0.65, arrowhead=vee];'];
  const mermaid = ['erDiagram', '    direction LR'];
  for (const [name, [title, fields]] of Object.entries(essential)) {
    if (!tables[name] || fields.some(field => !tables[name].columns.some(column => column.name === field))) throw new Error('Entidad o campo inexistente: ' + name);
    const rows = fields.map(field => `<TR><TD ALIGN="LEFT">${esc(field)}</TD><TD ALIGN="RIGHT"><FONT COLOR="#007777">${keys(tables[name], field) || '-'}</FONT></TD></TR>`).join('');
    const color = name === 'tickets' ? '#007777' : '#003061';
    dot.push(`${quote(name)} [label=<<TABLE BORDER="1" COLOR="#CAD7E3" CELLBORDER="0" CELLSPACING="0" CELLPADDING="6" BGCOLOR="white"><TR><TD COLSPAN="2" BGCOLOR="${color}" CELLPADDING="10"><FONT COLOR="white" POINT-SIZE="17"><B>${title}</B></FONT><BR/><FONT COLOR="#DDE9F4" POINT-SIZE="12">${name}</FONT></TD></TR>${rows}</TABLE>>];`);
    mermaid.push('    ' + name + ' {');
    for (const field of fields) {
      const column = tables[name].columns.find(item => item.name === field);
      mermaid.push('        ' + column.type.replace(/[^a-zA-Z0-9_]/g, '_') + ' ' + field + (keys(tables[name], field) ? ' ' + keys(tables[name], field) : ''));
    }
    mermaid.push('    }');
  }
  for (const [parent, child, field, label] of essentialRelations) {
    const table = tables[child];
    const fk = table.foreign_keys.find(item => item.foreign_table === parent && item.columns.length === 1 && item.columns[0] === field);
    if (!fk) throw new Error('Relación no declarada: ' + child + '.' + field);
    const optional = table.columns.find(column => column.name === field).nullable;
    const single = unique(table, [field]);
    const cardinality = (optional ? '0..1' : '1') + ' : ' + (single ? '0..1' : '0..N');
    dot.push(`${quote(parent)} -> ${quote(child)} [label=${quote(label + '\n' + cardinality)}, tooltip=${quote(child + '.' + field)}, style=solid];`);
    mermaid.push('    ' + parent + (optional ? ' |o' : ' ||') + '..' + (single ? 'o|' : 'o{') + ' ' + child + ' : "' + label + '"');
  }
  for (const [parent, child, bridge, parentKey, childKey, label] of bridges) {
    const table = tables[bridge];
    if (!table || !table.foreign_keys.some(fk => fk.foreign_table === parent && fk.columns[0] === parentKey) || !table.foreign_keys.some(fk => fk.foreign_table === child && fk.columns[0] === childKey)) throw new Error('Puente no verificado: ' + bridge);
    dot.push(`${quote(parent)} -> ${quote(child)} [label=${quote(label + '\n0..N : 0..N')}, style=dashed, color="#007777", tooltip=${quote('Relacion N:M mediante ' + bridge)}];`);
    mermaid.push('    ' + parent + ' }o..o{ ' + child + ' : "' + bridge + '"');
  }
  dot.push('}');
  fs.writeFileSync(path.join(__dirname, 'resumen.mmd'), mermaid.join('\n') + '\n');
  return dot.join('\n');
}

async function main() {
  const viz = await instance();
  const owned = Object.keys(tables);
  const edges = owned.flatMap(child => tables[child].foreign_keys.map(fk => ({ child, fk })));
  const result = viz.render(complete ? graph(owned, edges, false, true) : summaryGraph(), { format: 'svg', engine: 'dot' });
  if (result.status !== 'success' || result.errors.length) throw new Error(JSON.stringify(result.errors));
  const nodeCount = (result.output.match(/class="node"/g) || []).length;
  const edgeCount = (result.output.match(/class="edge"/g) || []).length;
  const columnCount = complete ? Object.values(tables).reduce((n, table) => n + table.columns.length, 0) : Object.values(essential).reduce((n, [, fields]) => n + fields.length, 0);
  if (complete ? nodeCount !== data.metadata.table_count || edgeCount !== data.metadata.foreign_key_count || columnCount !== data.metadata.column_count : nodeCount !== Object.keys(essential).length || edgeCount !== essentialRelations.length + bridges.length) throw new Error('Cobertura incompleta');
  const heading = complete ? 'Base completa' : 'Modelo esencial';
  const filename = complete ? 'completo.svg' : 'resumen.svg';
  const subtitle = complete ? `${nodeCount} tablas · ${columnCount} columnas · ${edgeCount} claves foráneas` : `${nodeCount} entidades principales · ${edgeCount} relaciones esenciales`;
  let svg = result.output.slice(result.output.indexOf('<svg'));
  svg = svg.replaceAll('>Categorias</text>', '>Categorías</text>');
  svg = svg.replace('<svg ', '<svg role="img" aria-labelledby="er-title" ');
  svg = svg.replace(/(<svg[^>]*>)/, `$1\n<title id="er-title">${heading}: ${nodeCount} entidades y ${edgeCount} relaciones</title>`);
  fs.writeFileSync(path.join(__dirname, filename), svg);
  const dimensions = svg.match(/viewBox="0\.00 0\.00 ([\d.]+) ([\d.]+)"/);
  if (!dimensions) throw new Error('SVG sin dimensiones');
  const width = Number(dimensions[1]), height = Number(dimensions[2]);
  const html = `<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagrama ER · ${heading} · ZOLIC</title>
<style>
*{box-sizing:border-box}body{margin:0;height:100vh;height:100dvh;display:grid;grid-template-rows:auto minmax(0,1fr) auto;font:14px/1.4 system-ui,sans-serif;color:#183D60;background:white}header{padding:12px 18px;background:#003061;color:white;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}h1{font-size:20px;margin:0}header p{margin:4px 0 0;font-size:13px;color:#D9E6F1}.toolbar{display:flex;align-items:center;gap:6px;flex-wrap:wrap}button,a{font:inherit}button,.toolbar a{padding:8px 12px;background:white;border:1px solid #BDCFDF;border-radius:5px;color:#003061;cursor:pointer;text-decoration:none}button:focus-visible,a:focus-visible,main:focus-visible{outline:3px solid #EAA340;outline-offset:2px}output{min-width:60px;text-align:center}main{position:relative;overflow:hidden;background:#F3F6F9;cursor:grab;touch-action:none}main.dragging{cursor:grabbing;user-select:none}#canvas{position:absolute;left:0;top:0;transform-origin:0 0;background:white}#canvas svg{display:block;width:${width}px;height:${height}px}footer{padding:8px 16px;border-top:1px solid #D3DFE9;font-size:12px;color:#476078;display:flex;gap:6px 22px;flex-wrap:wrap}@media(max-width:640px){h1{font-size:17px}header{padding:10px}footer{font-size:11px}}@media print{body{height:auto;display:block}header,footer{display:none}main{overflow:visible}#canvas{position:static;transform:none!important}#canvas svg{width:100%;height:auto}}
</style></head><body>
<header><div><h1>Diagrama entidad–relación · ${heading}</h1><p>${subtitle}</p></div><div class="toolbar" role="group" aria-label="Zoom del diagrama"><button id="out" type="button" aria-label="Alejar">−</button><output id="zoom" aria-live="polite"></output><button id="in" type="button" aria-label="Acercar">+</button><button id="fit" type="button">Ver todo</button><button id="actual" type="button">100 %</button><a href="erd/${filename}" download="diagrama-er-${complete ? 'completo' : 'resumido'}.svg">Descargar SVG</a></div></header>
<main id="viewport" tabindex="0" aria-label="Diagrama entidad relación. Arrastra para desplazar. Usa la rueda o los botones para ampliar. Teclado: flechas para mover; más y menos para ampliar; Inicio para ver todo."><div id="canvas">${svg}</div></main>
<footer>${complete ? '<span>PK: primaria · FK: foránea · UK: única · ?: acepta NULL</span><span>Flecha: padre → hijo · Cardinalidad: padre : hijo · 0..N: cero o muchos</span><span>Continua: identificadora · Discontinua: no identificadora</span><span>Esquema de las migraciones; tipos normalizados. No incluye migrations ni cambios manuales.</span>' : '<span>PK: primaria · FK: foránea · UK: única</span><span>1: uno · 0..1: opcional · 0..N: cero o muchos · Cardinalidad: origen : destino</span><span>Continua: FK directa · Discontinua: N:M mediante tabla puente</span><span>Vista conceptual resumida; se omiten entidades y campos secundarios. Los técnicos y administradores son usuarios con roles.</span>'}</footer>
<script>
const viewport=document.getElementById('viewport'),canvas=document.getElementById('canvas'),display=document.getElementById('zoom');
const width=${width},height=${height};let scale=1,x=0,y=0,drag=null;
function paint(){canvas.style.transform='translate('+x+'px,'+y+'px) scale('+scale+')';display.textContent=Math.round(scale*1000)/10+' %';}
function fit(){scale=Math.min((viewport.clientWidth-40)/width,(viewport.clientHeight-40)/height,1);x=(viewport.clientWidth-width*scale)/2;y=(viewport.clientHeight-height*scale)/2;paint();}
function zoom(next,px=viewport.clientWidth/2,py=viewport.clientHeight/2){next=Math.max(0.001,Math.min(3,next));const ratio=next/scale;x=px-(px-x)*ratio;y=py-(py-y)*ratio;scale=next;paint();}
document.getElementById('in').onclick=()=>zoom(scale*1.4);document.getElementById('out').onclick=()=>zoom(scale/1.4);document.getElementById('fit').onclick=fit;document.getElementById('actual').onclick=()=>zoom(1);
viewport.addEventListener('wheel',event=>{event.preventDefault();const rect=viewport.getBoundingClientRect();zoom(scale*Math.exp(-event.deltaY*0.0015),event.clientX-rect.left,event.clientY-rect.top);},{passive:false});
viewport.addEventListener('pointerdown',event=>{if(event.button!==0||drag)return;drag={id:event.pointerId,x:event.clientX,y:event.clientY};viewport.setPointerCapture(event.pointerId);viewport.classList.add('dragging');});
viewport.addEventListener('pointermove',event=>{if(!drag||drag.id!==event.pointerId)return;x+=event.clientX-drag.x;y+=event.clientY-drag.y;drag.x=event.clientX;drag.y=event.clientY;paint();});
function release(event){if(!drag||drag.id!==event.pointerId)return;drag=null;viewport.classList.remove('dragging');if(viewport.hasPointerCapture(event.pointerId))viewport.releasePointerCapture(event.pointerId);}
viewport.addEventListener('pointerup',release);viewport.addEventListener('pointercancel',release);viewport.addEventListener('lostpointercapture',release);
viewport.addEventListener('keydown',event=>{const moves={ArrowLeft:[60,0],ArrowRight:[-60,0],ArrowUp:[0,60],ArrowDown:[0,-60]};if(moves[event.key]){event.preventDefault();x+=moves[event.key][0];y+=moves[event.key][1];paint();}else if(event.key==='+'||event.key==='='){event.preventDefault();zoom(scale*1.4);}else if(event.key==='-'){event.preventDefault();zoom(scale/1.4);}else if(event.key==='Home'){event.preventDefault();fit();}});
new ResizeObserver(fit).observe(viewport);fit();
</script></body></html>`;
  fs.writeFileSync(path.join(__dirname, '..', 'DIAGRAMA_ENTIDAD_RELACION.html'), html);
  if (!complete) {
    const md = ['# Diagrama entidad–relación resumido — ZOLIC', '',
      'Un único diagrama con las **12 entidades principales** y **13 relaciones esenciales** del sistema.', '',
      '[Abrir diagrama](DIAGRAMA_ENTIDAD_RELACION.html) · [Imagen SVG](erd/resumen.svg)', '',
      'Se omiten tablas auxiliares, auditoría y campos secundarios. Los administradores y técnicos son usuarios con roles. Las relaciones N:M condensan `role_user`, `ticket_equipment_links` y `ticket_supply_usages`; no representan claves foráneas directas.', '',
      '```mermaid', fs.readFileSync(path.join(__dirname, 'resumen.mmd'), 'utf8').trim(), '```', '',
      'Actualizar: `php docs/erd/generar.php` y luego `node docs/erd/renderizar.cjs /ruta/a/@viz-js/viz`. Para renderizar el modelo completo se puede añadir `--complete`.', ''];
    fs.writeFileSync(path.join(__dirname, '..', 'DIAGRAMA_ENTIDAD_RELACION.md'), md.join('\n'));
  }
  console.log(JSON.stringify({ diagrams: 1, tables: nodeCount, columns: columnCount, relationships: edgeCount, width, height }));
}
main().catch(error => { console.error(error); process.exitCode = 1; });
