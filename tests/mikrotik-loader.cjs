const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
(async()=>{
 let checks=0;
 for(const page of ['login','status','logout']){
  const html=fs.readFileSync(path.join(__dirname,'../mt_hotspot/'+page+'.html'),'utf8');
  assert.ok(!/<form\b/i.test(html),'No local forms');
  assert.ok(!html.includes('temporarily unavailable'));
  const script=html.match(/<script>([\s\S]*?)<\/script>/)[1];
  for(const outcome of ['success','http','network','invalid','render']){
   const status={textContent:''},retry={hidden:true,addEventListener(type,fn){this.click=fn;}};
   let written='',requests=0;
   const sandbox={URLSearchParams,console:{error(){}},document:{
    getElementById:id=>id==='pp-load-status'?status:retry,
    open(){if(outcome==='render')throw new Error('Render failed');},
    write(value){written=value;},close(){}
   },fetch:async(url,options)=>{
    requests++;
    assert.ok(url.startsWith('https://hs.portalx.win/hotspot/'+page+'?'));
    assert.equal(options.redirect,'error');
    assert.equal(options.credentials,'omit');
    if(outcome==='network')throw new TypeError('Failed to fetch');
    return {ok:outcome!=='http',status:503,text:async()=>outcome==='invalid'?'<html>Unexpected response</html>':'<html><head></head><body><div id="pixiepoint-root">Hosted portal</div></body></html>'};
   }};
   vm.runInNewContext(script,sandbox);
   await new Promise(resolve=>setImmediate(resolve));
   if(outcome==='success'){
    assert.ok(written.includes('<base href="https://hs.portalx.win/">'));
    assert.equal(retry.hidden,true);
   }else{
    assert.equal(written,'');
    assert.equal(retry.hidden,false);
    assert.ok(!status.textContent.includes('temporarily unavailable'));
    if(outcome==='http')assert.ok(status.textContent.includes('HTTP 503'));
    if(outcome==='invalid')assert.ok(status.textContent.includes('(response)'));
    if(outcome==='render')assert.ok(status.textContent.includes('(render)'));
    retry.click();await new Promise(resolve=>setImmediate(resolve));assert.equal(requests,2);
   }
   checks++;
  }
 }
 console.log('Passed '+checks+' loader success/failure/retry cases; no local forms or insecure redirects.');
})().catch(error=>{console.error(error);process.exitCode=1;});
