/* Shared academic configuration and independent document consultation. */
'use strict';
// Emitted documents are registered once by the PHP transaction.
IXHOST.registerDoc=()=>{};
MODULES['ix-config']='Configuración académica general';
MODULES['ix-certificados']='Certificación modular';
MODULES.certificados='Constancias / Certificados';
for(const [,items] of NAV_GROUPS){for(let i=items.length-1;i>=0;i--)if(['programas','ix-config','certificados'].includes(items[i][0]))items.splice(i,1);}
const academicMenu=NAV_GROUPS.find(([label])=>label==='Gestión académica')[1];
academicMenu.unshift(['ix-config','gear']);academicMenu.splice(3,0,['certificados','award']);
for(const card of MODULE_CARDS)if(card[0]==='ix-certificados'){card[1]='Certificación modular';card[2]='Registra certificaciones de los módulos formativos y consulta el historial académico.';}
MODULE_CARDS.splice(2,0,['certificados','Constancias / Certificados','Registra documentos de cursos y otras actividades con sus datos de emisión.','award']);
// Explicit defaults, always constrained by server-projected role permissions.
for(const r of ['secretaria','coordinador','directivo'])if(ROLES[r]&&portalBootstrap.roles?.[r])ROLES[r].mods=portalBootstrap.roles[r].mods;

// One institutional program catalog, integrated into the shared configuration.
SPECS.programas.mod='ix-config';
SPECS.programas.sub='Configuración académica general · Programas de estudios. Este catálogo alimenta Horarios, módulos formativos y documentos.';
SPECS.programas.tabs=()=>`<div class="tabs"><a class="btn sec" href="#/admin/ix-config">← Configuración académica general</a><span class="btn">Programas de estudios</span></div>`;
const academicProgramFields=SPECS.programas.fields;
SPECS.programas.fields=w=>(typeof academicProgramFields==='function'?academicProgramFields(w):academicProgramFields).filter(f=>f.n!=='modulos');

// The verification registry remains unique. Academic records are maintained at origin.
const docSpec=SPECS.certificados;
docSpec.title='Constancias / Certificados';
docSpec.sub='Documentos de cursos y actividades. Las certificaciones modulares y los títulos se administran en sus módulos y comparten la consulta pública.';
docSpec.scope=r=>!r.ref && !/modular|título|titulo|validación de título/i.test(r.tipo||'');
docSpec.fields=[
 {n:'codigo',l:'Código del documento',req:true,unique:true},
 {n:'dni',l:'DNI del titular',req:true},
 {n:'nombres',l:'Apellidos y nombres',req:true,full:true},
 {n:'tipo',l:'Tipo de documento',t:'select',req:true,opts:['Constancia','Certificado']},
 {n:'detalle',l:'Curso, actividad o denominación',req:true,full:true},
 {n:'fecha',l:'Fecha de emisión',t:'date',req:true},
 {n:'horas',l:'Horas del documento',t:'number',min:1,max:10000},
 {n:'tipoHoras',l:'Tipo de horas',t:'select',opts:['Académicas','Lectivas']},
 {n:'programaId',l:'Programa de estudios (si corresponde)',t:'select',opts:progOpts},
 {n:'institucion',l:'Institución emisora',req:true,full:true}
];
docSpec.beforeSave=w=>{w.codigo=String(w.codigo||'').trim().toUpperCase();w.grupo='documentos';};
docSpec.cols.splice(3,0,{l:'Denominación',f:r=>esc(r.detalle||'—')});
const docValidation=docSpec.validate;
docSpec.validate=w=>{const errors=docValidation(w);if(w.horas&&(!w.tipoHoras))errors.tipoHoras='Indica si las horas son académicas o lectivas.';return errors;};

function academicDate(date){return /^\d{4}-\d{2}-\d{2}$/.test(date||'')?date.split('-').reverse().join('/'):esc(date||'—');}
function academicGroup(r){if(r.grupo)return r.grupo;return /título|titulo|validación de título/i.test(r.tipo||'')?'titulos':/modular/i.test(r.tipo||'')?'modular':'documentos';}
function academicResults(records){
 const sections=[['titulos','Grados y títulos','Grado o título'],['documentos','Constancias / Certificados','Documento'],['modular','Certificación modular','Módulo formativo']];
 return sections.map(([key,title,col])=>{const rows=records.filter(r=>academicGroup(r)===key);return `<section class="academic-result"><h2>${title} <small>(${rows.length})</small></h2><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Apellidos y nombres</th><th>${col}</th><th>Institución educativa</th></tr></thead><tbody>${rows.map(r=>`<tr><td><strong>${esc(r.nombres)}</strong><br>DNI: ${esc(r.dni||'—')}</td><td><strong>${esc(key==='modular'?(r.modulo||r.detalle):key==='documentos'?(r.detalle||r.tipo):r.tipo)}</strong><br>${key==='modular'?`Programa de estudios: ${esc(r.programa||byId('programas',r.programaId)?.nombre||'—')}<br>`:''}${key==='documentos'?`Tipo: ${esc(r.tipo)}<br>`:''}${key==='titulos'?`Nivel: ${esc(r.nivel||'Superior tecnológico')}<br>`:''}Fecha de emisión: ${academicDate(r.fecha)}<br>${r.horas?`Horas: ${esc(r.horas)} ${esc(r.tipoHoras||'')}<br>`:''}${key==='titulos'?'Código DRE / registro':'Código del documento'}: ${esc(r.codigo)}<br>${r.resolucion?`Resolución: ${esc(r.resolucion)}<br>`:''}<span class="badge ${r.estado==='Anulado'?'err':'ok'}">${esc(r.estado||'Válido')}</span>${r.motivo?`<br>${esc(r.motivo)}`:''}</td><td><strong>${esc(r.institucion||inst().nombre)}</strong>${key==='titulos'?`<br>País: ${esc(r.pais||'—')}<br>Región: ${esc(r.region||'—')}`:''}</td></tr>`).join('')||'<tr><td colspan="3">No se encontraron registros en esta sección.</td></tr>'}</tbody></table></div></section>`;}).join('');
}
R('servicios/consulta-certificados',()=>({
 html:`${headerHTML()}<main class="wrap academic-query" id="contenido"><div class="svc-strip"><span class="svc-strip-ic">${typeof sic==='function'?sic('shield',22):''}</span><div><h1>Consulta de documentos</h1><p>Consulta los registros institucionales de grados y títulos, constancias, certificados y certificación modular.</p></div></div><form id="academic-search" class="panel"><div class="academic-search-grid"><div class="field"><label for="academic-dni">Número de documento</label><input id="academic-dni" name="dni" inputmode="numeric" maxlength="8" placeholder="DNI de ocho dígitos"></div><div class="field"><label for="academic-name">Apellidos y nombres</label><input id="academic-name" name="nombre" maxlength="150" placeholder="Ingrese al menos tres caracteres"></div><div class="field"><label for="academic-code">Código del documento</label><input id="academic-code" name="codigo" maxlength="64" placeholder="Consulta por código"></div><div class="field"><label for="academic-answer" id="academic-question">Verificación de seguridad</label><input id="academic-answer" name="answer" inputmode="numeric" required autocomplete="off"></div></div><div class="btnrow"><button class="btn" type="submit">Buscar documentos</button><button class="btn sec" type="reset">Limpiar</button></div><p id="academic-error" role="alert"></p></form><section id="academic-report" hidden><div class="academic-report-head"><h2>Resultados de la consulta</h2><button class="btn sec" id="academic-print" type="button">Imprimir resultados</button></div><div id="academic-results"></div><p class="note">Fuente: registros institucionales de la intranet del IES Público Palpa.</p></section></main>${footerHTML()}`,
 after:()=>{
  const form=document.getElementById('academic-search');form.codigo.value=new URLSearchParams(location.hash.split('?')[1]||'').get('codigo')||'';let challenge=null;
  const refresh=()=>{try{challenge=portalRequest('POST','/api/desafio',{purpose:'certificados'});document.getElementById('academic-question').textContent='Verificación: '+challenge.question;form.answer.value='';}catch(e){document.getElementById('academic-error').textContent=e.message;}};
  refresh();
  form.onsubmit=e=>{e.preventDefault();const report=document.getElementById('academic-report');report.hidden=true;const error=document.getElementById('academic-error');error.textContent='';
   try{const dni=form.dni.value.trim(),name=form.nombre.value.trim(),code=form.codigo.value.trim();if(!dni&&!name&&!code)throw Error('Ingrese un documento, nombre o código.');if(dni&&!/^\d{8}$/.test(dni))throw Error('El DNI debe tener ocho dígitos.');
    if(!challenge)throw Error('La verificación no está disponible. Recargue la página.');
    const result=portalRequest('POST','/api/consultas/certificados',{reference:code||dni||name,mode:code?'codigo':dni?'dni':'nombre',proof:{challenge_id:challenge.id,answer:form.answer.value.trim()}});
    const records=result.records||[result.record];document.getElementById('academic-results').innerHTML=academicResults(records.filter(Boolean));report.hidden=false;
   }catch(ex){error.textContent=ex.message;}finally{refresh();}
  };
  form.onreset=()=>{document.getElementById('academic-report').hidden=true;document.getElementById('academic-results').innerHTML='';document.getElementById('academic-error').textContent='';setTimeout(refresh,0);};
  document.getElementById('academic-print').onclick=()=>{document.body.classList.add('academic-print');window.print();};
 }
}));
window.addEventListener('afterprint',()=>document.body.classList.remove('academic-print'));
// Select an existing student when relevant; external course participants can be entered directly.
docSpec.defaults=()=>({institucion:inst().nombre,tipoHoras:'Académicas',grupo:'documentos'});
docSpec.fields.unshift({n:'estudianteId',l:'Alumno registrado (opcional)',t:'select',opts:()=>DB.academico.estudiantes.map(e=>[e.id,e.dni+' · '+e.nom])});
docSpec.afterForm=(work,isNew,rerender,form)=>{
 const select=form.querySelector('[name="estudianteId"]');
 select.addEventListener('change',()=>{const student=DB.academico.estudiantes.find(e=>String(e.id)===select.value);if(!student)return;work.dni=student.dni;work.nombres=student.nom;work.programaId=Object.keys(DB.academico.progMap).find(k=>DB.academico.progMap[k]===student.prog)||'';form.querySelector('[name=dni]').value=work.dni;form.querySelector('[name=nombres]').value=work.nombres;form.querySelector('[name=programaId]').value=work.programaId;});
};
// Issued documents retain their payload; cancellation and its reason remain editable.
docSpec.fields.push({n:'motivo',l:'Motivo de anulación',full:true});
for(const f of docSpec.fields)if(f.n!=='motivo')f.ro=w=>!!byId('certificados',w.id)&&!!w.grupo;
const sharedDocumentAfterForm=docSpec.afterForm;
docSpec.afterForm=(work,isNew,rerender,form)=>{
 sharedDocumentAfterForm(work,isNew,rerender,form);
 if(!isNew&&work.grupo)for(const field of form.querySelectorAll('input,select,textarea'))if(!['estado','motivo'].includes(field.name)){if(field.tagName==='SELECT')field.disabled=true;else field.readOnly=true;}
};

// Embed the existing program editor list; there is still one source catalog.
IXHOST.programsView=()=>{const out=crudList(SPECS.programas);queueMicrotask(()=>{if(document.querySelector('[data-shared-programs]'))out.after?.();});return '<section data-shared-programs>'+out.html+'</section>';};

const independentTypeColumn=docSpec.cols.find(c=>c.l==='Tipo');if(independentTypeColumn)independentTypeColumn.f=r=>esc(r.tipo);
docSpec.sub+=' Los datos emitidos se conservan; para corregirlos, anule y registre una nueva emisión.';
