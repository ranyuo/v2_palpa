// Correcciones QA 04/11/2026 en navegador: clave temporal, acceso denegado auditado y aviso opcional en Mesa de Partes.
// Requiere el servidor de pruebas (README) sobre la base cargada por tests/intranet.php.
const{chromium}=require('playwright'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),path=require('node:path');
const base=process.env.PORTAL_BASE_URL||'http://127.0.0.1:8084',root=path.resolve(__dirname,'..');
const php=code=>execFileSync('php',['-r',`ob_start();require ${JSON.stringify(root+'/portal-private/bootstrap.php')};$app=portal_app();$db=$app->db();${code}`],{env:process.env}).toString().trim().split('\n').pop();
(async()=>{const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});let checks=0;const ok=(v,m)=>{assert.ok(v,m);checks++;console.log('OK '+m);};
 const login=async(page,u,p)=>{await page.goto(base+'/#/admin');await page.fill('#lg-u',u);await page.fill('#lg-p',p);await page.keyboard.press('Enter');await page.waitForTimeout(1500);};
 // Cuenta de estudiante con clave temporal creada por el servidor.
 const temp=php(`$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);$s=$app->bootstrap()['data']['academico']['estudiantes'][2];$db->prepare('DELETE FROM intranet_users WHERE username=?')->execute([$s['dni']]);$r=$app->accountOperation(['action'=>'student','student_id'=>$s['id']]);ob_end_clean();echo $s['dni'].' '.$r['temporary_password'];`).split(' ');
 const[dni,pass]=temp;ok(!pass.includes('Demo'),'La clave temporal no tiene prefijo fijo');
 const student=await browser.newPage();await login(student,dni,pass);await student.waitForURL(/accounts\.php/,{timeout:8000});
 ok(/contraseña temporal/i.test(await student.locator('body').innerText()),'El primer ingreso redirige al cambio de contraseña');
 const blocked=await student.evaluate(async()=>{const csrf=document.querySelector('input[name=_csrf]').value;const r=await fetch('/?route=api/eventos',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:'{}'});const s=await fetch('/?route=api/consultas/expedientes',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:'{}'});return [r.status,s.status,(await s.json()).message];});
 ok(blocked[1]===403&&/contraseña temporal/.test(blocked[2]),'Con la clave temporal el servidor bloquea otras operaciones');
 await student.fill('input[name=current_password]',pass);await student.fill('form:has(input[name=current_password]) input[name=password]','ClaveDefinitiva-2026');await student.fill('form:has(input[name=current_password]) input[name=password_confirmation]','ClaveDefinitiva-2026');
 await student.locator('form:has(input[name=current_password]) button').click();await student.waitForURL(/#\/admin\/dashboard/,{timeout:8000});await student.waitForTimeout(1000);
 ok(!student.url().includes('accounts.php'),'Tras cambiar la clave entra a su panel');
 // Acceso denegado registrado en el servidor.
 const before=+php(`echo $db->query("SELECT COUNT(*) FROM intranet_audit WHERE action='Acceso denegado'")->fetchColumn();`);
 await student.goto(base+'/#/admin/usuarios');await student.waitForTimeout(1500);ok(/Acceso denegado/.test(await student.locator('main').innerText()),'Un estudiante ve Acceso denegado en Usuarios');
 await student.waitForTimeout(800);const after=php(`echo $db->query("SELECT COUNT(*) FROM intranet_audit WHERE action='Acceso denegado'")->fetchColumn().'|'.$db->query("SELECT username FROM intranet_audit WHERE action='Acceso denegado' ORDER BY id DESC LIMIT 1")->fetchColumn();`).split('|');
 ok(+after[0]===before+1&&after[1]===dni,'El intento queda en la auditoría del servidor con el usuario');
 // Mesa de Partes: el aviso por correo puede omitirse en un cambio puntual.
 const admin=await browser.newPage();await login(admin,'admin','PruebaIntranet-2026!');
 const exp=php(`$app->login(['username'=>'admin','password'=>'PruebaIntranet-2026!']);$x=current($app->bootstrap()['data']['expedientes']);ob_end_clean();echo $x['id'];`);
 const count=()=>+php(`echo $db->query("SELECT COUNT(*) FROM intranet_mail WHERE source_type='expedientes' AND source_id=".$db->quote('${exp}'))->fetchColumn();`);
 const mails=count();await admin.goto(base+'/#/admin/expedientes/'+exp);await admin.waitForTimeout(1500);
 ok(await admin.isEnabled('#xm'),'La casilla de aviso por correo es editable');
 await admin.uncheck('#xm');await admin.selectOption('#xs','En proceso');await admin.fill('#xn','Corrección interna sin aviso');await admin.click('[data-xs]');await admin.waitForTimeout(2000);
 ok(count()===mails,'Sin la casilla marcada no se encola correo');
 await admin.goto(base+'/#/admin/expedientes/'+exp);await admin.reload();await admin.waitForTimeout(1500);ok(await admin.isChecked('#xm'),'La casilla vuelve a estar marcada por defecto');
 await admin.selectOption('#xs','Atendido');await admin.fill('#xn','Atendido con aviso');await admin.click('[data-xs]');await admin.waitForTimeout(2000);
 ok(count()===mails+1,'Con la casilla marcada se encola el aviso al solicitante');
 await browser.close();console.log(`${checks} comprobaciones de navegador superadas.`);
})().catch(e=>{console.error(e);process.exit(1);});
