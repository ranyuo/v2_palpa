const {chromium}=require('playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const base=process.env.PORTAL_BASE_URL||'http://127.0.0.1:8084';assert(['127.0.0.1','localhost'].includes(new URL(base).hostname));
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.route('https://**/*',r=>r.abort());await page.goto(base+'/');
  const urls=await page.locator('script[src],link[rel=stylesheet]').evaluateAll(nodes=>nodes.map(n=>n.src||n.href));
  for(const name of ['intranet.css','portal-server.js','intranet.js','academic-documents.js','standalone-services.js']){
   const url=urls.map(s=>new URL(s)).find(u=>u.pathname==='/'+name);assert(url,'Recurso presente: '+name);
   const bytes=fs.readFileSync(path.join(__dirname,'../public_html',name));
   assert.equal(url.searchParams.get('v'),crypto.createHash('sha256').update(bytes).digest('hex').slice(0,12));
   const response=await page.request.get(url.href);assert.equal(response.status(),200);assert((await response.body()).equals(bytes));
  }
  await page.locator('[data-login]').waitFor();assert.deepEqual(errors,[]);
  console.log('5 recursos de aplicación versionados por contenido, servidos íntegros y sin errores JavaScript.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
