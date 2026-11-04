const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const base=process.env.PORTAL_BASE_URL||'http://127.0.0.1:8084';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const page=await browser.newPage();
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const go=async route=>{await page.goto(base+'/#/admin/'+route);await page.locator('main').waitFor();};
 const save=async button=>{
  const response=page.waitForResponse(r=>r.request().method()==='PUT'&&r.url().includes('api%2Fportal'));
  await button.click();assert.equal((await response).status(),200);
 };
 let original;
 try{
  await go('');await page.fill('#lg-u','admin');await page.fill('#lg-p','PruebaIntranet-2026!');
  await page.locator('[data-login] button[type=submit]').click();await page.locator('.intra-welcome').waitFor();
  await go('config');assert.match(await page.locator('[data-mail-panel]').innerText(),/notificaciones@proyectos\.pe/);original=await page.locator('[name=correoMesaPartes]').inputValue();
  await page.fill('[name=correoMesaPartes]','nuevo-destino@example.test');
  await save(page.locator('[data-sf] button').filter({hasText:'Guardar cambios'}));
  await page.reload();assert.equal(await page.locator('[name=correoMesaPartes]').inputValue(),'nuevo-destino@example.test');
  await go('expedientes/config');assert.equal(await page.locator('#mc-c').inputValue(),'nuevo-destino@example.test');
  await page.fill('#mc-c','otro-destino@example.test');
  await save(page.locator('[data-mpvcfg] button').filter({hasText:'Guardar configuración'}));
  await go('config');assert.equal(await page.locator('[name=correoMesaPartes]').inputValue(),'otro-destino@example.test');
  const reject=async value=>page.evaluate(async value=>{
   const url='/index.php?route=api%2Fportal';const b=await fetch(url).then(r=>r.json());
   b.data.config.mpv.correo=value;
   const r=await fetch(url,{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':b.csrf},body:JSON.stringify({data:b.data,revision:b.revision})});
   return r.status;
  },value);
  for(const invalid of ['', 'sin-correo', 'correo@example.test\r\nBcc: otro@example.test', ['correo@example.test']])assert.equal(await reject(invalid),422);
  await page.reload();assert.equal(await page.locator('[name=correoMesaPartes]').inputValue(),'otro-destino@example.test');
  // Restaura el ajuste antes de probar los permisos de otra cuenta.
  await page.fill('[name=correoMesaPartes]',original);
  await save(page.locator('[data-sf] button').filter({hasText:'Guardar cambios'}));original=undefined;
  await page.locator('[data-logout]').click();await page.locator('[data-login]').waitFor();
  await page.fill('#lg-u','atencion');await page.fill('#lg-p','PruebaIntranet-2026!');
  await page.locator('[data-login] button[type=submit]').click();await page.locator('.intra-welcome').waitFor();
  assert.equal(await reject('intrusion@example.test'),403);
  assert.equal(await page.evaluate(async()=> (await fetch('/index.php?route=api%2Fnotificaciones')).status),403);
  assert.deepEqual(errors,[]);
  console.log('Correo receptor: persistencia, sincronización en ambas pantallas, validación del servidor y permisos aprobados.');
 }finally{
  if(original!==undefined){await go('config');await page.fill('[name=correoMesaPartes]',original);await save(page.locator('[data-sf] button').filter({hasText:'Guardar cambios'}));}
  await browser.close();
 }
})().catch(e=>{console.error(e);process.exit(1)});
