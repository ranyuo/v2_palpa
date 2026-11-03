/* Aplicación de gestión complementaria. La web oficial mantiene su propia administración. */
'use strict';
const INTRANET_WEBSITE='https://www.istpalpa.edu.pe/';
const INTRANET_MODULES=new Set(portalBootstrap.scope.modules);
MODULES.dashboard='Mi panel';MODULES.docentes='Personal docente';MODULES.cvdocente='Portafolio docente';MODULES.config='Configuración de la intranet';MODULES['ix-certificados']='Constancias y certificados';
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
CUSTOM.dashboard=function(){
 const user=currentUser(),cards=MODULE_CARDS.filter(([key])=>can(key));
 let next=null;try{next=sessionStorage.getItem('pz_next');sessionStorage.removeItem('pz_next');}catch{}
 if(next&&['estudiante','egresado'].includes(user.rol)&&/^#\/admin\/ix-bolsa(?:\/|$)/.test(next))setTimeout(()=>go(next),0);
 const support=NAV_GROUPS.slice(4).flatMap(([,items])=>items).filter(([key])=>can(key));
 const own=role().community;
 const body=`<section class="intra-welcome"><span class="intra-eyebrow">${esc(role().label)}</span><h1>Hola, ${esc(user.nombre.split(' ')[0])}</h1><p>${own?'Tus consultas y herramientas, según tu perfil.':'Gestiona los servicios académicos y administrativos del instituto.'}</p><a href="/accounts.php">Mi cuenta y contraseña →</a></section><section><h2 class="intra-section-title">Mis módulos</h2><div class="intra-modules">${cards.map(([key,title,desc,icon])=>`<a class="intra-module" href="#/admin/${key}"><span class="intra-module-icon">${ic(icon)}</span><h3>${title}</h3><p>${desc}</p><span class="intra-module-open">Abrir módulo →</span></a>`).join('')||'<p class="notice">No tienes módulos asignados. Consulta con el administrador.</p>'}</div></section>${support.length?`<section class="intra-support"><h2 class="intra-section-title">Catálogos y administración</h2><div>${support.map(([key])=>`<a href="#/admin/${key}">${esc(MODULES[key])} →</a>`).join('')}</div></section>`:''}<section class="intra-support"><h2 class="intra-section-title">Servicios para la comunidad</h2><div>${serviceLinks()}</div></section>`;
 return {html:body};
};
// Configurar la aplicación sin herramientas de publicación del sitio institucional.
CUSTOM.config=()=>singletonForm({title:'Configuración de la intranet',icon:'gear',sub:'Identidad de la aplicación y contacto para los servicios complementarios.',
 get:()=>({nombre:inst().nombre,nombreCorto:inst().nombreCorto,telefono:inst().telefono,correo:inst().correo,direccion:inst().direccion,horario:inst().horario,correoReclamos:DB.config.correoReclamos||''}),
 fields:[{n:'nombre',l:'Nombre de la institución',req:true,full:true},{n:'nombreCorto',l:'Nombre corto',req:true},{n:'telefono',l:'Teléfono'},{n:'correo',l:'Correo de contacto',t:'email',req:true},{n:'direccion',l:'Dirección',full:true},{n:'horario',l:'Horario de atención',full:true},{n:'correoReclamos',l:'Correo responsable de reclamos',t:'email'}],
 onSave:(w)=>{for(const key of ['nombre','nombreCorto','telefono','correo','direccion','horario'])DB.config.inst[key]=w[key];DB.config.correoReclamos=w.correoReclamos;DB.config.lr=DB.config.lr||{};DB.config.lr.correo=w.correoReclamos;}
});
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
