// Mesa de Partes: al escribir DNI o RUC se completan nombres o razón social con la respuesta del servidor.
// El proveedor externo se simula interceptando /api/identidad; no se hacen consultas reales.
const{chromium}=require('playwright'),assert=require('node:assert/strict');
const base=process.env.PORTAL_BASE_URL||'http://127.0.0.1:8084';assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;const errors=[];
try{
// Sin token configurado la ruta responde 503 y el formulario no muestra el aviso ni consulta.
const plain=await browser.newPage();plain.on('pageerror',e=>errors.push(e.message));await plain.route('https://**/*',r=>r.abort());
await plain.goto(base+'/mesa-de-partes.php');await plain.locator('[data-mpv]').waitFor();
const disabled=await plain.evaluate(async()=>{const r=await fetch('/index.php?route=api%2Fidentidad',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':PORTAL_BOOTSTRAP.csrf},body:JSON.stringify({tipoDoc:'DNI',numDoc:'46027897'})});return [r.status,PORTAL_BOOTSTRAP.documentLookup,JSON.stringify(PORTAL_BOOTSTRAP).includes('token')];});
assert.deepEqual(disabled,[503,false,false]);checks++;
let asked=0;plain.on('request',r=>{if(r.url().includes('api%2Fidentidad'))asked++;});await plain.fill('#mv-nd','46027897');await plain.waitForTimeout(300);assert.equal(asked,0);assert.equal(await plain.inputValue('#mv-no'),'');assert.equal((await plain.locator('[data-idhint]').innerText()).trim(),'');checks++;
await plain.close();

const page=await browser.newPage({viewport:{width:1280,height:1000}});page.on('pageerror',e=>errors.push(e.message));await page.route('https://**/*',r=>r.abort());
await page.addInitScript(()=>{let b;Object.defineProperty(window,'PORTAL_BOOTSTRAP',{configurable:true,get:()=>b,set:v=>{v.documentLookup=true;b=v;}});});
const seen=[];
await page.route(/route=api%2Fidentidad/,async route=>{const body=route.request().postDataJSON();seen.push(body);assert.ok(route.request().headers()['x-csrf-token']);
  const answers={'46027897':{tipoDoc:'DNI',numDoc:'46027897',nombres:'ROXANA KARINA',apellidos:'DELGADO HUAMAN'},'20131312955':{tipoDoc:'RUC',numDoc:'20131312955',razon:'SUPERINTENDENCIA NACIONAL DE ADUANAS Y DE ADMINISTRACION TRIBUTARIA - SUNAT',direccion:'AV. GARCILASO DE LA VEGA NRO. 1472 - LIMA LIMA LIMA',estado:'ACTIVO',condicion:'HABIDO'}};
  const record=answers[body.numDoc];await route.fulfill({status:record?200:404,contentType:'application/json',body:JSON.stringify(record?{record}:{message:'No se encontraron datos para ese documento. Completa los campos manualmente.'})});});
await page.goto(base+'/mesa-de-partes.php');await page.locator('[data-mpv]').waitFor();
const hint=page.locator('[data-idhint]');assert.match(await hint.innerText(),/completaremos tus datos/);checks++;

// Un DNI incompleto no consulta; al completar 8 dígitos se llenan nombres y apellidos.
await page.fill('#mv-nd','4602789');await page.waitForTimeout(200);assert.equal(seen.length,0);checks++;
await page.locator('#mv-nd').pressSequentially('7');await page.waitForFunction(()=>document.querySelector('#mv-no').value!=='');
assert.equal(await page.inputValue('#mv-no'),'ROXANA KARINA');assert.equal(await page.inputValue('#mv-ap'),'DELGADO HUAMAN');assert.deepEqual(seen[0],{tipoDoc:'DNI',numDoc:'46027897'});assert.match(await hint.innerText(),/Datos encontrados: ROXANA KARINA DELGADO HUAMAN/);checks++;
// Los campos siguen editables y no se vuelve a consultar el mismo número.
await page.fill('#mv-no','ROXANA');assert.equal(await page.inputValue('#mv-no'),'ROXANA');await page.locator('#mv-nd').dispatchEvent('input');await page.waitForTimeout(200);assert.equal(seen.length,1);checks++;
// Las letras se descartan en DNI.
await page.fill('#mv-nd','');await page.locator('#mv-nd').pressSequentially('1a2');assert.equal(await page.inputValue('#mv-nd'),'12');checks++;

// DNI sin resultados: muestra el aviso y permite completar a mano.
await page.fill('#mv-nd','99999999');await page.waitForFunction(()=>/No se encontraron datos/.test(document.querySelector('[data-idhint]').textContent));assert.match(await hint.getAttribute('class'),/pz-id-err/);checks++;

// RUC 20: cambia a persona jurídica, llena razón social y dirección.
await page.selectOption('#mv-td','RUC');await page.fill('#mv-nd','20131312955');await page.waitForFunction(()=>document.querySelector('#mv-rs').value!=='');
assert.equal(await page.locator('[name=tipoPersona]:checked').getAttribute('value'),'Persona jurídica / entidad');assert.equal(await page.inputValue('#mv-td'),'RUC');assert.equal(await page.locator('#mv-rs').isVisible(),true);
assert.match(await page.inputValue('#mv-rs'),/^SUPERINTENDENCIA NACIONAL/);assert.equal(await page.inputValue('#mv-dir'),'AV. GARCILASO DE LA VEGA NRO. 1472 - LIMA LIMA LIMA');assert.match(await hint.innerText(),/ACTIVO · HABIDO/);checks++;

// Otros documentos no consultan.
const before=seen.length;await page.selectOption('#mv-td','Pasaporte');await page.fill('#mv-nd','AB123456');await page.waitForTimeout(200);assert.equal(seen.length,before);checks++;
await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));checks++;
assert.deepEqual(errors,[]);console.log(`${checks} comprobaciones de autocompletado de DNI/RUC aprobadas.`);
}finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});
