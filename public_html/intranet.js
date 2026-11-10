/* Aplicación de gestión complementaria. La web oficial mantiene su propia administración. */
'use strict';
const INTRANET_WEBSITE='https://www.istpalpa.edu.pe/';
const INTRANET_MODULES=new Set(portalBootstrap.scope.modules);
MODULES.dashboard='Mi panel';MODULES.docentes='Personal docente';MODULES.cvdocente='Portafolio docente';MODULES.config='Configuración de la intranet';MODULES['ix-certificados']='Certificación modular';
NAV_GROUPS.splice(0,NAV_GROUPS.length,
 ['Inicio',[['dashboard','home']]],
 ['Gestión académica',[['ix-horarios','calendar'],['ix-certificados','award'],['ix-titulos','grad']]],
 ['Gestión administrativa',[['expedientes','inbox'],['reclamos','book'],['ix-bolsa','briefcase']]],
 ['Perfiles profesionales',[['cvdocente','user']]],
 ['Catálogos de soporte',[['programas','grad'],['ix-alumnos','users'],['docentes','users'],['ix-config','gear']]],
 ['Administración y seguridad',[['config','gear'],['usuarios','key'],['auditoria','history'],['respaldo','database']]]);
const INTRANET_SERVICES=[
 ['servicios/mesa-de-partes','Mesa de partes','Presenta documentos y consulta el estado de tu expediente.','inbox'],
 ['servicios/libro-de-reclamaciones','Libro de reclamaciones','Registra un reclamo o consulta su atención.','book'],
 ['servicios/consulta-certificados','Validación de documentos','Consulta constancias, certificaciones y títulos registrados.','shield'],
 ['servicios/bolsa-laboral','Bolsa laboral','Consulta oportunidades laborales y acceso para empresas.','briefcase'],
 ['nosotros/plana-docente','Directorio docente','Consulta los perfiles profesionales publicados.','users']
];
const serviceLinks=()=>INTRANET_SERVICES.map(([url,label])=>`<a href="#/${url}">${esc(label)}</a>`).join('');
function intranetBrand(){return `<a class="intra-brand" href="#/admin"><img src="${logoSrc()}" alt="Logotipo de Palpa"><span><strong>IES Público Palpa</strong><small>Intranet institucional</small></span></a>`;}
headerHTML=function(){return `<header class="intra-public-header"><div class="wrap">${intranetBrand()}<nav aria-label="Accesos principales"><a href="#/servicios">Servicios en línea</a><a href="${INTRANET_WEBSITE}" target="_blank" rel="noopener">Web institucional ↗</a><a class="btn sm" href="#/admin">${currentUser()?'Mi panel':'Iniciar sesión'}</a></nav></div></header>`;};
footerHTML=function(){return `<footer class="intra-footer"><div class="wrap"><span>© ${yearNow()} IES Público Palpa · Servicios complementarios</span><nav aria-label="Información legal"><a href="#/politica-de-privacidad">Privacidad</a><a href="#/terminos-de-uso">Términos de uso</a><a href="${INTRANET_WEBSITE}" target="_blank" rel="noopener">Web institucional ↗</a></nav></div></footer>`;};
navModel=function(){return [];};
sideContact=function(){return `<aside class="side-box"><div class="pad"><strong>Atención institucional</strong><p>${esc(inst().horario)}</p><a href="mailto:${esc(inst().correo)}">${esc(inst().correo)}</a><p><a href="${INTRANET_WEBSITE}" target="_blank" rel="noopener">Ir a la web institucional ↗</a></p></div></aside>`;};
loginView=function(){return `<main class="intra-login container-fluid" id="contenido"><div class="row g-0 intra-login-grid"><section class="intra-login-story col-lg-6">${intranetBrand()}<div><span class="intra-eyebrow">Gestión académica y administrativa</span><h1>Tu comunidad.<br>Un solo espacio.</h1><p>Accede a las herramientas de trabajo y consulta del instituto con tu cuenta institucional.</p><ul><li>Horarios, constancias, certificados y títulos</li><li>Mesa de partes y libro de reclamaciones</li><li>Bolsa laboral y portafolios docentes</li></ul></div><a href="${INTRANET_WEBSITE}" target="_blank" rel="noopener">Visitar la web institucional ↗</a></section><section class="intra-login-access col-lg-6"><div class="intra-login-box"><span class="intra-eyebrow">Intranet Palpa</span><h2>Iniciar sesión</h2><p>Personal, docentes, estudiantes, egresados y empresas.</p><form data-login><div class="field"><label for="lg-u">Usuario</label><input id="lg-u" name="u" autocomplete="username" placeholder="Usuario, DNI o RUC" required></div><div class="field"><label for="lg-p">Contraseña</label><div class="intra-password"><input id="lg-p" name="p" type="password" autocomplete="current-password" required><button type="button" data-eye aria-label="Mostrar contraseña" aria-pressed="false">Ver</button></div></div><p id="lg-e" role="alert"></p><button class="btn intra-enter" type="submit">Ingresar a la intranet →</button></form><p class="intra-help">¿Necesitas una cuenta o recuperar tu acceso? Comunícate con el administrador del instituto.</p><div class="intra-service-access"><h3>¿Quieres realizar una consulta o trámite?</h3><p>Estos servicios pueden utilizarse desde la web institucional.</p>${serviceLinks()}</div></div></section></div></main>`;};
const intranetAdmShell=admShell;
admShell=function(active,content){return intranetAdmShell(active,content).replace('href="#/" target="_blank"',`href="${INTRANET_WEBSITE}" target="_blank"`).replace('Ver sitio web','Web institucional').replace('Intranet institucional · Portal Web 2026','Intranet institucional · Palpa').replace('Intranet · Panel administrativo','Gestión académica y administrativa');};
const MODULE_CARDS=[
 ['ix-horarios','Horarios académicos','Programa clases, revisa cruces y consulta tus horarios.','calendar'],
 ['ix-certificados','Constancias y certificados','Registra certificaciones y consulta el historial académico.','award'],
 ['ix-titulos','Grados y títulos','Administra y valida el registro institucional de titulados.','grad'],
 ['expedientes','Mesa de partes','Recibe, deriva y atiende expedientes con seguimiento.','inbox'],
 ['reclamos','Libro de reclamaciones','Gestiona la atención y el historial de reclamos y quejas.','book'],
 ['ix-bolsa','Bolsa laboral','Perfiles, ofertas, empresas y seguimiento de postulaciones.','briefcase'],
 ['cvdocente','Portafolio docente','Formación, experiencia, certificaciones y proyectos.','user']
];
// Textos de consulta para estudiantes y egresados: no administran estos registros.
const COMMUNITY_CARD_TEXT={'ix-horarios':'Consulta tu horario publicado.','ix-certificados':'Consulta tus certificaciones modulares e historial académico.','ix-titulos':'Consulta tu título registrado y su validación.','ix-bolsa':'Ofertas, tu perfil profesional y tus postulaciones.'};
CUSTOM.dashboard=function(){
 const user=currentUser(),cards=MODULE_CARDS.filter(([key])=>can(key));
 let next=null;try{next=sessionStorage.getItem('pz_next');sessionStorage.removeItem('pz_next');}catch{}
 if(next&&['estudiante','egresado'].includes(user.rol)&&/^#\/admin\/ix-bolsa(?:\/|$)/.test(next))setTimeout(()=>go(next),0);
 const support=NAV_GROUPS.slice(4).flatMap(([,items])=>items).filter(([key])=>can(key));
 const own=role().community;
 const body=`<section class="intra-welcome"><span class="intra-eyebrow">${esc(role().label)}</span><h1>Hola, ${esc(user.nombre.split(' ')[0])}</h1><p>${own?'Tus consultas y herramientas, según tu perfil.':'Gestiona los servicios académicos y administrativos del instituto.'}</p><a href="/accounts.php">Mi cuenta y contraseña →</a></section><section><h2 class="intra-section-title">Mis módulos</h2><div class="intra-modules">${cards.map(([key,title,desc,icon])=>`<a class="intra-module" href="#/admin/${key}"><span class="intra-module-icon">${ic(icon)}</span><h3>${title}</h3><p>${own&&COMMUNITY_CARD_TEXT[key]||desc}</p><span class="intra-module-open">Abrir módulo →</span></a>`).join('')||'<p class="notice">No tienes módulos asignados. Consulta con el administrador.</p>'}</div></section>${support.length?`<section class="intra-support"><h2 class="intra-section-title">Catálogos y administración</h2><div>${support.map(([key])=>`<a href="#/admin/${key}">${esc(MODULES[key])} →</a>`).join('')}</div></section>`:''}<section class="intra-support"><h2 class="intra-section-title">Servicios para la comunidad</h2><div>${serviceLinks()}</div></section>`;
 return {html:body};
};
// Configurar la aplicación sin herramientas de publicación del sitio institucional.
CUSTOM.config=()=>{const out=singletonForm({title:'Configuración de la intranet',icon:'gear',sub:'Identidad de la aplicación y contacto para los servicios complementarios.',
 get:()=>({nombre:inst().nombre,nombreCorto:inst().nombreCorto,telefono:inst().telefono,correo:inst().correo,direccion:inst().direccion,horario:inst().horario,correoMesaPartes:DB.config.mpv.correo,correoReclamos:DB.config.correoReclamos||'',correoBolsa:DB.config.bolsa?.correo||''}),
 fields:[{n:'nombre',l:'Nombre de la institución',req:true,full:true},{n:'nombreCorto',l:'Nombre corto',req:true},{n:'telefono',l:'Teléfono'},{n:'correo',l:'Correo de contacto',t:'email',req:true},{n:'direccion',l:'Dirección',full:true},{n:'horario',l:'Horario de atención',full:true},{n:'correoMesaPartes',l:'Correo receptor de Mesa de Partes',t:'email',req:true,max:254,hint:'Destino de los avisos de nuevos trámites. Es el mismo correo de la configuración de Mesa de Partes.'},{n:'correoReclamos',l:'Correo responsable de reclamos',t:'email'},{n:'correoBolsa',l:'Correo responsable de Bolsa Laboral',t:'email'}],
 onSave:(w,er)=>{if(Object.keys(er).length)return;for(const key of ['nombre','nombreCorto','telefono','correo','direccion','horario'])DB.config.inst[key]=w[key];DB.config.mpv.correo=w.correoMesaPartes.trim();DB.config.correoReclamos=w.correoReclamos;DB.config.lr=DB.config.lr||{};DB.config.lr.correo=w.correoReclamos;DB.config.bolsa.correo=w.correoBolsa.trim();}
});out.html+=intranetSmtpHTML()+intranetDocLookupHTML()+'<section class="panel"><h2>Notificaciones por correo</h2><div class="pb" data-mail-panel>Cargando estado del correo…</div></section>';const after=out.after;out.after=()=>{after();intranetSmtpBind();intranetSmtpTestBind();intranetDocLookupBind();intranetMailPanel();};return out;};

function intranetSmtpHTML(){return `<section class="panel" style="margin-top:20px"><h2>Configurar correo notificador (SMTP)</h2><form class="pb" data-smtp-form autocomplete="off"><p>Configura la cuenta central que enviará los avisos de Mesa de Partes, Libro de Reclamaciones y Bolsa Laboral.</p><div class="form-grid">
 <div class="field full"><label class="check"><input name="enabled" type="checkbox"> Activar envío de notificaciones por correo</label></div>
 <div class="field"><label for="smtp-host">Servidor SMTP</label><input id="smtp-host" name="host" required maxlength="253" placeholder="smtp.gmail.com"></div>
 <div class="field"><label for="smtp-port">Puerto</label><input id="smtp-port" name="port" type="number" min="1" max="65535" required></div>
 <div class="field"><label for="smtp-security">Seguridad de la conexión</label><select id="smtp-security" name="encryption"><option value="tls">STARTTLS (Gmail: puerto 587)</option><option value="ssl">SSL/TLS (puerto 465)</option></select></div>
 <div class="field"><label for="smtp-user">Correo de acceso SMTP</label><input id="smtp-user" name="username" type="email" required maxlength="254" autocomplete="off"></div>
 <div class="field"><label for="smtp-from">Correo remitente</label><input id="smtp-from" name="from_email" type="email" required maxlength="254"></div>
 <div class="field"><label for="smtp-name">Nombre visible del remitente</label><input id="smtp-name" name="from_name" required maxlength="150"></div>
 <div class="field full"><label for="smtp-password">Contraseña de aplicación de Google</label><input id="smtp-password" name="password" type="password" maxlength="512" autocomplete="new-password" placeholder="Introduce una nueva contraseña de aplicación"><small data-smtp-password-info>La contraseña guardada no se muestra.</small></div>
 </div><p data-smtp-message role="status" aria-live="polite"></p><div style="display:flex;gap:10px;flex-wrap:wrap"><button type="button" class="btn sec" data-smtp-gmail>Usar Gmail Workspace</button><button type="submit" class="btn">Guardar configuración SMTP</button></div><p class="note">Guardar no envía correos. Después de guardar, use «Enviar correo de prueba» para confirmar la cuenta. Al activar el servicio se podrán entregar los avisos pendientes. El proceso de reintentos (cron) debe estar programado en el hosting.</p></form>
 <form class="pb" data-smtp-test-form autocomplete="off" style="border-top:1px solid var(--line,#e5e7eb)"><h3 style="margin:0 0 6px">Enviar correo de prueba</h3><p>Envía un mensaje real con la configuración <b>guardada</b> para comprobar usuario, contraseña y conexión. Si deja el destinatario vacío se usará el correo de su cuenta.</p>
 <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end"><label class="field" style="flex:1;min-width:220px">Destinatario de la prueba<input type="email" name="to" maxlength="254" placeholder="correo@ejemplo.pe"></label><button type="submit" class="btn sec">Enviar correo de prueba</button></div>
 <p data-smtp-test-message role="status" aria-live="polite"></p></form></section>`;}
function intranetSmtpBind(){
 const form=document.querySelector('[data-smtp-form]');if(!form)return;
 const message=form.querySelector('[data-smtp-message]');const save=form.querySelector('[type=submit]');
 const fill=info=>{for(const key of ['host','port','encryption','username','from_email','from_name'])form.elements[key].value=info[key];form.elements.enabled.checked=info.enabled;form.elements.password.value='';form.querySelector('[data-smtp-password-info]').textContent=info.has_password?'Contraseña guardada de forma protegida. Deja el campo vacío para conservarla.':'Todavía no hay contraseña guardada. Introduce una nueva contraseña de aplicación.';};
 try{fill(portalRequest('GET','/api/smtp'));}catch(error){message.textContent=error.message;save.disabled=true;}
 form.querySelector('[data-smtp-gmail]').onclick=()=>{for(const [key,value] of Object.entries({host:'smtp.gmail.com',port:587,encryption:'tls',username:'notificaciones@proyectos.pe',from_email:'notificaciones@proyectos.pe',from_name:'Notificaciones · IES Público Palpa'}))form.elements[key].value=value;message.textContent='Datos de Gmail cargados. Introduce tu nueva contraseña de aplicación y guarda la configuración.';};
 form.onsubmit=event=>{event.preventDefault();if(!form.reportValidity())return;save.disabled=true;message.textContent='Guardando…';
  try{const input=Object.fromEntries(new FormData(form));input.enabled=form.elements.enabled.checked;input.port=Number(input.port);const result=portalRequest('PUT','/api/smtp',input);fill(result);message.textContent='Configuración SMTP guardada. '+(result.enabled?'Envío habilitado.':'Envío desactivado.');intranetMailPanel();}
  catch(error){message.textContent=error.message;}finally{save.disabled=false;}
 };
}
// La prueba usa una petición asíncrona: el servidor SMTP puede tardar varios segundos y la pantalla no debe congelarse.
function intranetSmtpTestBind(){
 const form=document.querySelector('[data-smtp-test-form]');if(!form)return;
 const message=form.querySelector('[data-smtp-test-message]');const send=form.querySelector('[type=submit]');
 form.onsubmit=async event=>{event.preventDefault();if(send.disabled||!form.reportValidity())return;
  send.disabled=true;send.setAttribute('aria-busy','true');message.className='';message.textContent='Enviando correo de prueba… puede tardar hasta 30 segundos.';
  try{const response=await fetch(portalURL('/api/smtp/prueba'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':portalBootstrap.csrf},body:JSON.stringify({to:form.elements.to.value.trim()})});
   let result={};try{result=await response.json();}catch{}
   if(!response.ok)throw new Error(result.message||'No se pudo conectar con el servidor.');
   message.className=result.ok?'ok':'notice';message.textContent=result.ok?'✓ '+result.message:'✗ La prueba falló: '+result.message;
   toast(result.ok?'Correo de prueba enviado.':'La prueba de correo falló.',result.ok?'ok':'err');intranetMailPanel();
  }catch(error){message.className='notice';message.textContent=error.message;}
  finally{send.disabled=false;send.removeAttribute('aria-busy');}
 };
}

// API de consulta de DNI/RUC para autocompletar la Mesa de Partes; el token se guarda cifrado y nunca vuelve al navegador.
function intranetDocLookupHTML(){return `<section class="panel" style="margin-top:20px"><h2>Consulta de DNI y RUC (API)</h2><form class="pb" data-idapi-form autocomplete="off"><p>Al escribir un DNI o RUC en la Mesa de Partes o en el Libro de Reclamaciones se completan los nombres o la razón social con la API configurada aquí.</p><div class="form-grid">
 <div class="field full"><label class="check"><input name="enabled" type="checkbox"> Activar autocompletado por DNI y RUC</label></div>
 <div class="field"><label for="idapi-provider">Proveedor</label><select id="idapi-provider" name="provider"></select><small data-idapi-docs></small></div>
 <div class="field"><label for="idapi-url">URL base de la API</label><input id="idapi-url" name="base_url" type="url" required maxlength="200" placeholder="https://api.migo.pe/api/v2"><small>Debe empezar con https://. Déjala como está salvo que el proveedor indique otra.</small></div>
 <div class="field full"><label for="idapi-token">Token de la API</label><input id="idapi-token" name="token" type="password" maxlength="512" autocomplete="new-password" spellcheck="false" placeholder="Pega aquí el token del proveedor"><small data-idapi-token-info>El token guardado no se muestra.</small><label class="check" data-idapi-clear hidden><input name="clear_token" type="checkbox"> Borrar el token guardado</label></div>
 <div class="field"><label for="idapi-min">Consultas por minuto por visitante</label><input id="idapi-min" name="per_minute" type="number" min="1" max="120" required></div>
 <div class="field"><label for="idapi-hour">Consultas por hora por visitante</label><input id="idapi-hour" name="per_hour" type="number" min="1" max="2000" required><small>Evitan que se use el formulario para recorrer el padrón. Cada consulta consume créditos del plan.</small></div>
 </div><p data-idapi-message role="status" aria-live="polite"></p><button type="submit" class="btn">Guardar configuración de la API</button></form>
 <form class="pb" data-idapi-test-form autocomplete="off" style="border-top:1px solid var(--line,#e5e7eb)"><h3 style="margin:0 0 6px">Probar la API</h3><p>Consulta un documento con la configuración <b>guardada</b>, aunque el autocompletado esté desactivado.</p>
 <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end"><label class="field" style="min-width:130px">Tipo<select name="tipoDoc"><option>RUC</option><option>DNI</option></select></label><label class="field" style="flex:1;min-width:180px">Número<input name="numDoc" required inputmode="numeric" maxlength="11" value="20131312955"></label><button type="submit" class="btn sec">Probar consulta</button></div>
 <p data-idapi-test-message role="status" aria-live="polite"></p></form></section>`;}
function intranetDocLookupBind(){
 const form=document.querySelector('[data-idapi-form]');if(!form)return;
 const message=form.querySelector('[data-idapi-message]'),save=form.querySelector('[type=submit]'),provider=form.elements.provider,docs=form.querySelector('[data-idapi-docs]');let providers={};
 const showDocs=()=>{const p=providers[provider.value];docs.innerHTML=p?`Documentación: <a href="${esc(p.docs)}" target="_blank" rel="noopener">${esc(p.docs)}</a>`:'';};
 const fill=info=>{providers=info.providers||{};provider.innerHTML=Object.entries(providers).map(([k,p])=>`<option value="${esc(k)}">${esc(p.label)}</option>`).join('');
  provider.value=info.provider;form.elements.base_url.value=info.base_url;form.elements.enabled.checked=info.enabled;form.elements.per_minute.value=info.per_minute;form.elements.per_hour.value=info.per_hour;form.elements.token.value='';form.elements.clear_token.checked=false;
  form.querySelector('[data-idapi-clear]').hidden=!info.has_token;form.querySelector('[data-idapi-token-info]').textContent=info.has_token?`Token guardado de forma protegida (${info.token_hint}). Deja el campo vacío para conservarlo.`:'Todavía no hay token guardado.';showDocs();};
 try{fill(portalRequest('GET','/api/identidad/config'));}catch(error){message.textContent=error.message;save.disabled=true;}
 provider.onchange=()=>{const p=providers[provider.value];if(p)form.elements.base_url.value=p.base_url;showDocs();message.textContent='Proveedor cambiado: introduce el token de este proveedor y guarda.';};
 form.onsubmit=event=>{event.preventDefault();if(!form.reportValidity())return;save.disabled=true;message.className='';message.textContent='Guardando…';
  try{const e=form.elements;const result=portalRequest('PUT','/api/identidad/config',{enabled:e.enabled.checked,provider:e.provider.value,base_url:e.base_url.value.trim(),token:e.token.value.trim(),clear_token:e.clear_token.checked,per_minute:Number(e.per_minute.value),per_hour:Number(e.per_hour.value)});
   fill(result);window.PORTAL_BOOTSTRAP.documentLookup=result.enabled&&result.has_token;message.className='ok';message.textContent='Configuración guardada. '+(result.enabled?'Autocompletado activado.':'Autocompletado desactivado.');toast('Configuración de la API guardada.','ok');}
  catch(error){message.className='notice';message.textContent=error.message;}finally{save.disabled=false;}
 };
 const test=document.querySelector('[data-idapi-test-form]'),out=test.querySelector('[data-idapi-test-message]'),run=test.querySelector('[type=submit]');
 test.elements.tipoDoc.onchange=()=>{test.elements.numDoc.maxLength=test.elements.tipoDoc.value==='DNI'?8:11;};
 test.onsubmit=async event=>{event.preventDefault();if(run.disabled||!test.reportValidity())return;run.disabled=true;run.setAttribute('aria-busy','true');out.className='';out.textContent='Consultando…';
  try{const response=await fetch(portalURL('/api/identidad/prueba'),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':portalBootstrap.csrf},body:JSON.stringify({tipoDoc:test.elements.tipoDoc.value,numDoc:test.elements.numDoc.value.trim()})});
   let result={};try{result=await response.json();}catch{}
   if(!response.ok)throw new Error(result.message||'No se pudo conectar con el servidor.');
   const r=result.record||{};out.className=result.ok?'ok':'notice';
   out.textContent=result.ok?'✓ '+result.message+' '+(r.razon||[r.nombres,r.apellidos].filter(Boolean).join(' '))+(r.estado?` (${[r.estado,r.condicion].filter(Boolean).join(' · ')})`:''):'✗ '+result.message;
  }catch(error){out.className='notice';out.textContent=error.message;}
  finally{run.disabled=false;run.removeAttribute('aria-busy');}
 };
}

function intranetMailPanel(){
 const panel=document.querySelector('[data-mail-panel]');if(!panel)return;
 try{const info=portalRequest('GET','/api/notificaciones');
  panel.innerHTML=`<p>Remitente central: <b>${esc(info.from)}</b>.</p><p>${info.configured?'SMTP habilitado para Mesa de Partes, Reclamos y Bolsa Laboral. Los avisos de seguimiento y las postulaciones se procesan mediante el cron de correo.':'Completa el formulario SMTP y activa el envío. Los avisos quedan en cola hasta habilitar el servicio.'}</p><p>El estado «Enviado» indica aceptación por el servidor de correo; revise también la bandeja de spam del destinatario.</p><p>Última ejecución del cron de correo: <b data-mail-worker>${info.worker_last_run?esc(info.worker_last_run):'sin registro'}</b>.</p>${(info.warnings||[]).map(w=>`<p class="notice" data-mail-warning role="alert">${esc(w)}</p>`).join('')}<button type="button" class="btn sec sm" data-mail-refresh>Actualizar estado</button><div class="tbl-wrap"><table class="tbl light"><thead><tr><th>Módulo / evento</th><th>Referencia</th><th>Destinatario</th><th>Estado</th><th>Intentos</th><th>Detalle</th></tr></thead><tbody>${info.messages.map(m=>`<tr><td>${esc(({expedientes:"Mesa de Partes",reclamos:"Reclamos",empresas:"Empresas",ofertas:"Ofertas",postulaciones:"Postulaciones"})[m.source_type]||m.source_type)} · ${esc(m.event)}</td><td>${esc(m.reference)}</td><td>${esc(m.recipient)}</td><td>${esc(m.status)}</td><td>${+m.attempts}</td><td>${esc(m.last_error||'')}${m.status==='Error'?` <button type="button" class="btn sm" data-mail-retry="${esc(m.id)}">Reintentar</button>`:''}</td></tr>`).join('')||'<tr><td colspan="6">No hay notificaciones registradas.</td></tr>'}</tbody></table></div>`;
  panel.querySelector('[data-mail-refresh]').onclick=intranetMailPanel;
  panel.querySelectorAll('[data-mail-retry]').forEach(button=>{button.onclick=()=>{try{portalRequest('POST','/api/notificaciones/reintentar',{id:button.dataset.mailRetry});intranetMailPanel();toast('Notificación en cola. Se procesará en la próxima ejecución del correo.','ok');}catch(error){toast(error.message,'err');}};});
 }catch(error){panel.textContent='No se pudo consultar el correo. Verifica que se haya ejecutado la actualización de la base.';}
}
// El catálogo de programas es soporte académico, sin perfiles promocionales ni brochures del sitio.
SPECS.programas.sub='Programas, duración y organización curricular para los módulos académicos.';
SPECS.programas.cols=SPECS.programas.cols.filter(c=>!['Imagen','Egresados'].includes(c.l));
SPECS.programas.sideExtra=()=>'';
const intranetProgramFields=SPECS.programas.fields;
SPECS.programas.fields=(w)=>{const fields=typeof intranetProgramFields==='function'?intranetProgramFields(w):intranetProgramFields;return fields.filter(f=>['orden','nombre','codigo','duracion','modalidad','turno','horario','titulo','coordinadorId','modulos'].includes(f.n));};
SPECS.programas.title='Programas de estudios';SPECS.programas.preview=null;
const PUBLIC_ROUTES=new Set(['servicios',...INTRANET_SERVICES.map(([route])=>route),'libro-de-reclamaciones','transparencia/libro-de-reclamaciones','politica-de-privacidad','terminos-de-uso']);
for(const key of Object.keys(ROUTES))if(!PUBLIC_ROUTES.has(key))delete ROUTES[key];
R('servicios',()=>({html:`${headerHTML()}<main class="wrap intra-services" id="contenido"><span class="intra-eyebrow">IES Público Palpa</span><h1>Servicios en línea</h1><p>Presenta documentos, consulta su seguimiento y accede a los servicios complementarios del instituto.</p><div class="intra-modules">${INTRANET_SERVICES.map(([url,title,desc,icon])=>`<a class="intra-module" href="#/${url}"><span class="intra-module-icon">${ic(icon)}</span><h2>${title}</h2><p>${desc}</p><span class="intra-module-open">Acceder →</span></a>`).join('')}</div></main>${footerHTML()}`}));
notFound=function(){return {html:`${headerHTML()}<main class="wrap intra-services" id="contenido"><h1>Esta sección pertenece a la web institucional</h1><p>La intranet reúne las herramientas de gestión académica y administrativa de Palpa.</p><a class="btn" href="#/admin">Ir a la intranet</a> <a class="btn sec" href="${INTRANET_WEBSITE}" target="_blank" rel="noopener">Visitar la web institucional ↗</a></main>${footerHTML()}`};};
const intranetRoute=route;
route=function(){const raw=location.hash.replace(/^#\/?/,'');if(!raw||raw==='inicio'){history.replaceState(null,'',location.pathname+location.search+'#/admin');}intranetRoute();
 if(location.hash.startsWith('#/admin'))document.title='Intranet Palpa · '+(currentUser()?MODULES[location.hash.split('/')[2]]||'Mi panel':'Iniciar sesión');
 // El directorio es un servicio profesional, sin navegación del antiguo portal público.
 document.querySelectorAll('a[href="#/nosotros/plana-jerarquica"],a[href="#/servicios/complementarios"]').forEach(a=>a.remove());
};

// Las herramientas de cuenta, auditoría y respaldo conservan el marco común de la intranet.
const intranetRenderAdmin=renderAdmin;
renderAdmin=function(parts,q){const out=intranetRenderAdmin(parts,q);if(currentUser()&&['auditoria','respaldo'].includes(parts[1])&&!out.html.includes('class="adm"')){const after=out.after;out.html=admShell(parts[1],out.html);out.after=()=>{bindAdmChrome();if(after)after();};}return out;};

const intranetUserFields=SPECS.usuarios.fields;
SPECS.usuarios.fields=function(work){const fields=intranetUserFields(work);for(const f of fields)if(f.n==='rol'&&Array.isArray(f.opts))f.opts=f.opts.filter(([role])=>!['imagen','transparencia'].includes(role));return fields;};
// El directorio profesional comparte los accesos de servicios, sin el menú institucional del sitio.
nosotrosSide=function(){return `<div class="side-box"><div class="pad"><h2 style="font-size:16px">Servicios en línea</h2><nav class="intra-directory-nav" aria-label="Servicios complementarios">${serviceLinks()}</nav></div></div>`;};
sidePromo=function(){return '';};
