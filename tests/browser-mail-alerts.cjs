// Panel de correo: formulario «Enviar correo de prueba» separado del formulario SMTP, mensaje comprensible y última ejecución del cron.
const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const config=process.env.PORTAL_CONFIG_FILE;
assert(config&&path.basename(config)==='config.test.php','Solo configuración de pruebas');
const base=process.env.PORTAL_BASE_URL||'http://127.0.0.1:8084';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
const settings=path.join(path.dirname(config),'.smtp-settings.json');
const previous=fs.existsSync(settings)?fs.readFileSync(settings):null;
(async()=>{
 // Sin contraseña guardada la prueba no intenta conectarse a ningún servidor externo.
 if(previous!==null)fs.renameSync(settings,settings+'.respaldo-qa');
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const page=await browser.newPage({viewport:{width:1440,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 let checks=0;const ok=()=>checks++;
 try{
  await page.goto(base+'/');await page.fill('#lg-u','admin');await page.fill('#lg-p','PruebaIntranet-2026!');await page.locator('[data-login] button[type=submit]').click();await page.locator('.intra-welcome').waitFor();
  await page.goto(base+'/#/admin/config');
  const test=page.locator('[data-smtp-test-form]');await test.waitFor();ok();
  assert.equal(await page.locator('[data-smtp-form] [data-smtp-test-form]').count(),0,'La prueba no forma parte del formulario SMTP');ok();
  assert.equal(await page.locator('[data-smtp-form] [name=to]').count(),0,'El destinatario de prueba no se envía al guardar SMTP');ok();
  assert.match(await page.locator('[data-smtp-form] .note').innerText(),/Enviar correo de prueba/);ok();
  await test.locator('[name=to]').fill('no-es-correo');await test.locator('[type=submit]').click();
  assert.equal(await test.locator('[name=to]').evaluate(i=>i.validity.valid),false,'El navegador valida el correo antes de enviar');ok();
  await test.locator('[name=to]').fill('prueba@example.test');
  const response=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('api%2Fsmtp%2Fprueba'));
  await test.locator('[type=submit]').click();const r=await response;
  assert.equal(r.status(),409);assert(r.request().headers()['x-csrf-token'],'La prueba envía el token CSRF');ok();
  await page.waitForFunction(()=>/Primero guarda la cuenta SMTP/.test(document.querySelector('[data-smtp-test-message]').textContent));ok();
  assert.equal(await test.locator('[type=submit]').isDisabled(),false,'El botón vuelve a habilitarse');ok();
  const panel=page.locator('[data-mail-panel]');await panel.locator('[data-mail-worker]').waitFor();
  assert.match(await panel.innerText(),/Última ejecución del cron de correo:/);ok();
  const denied=await page.evaluate(async()=>(await fetch('/index.php?route=api%2Fsmtp%2Fprueba',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'})).status);
  assert.equal(denied,419,'Sin token CSRF la prueba se rechaza');ok();
  await page.setViewportSize({width:390,height:900});await page.reload();await test.waitFor();
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),'Sin desplazamiento horizontal en móvil');ok();
  assert.deepEqual(errors,[]);ok();
  console.log(`${checks} comprobaciones de correo de prueba y alertas en el panel aprobadas.`);
 }finally{
  await browser.close();
  if(previous!==null)fs.renameSync(settings+'.respaldo-qa',settings);
 }
})().catch(error=>{console.error(error);process.exit(1);});
