const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const base=process.env.PORTAL_BASE_URL||'http://127.0.0.1:8084';
assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.goto(base+'/bolsa-laboral.php#/servicios/bolsa-laboral?registro=1');
  const form=page.locator('[data-form=regEmp]');await form.waitFor();
  const root='20'+String(Date.now()%100000000).padStart(8,'0');const weights=[5,4,3,2,7,6,5,4,3,2];
  let digit=11-weights.reduce((sum,w,i)=>sum+w*Number(root[i]),0)%11;if(digit===10)digit=0;if(digit===11)digit=1;
  const ruc=root+digit,password='PruebaEmpresa-2026!';
  for(const [name,value] of Object.entries({ruc,nom:'Empresa QA recuperación',sector:'Tecnología',ubic:'Palpa',dir:'Dirección QA',contacto:'Contacto QA',cargoC:'Responsable',correo:'recuperacion-'+ruc+'@example.test',tel:'900000076',pass:password}))await form.locator('[name='+name+']').fill(value);
  await form.locator('#empDecl').check();let calls=0,finished;
  const registered=new Promise(resolve=>finished=resolve);
  await page.route('**/index.php?route=api%2Fempresas',async route=>{
   calls++;const response=await route.fetch();assert.equal(response.status(),201);
   const result=await response.json();assert.equal(result.username,ruc);assert(!JSON.stringify(result).includes(password));
   await route.abort('failed');finished();
  });
  await form.locator('[type=submit]').click();await registered;
  await page.waitForFunction(()=>!document.querySelector('[data-form=regEmp]').dataset.sending);
  assert.equal(calls,1);assert(await form.locator('[type=submit]').isEnabled());
  assert.match(await page.locator('.portal-submission-announcer').innerText(),/ingresa con su RUC/);
  assert(await page.evaluate(()=>window.dispatchEvent(new Event('beforeunload',{cancelable:true}))));
  await page.goto(base+'/bolsa-laboral.php#/servicios/bolsa-laboral?tab=acceso');
  await page.locator('#sl-role').selectOption('empresa');await page.fill('#sl-user',ruc);await page.fill('#sl-password',password);
  await page.locator('[data-service-login] [type=submit]').click();await page.locator('[data-service-logout]').waitFor();
  assert.match(await page.locator('#service-workspace').innerText(),/Empresa QA recuperación/);assert.deepEqual(errors,[]);
  console.log('Empresa con respuesta perdida: aviso comprensible, formulario recuperado y acceso con la cuenta ya creada aprobados.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
