const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const base=process.env.PORTAL_BASE_URL||'http://127.0.0.1:8084';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));let release;
 try{
  await page.goto(base+'/libro-de-reclamaciones.php');
  await page.selectOption('#l2-td','DNI');
  for(const [id,value] of [['l2-nd','97030188'],['l2-no','Persona'],['l2-ap','Reclamo QA'],['l2-e','reclamo-web@example.test'],['l2-t','900000088'],['l2-f','2026-01-01']])await page.fill('#'+id,value);
  await page.selectOption('#l2-c','Público en general');await page.selectOption('#l2-p','ninguno');
  await page.locator('.pz-tipo label.rec').click();assert(await page.locator('[name=tipo][value=Reclamo]').isChecked());
  await page.fill('[name=descripcion]','Descripción completa de prueba para validar el registro de reclamos.');
  await page.fill('[name=pedido]','Solicito una respuesta institucional.');
  await page.check('[name=declaro]');await page.check('[name=acepto]');
  const nums=(await page.locator('main').innerText()).match(/(\d+)\s*\+\s*(\d+)/);
  await page.fill('[name=captcha]',String(+nums[1]+ +nums[2]));
  let calls=0,key,original,arrived;
  const arrival=new Promise(resolve=>arrived=resolve);const gate=new Promise(resolve=>release=resolve);
  await page.route('**/index.php?route=api%2Fsolicitudes%2Freclamos',async route=>{
   calls++;const input=route.request().postDataJSON();
   if(calls===1){key=input.idempotency_key;arrived();await gate;}else assert.equal(input.idempotency_key,key);
   const response=await route.fetch();const result=await response.json();
   if(calls===1){assert.equal(response.status(),201);original=result.record;await route.abort('failed');}
   else{assert.equal(response.status(),200);assert.equal(result.record.id,original.id);await route.fulfill({response});}
  });
  await page.locator('[data-lr2] [data-send]').click();await arrival;
  await page.locator('[data-lr-progress]').waitFor({state:'visible'});
  assert(await page.locator('[data-send]').isDisabled());
  assert(await page.locator('.pz-mt [data-tab]').first().isDisabled());
  assert(await page.locator('.portal-submission-announcer').evaluate(el=>!el.closest('[aria-busy=true]')));
  assert.match(await page.locator('.portal-submission-announcer').innerText(),/Registrando reclamo/);
  assert(await page.evaluate(()=>!window.dispatchEvent(new Event('beforeunload',{cancelable:true}))));
  await page.evaluate(()=>document.querySelector('[data-lr2]').dispatchEvent(new Event('submit',{cancelable:true})));
  assert.equal(calls,1);release();
  await page.waitForFunction(()=>!document.querySelector('[data-lr2]').hasAttribute('aria-busy'));
  assert.equal(await page.locator('#l2-e').inputValue(),'reclamo-web@example.test');
  assert(await page.evaluate(()=>window.dispatchEvent(new Event('beforeunload',{cancelable:true}))));
  await page.locator('[data-lr2] [data-send]').click();await page.getByRole('dialog').waitFor();
  assert.match(await page.getByRole('dialog').innerText(),new RegExp(original.codigo));assert.equal(calls,2);
  await page.goto(base+'/');await page.fill('#lg-u','admin');await page.fill('#lg-p','PruebaIntranet-2026!');
  await page.locator('[data-login] button[type=submit]').click();await page.locator('.intra-welcome').waitFor();
  await page.goto(base+'/#/admin/config');assert.equal(await page.locator('[name=correoBolsa]').count(),1);
  const mails=await page.evaluate(async()=>fetch('/index.php?route=api%2Fnotificaciones').then(r=>r.json()));
  const receipt=mails.messages.filter(m=>m.reference===original.codigo&&m.event==='registro');
  assert(receipt.length>=1);assert.equal(new Set(receipt.map(m=>m.recipient.toLowerCase())).size,receipt.length);
  assert.deepEqual(errors,[]);
  console.log('Reclamos: progreso accesible, protección de salida, respuesta perdida, recuperación del mismo cargo y correos sin duplicados aprobados.');
 }finally{release?.();await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
