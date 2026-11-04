const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const config=process.env.PORTAL_CONFIG_FILE;
assert(config&&path.basename(config)==='config.test.php','Solo configuración de pruebas');
const base=process.env.PORTAL_BASE_URL||'http://127.0.0.1:8084';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
const settings=path.join(path.dirname(config),'.smtp-settings.json');
const previous=fs.existsSync(settings)?fs.readFileSync(settings):null;
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const page=await browser.newPage({viewport:{width:1440,height:1100}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const login=async user=>{await page.goto(base+'/');await page.fill('#lg-u',user);await page.fill('#lg-p','PruebaIntranet-2026!');await page.locator('[data-login] button[type=submit]').click();await page.locator('.intra-welcome').waitFor();};
 try{
  await login('admin');await page.goto(base+'/#/admin/config');const form=page.locator('[data-smtp-form]');await form.waitFor();
  await form.locator('[data-smtp-gmail]').click();assert.equal(await form.locator('[name=host]').inputValue(),'smtp.gmail.com');assert.equal(await form.locator('[name=username]').inputValue(),'notificaciones@proyectos.pe');
  await form.locator('[name=enabled]').uncheck();await form.locator('[name=password]').fill('solo-prueba-navegador');
  const save=async()=>{const response=page.waitForResponse(r=>r.request().method()==='PUT'&&r.url().includes('api%2Fsmtp'));await form.locator('[type=submit]').click();const r=await response;assert.equal(r.status(),200);const body=await r.json();assert(!('password' in body));assert(!JSON.stringify(body).includes('solo-prueba-navegador'));};
  await save();assert.equal(await form.locator('[name=password]').inputValue(),'');
  await page.reload();assert.equal(await form.locator('[name=password]').inputValue(),'');assert.match(await form.locator('[data-smtp-password-info]').innerText(),/Contraseña guardada/);
  await form.locator('[name=from_name]').fill('Mesa de Partes QA');await save();
  const raw=fs.readFileSync(settings,'utf8');assert(!raw.includes('solo-prueba-navegador'));
  const denied=await page.evaluate(async()=>{const r=await fetch('/index.php?route=api%2Fsmtp',{method:'PUT',headers:{'Content-Type':'application/json'},body:'{}'});return r.status;});assert.equal(denied,419);
  await page.setViewportSize({width:390,height:844});assert(await form.evaluate(el=>el.scrollWidth<=el.clientWidth+1));
  await page.locator('[data-logout]').click();await page.locator('[data-login]').waitFor();await login('atencion');
  assert.equal(await page.evaluate(async()=> (await fetch('/index.php?route=api%2Fsmtp')).status),403);
  assert.deepEqual(errors,[]);console.log('Formulario SMTP: guardado protegido, contraseña oculta y conservada, CSRF, permisos y móvil aprobados.');
 }finally{await browser.close();if(previous!==null)fs.writeFileSync(settings,previous,{mode:0o600});else if(fs.existsSync(settings))fs.unlinkSync(settings);}
})().catch(e=>{console.error(e);process.exit(1)});
