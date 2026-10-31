(() => {
  window.__posAbort && window.__posAbort.abort();
  const app=document.querySelector('#pos-app'); if(!app)return;
  const posAbort=new AbortController(); window.__posAbort=posAbort; const posSignal={signal:posAbort.signal};
  const search=document.querySelector('#pos-search'), results=document.querySelector('#pos-results'), cartBody=document.querySelector('#pos-cart'), form=document.querySelector('#pos-form');
  function focusPicker(){ const visible=document.getElementById('pos-search-busca'); if(visible) visible.focus(); else if(search) search.focus(); }
  let cart=[], busy=false, lastScan={value:'',at:0}, key=crypto.randomUUID(), suspendedId=null;
  const number=v=>{let s=String(v||'').trim(); if(!s)return 0; if(s.includes(',')) s=s.replace(/\./g,'').replace(',','.'); return Number(s)||0;}, money=v=>v.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
  const round2=v=>Math.round((Number(v)||0)*100)/100;
  const lineGross=i=>round2(i.quantity*i.price);
  function clampDiscount(item){
    const gross=lineGross(item);
    if(item.discountMode==='percent'){
      const pct=Math.min(100,Math.max(0,round2(item.discountPercent||0)));
      item.discountPercent=pct;
      item.discount=Math.min(gross,round2(gross*pct/100));
    }else{
      item.discountMode='money';
      item.discountPercent=0;
      item.discount=Math.min(gross,Math.max(0,round2(item.discount||0)));
    }
  }
  function discountText(item){
    if(item.discountMode==='percent') return String(round2(item.discountPercent||0)).replace('.',',');
    return round2(item.discount||0).toFixed(2).replace('.',',');
  }
  const total=()=>cart.reduce((sum,i)=>sum+lineGross(i)-i.discount,0);
  function render(){
    const off=app.dataset.discount==='1'?'':'disabled';
    cartBody.innerHTML=cart.map((i,n)=>{
      const mode=i.discountMode==='percent'?'percent':'money';
      const ph=mode==='percent'?'Ex.: 10':'Ex.: 10,00';
      return `<tr><td>${escapeHtml(i.description)}</td><td><input class="cart-qty" data-n="${n}" value="${i.quantity}" placeholder="Ex.: 1"></td><td>${money(i.price)}</td><td><div class="cart-discount-box"><select class="cart-discount-mode" data-n="${n}" data-plain="1" aria-label="Tipo de desconto" ${off}><option value="money"${mode==='money'?' selected':''}>R$</option><option value="percent"${mode==='percent'?' selected':''}>%</option></select><input class="cart-discount" data-n="${n}" inputmode="decimal" value="${discountText(i)}" placeholder="${ph}" ${off}></div></td><td class="num">${money(lineGross(i)-i.discount)}</td><td><button type="button" class="cart-remove" data-n="${n}">×</button></td></tr>`;
    }).join('');
    document.querySelector('#pos-total').textContent=money(total());
  }
  function add(p,barcode=''){const found=cart.find(i=>i.product_id===Number(p.id));if(found){found.quantity+=1;clampDiscount(found);}else cart.push({product_id:Number(p.id),description:p.description,price:number(p.price),quantity:1,discount:0,discountMode:'money',discountPercent:0,barcode});render();}
  const escapeHtml=s=>{const d=document.createElement('div');d.textContent=s;return d.innerHTML;};
  function avisar(ok, texto){ if(window.SmartPlanetScanner){ SmartPlanetScanner.beep(ok); SmartPlanetScanner.flash(texto, ok); } }
  search.addEventListener('change',()=>{
    const opt=search.options[search.selectedIndex];
    if(!opt||opt.value==='')return;
    add({id:opt.value,description:opt.dataset.description||opt.textContent,price:opt.dataset.price},opt.dataset.barcode||'');
    search.value='';
  },posSignal);
  async function lookup(code, exact){
    const q=String(code||'').trim(); if(search) search.value=''; if(!q||busy)return;
    const now=Date.now(); if(lastScan.value===q&&now-lastScan.at<500)return; lastScan={value:q,at:now}; busy=true;
    try{const r=await fetch('/api/products/search?q='+encodeURIComponent(q)+(exact?'&exact=1':''),{headers:{Accept:'application/json'}});const data=await r.json();
      if(!data.items.length){ results.innerHTML='<span class="muted">Nenhum produto cadastrado para '+escapeHtml(q)+'.</span>'; avisar(false,'Não cadastrado: '+q); }
      else if(data.items.length===1){ add(data.items[0],q); results.innerHTML=''; avisar(true,data.items[0].description+' no carrinho'); }
      else { results.innerHTML=data.items.map((p,n)=>`<button type="button" data-result="${n}">${escapeHtml(p.internal_code+' · '+p.description)} · ${money(number(p.price))}</button>`).join(''); results._items=data.items; if(window.SmartPlanetScanner) SmartPlanetScanner.flash('Escolha o produto para '+q, null); }
    }catch(e){results.textContent='Falha na busca.'; avisar(false,'Falha ao consultar o código');}finally{busy=false;}
  }
  document.addEventListener('smartplanet:scan',e=>{
    if(!document.querySelector('#pos-app'))return;
    e.preventDefault();
    lookup(e.detail.code, true);
  },posSignal);
  results.addEventListener('click',e=>{const b=e.target.closest('[data-result]');if(b){add(results._items[Number(b.dataset.result)]);results.innerHTML='';}});
  function readRow(n){
    const item=cart[n]; if(!item)return;
    const qty=cartBody.querySelector('.cart-qty[data-n="'+n+'"]');
    const mode=cartBody.querySelector('.cart-discount-mode[data-n="'+n+'"]');
    const discount=cartBody.querySelector('.cart-discount[data-n="'+n+'"]');
    if(qty) item.quantity=Math.max(.001,number(qty.value));
    if(mode) item.discountMode=mode.value==='percent'?'percent':'money';
    if(discount){
      const raw=number(discount.value);
      if(item.discountMode==='percent') item.discountPercent=raw; else item.discount=raw;
    }
    clampDiscount(item);
  }
  cartBody.addEventListener('change',e=>{if(e.target.dataset.n==null)return;readRow(Number(e.target.dataset.n));render();});
  cartBody.addEventListener('click',e=>{const b=e.target.closest('.cart-remove');if(b){cart.splice(Number(b.dataset.n),1);render();}});
  const recoveredNode=document.querySelector('#pos-recovered');
  if(recoveredNode){
    const recovered=JSON.parse(recoveredNode.textContent);
    cart=Array.isArray(recovered.items)?recovered.items:[]; key=recovered.idempotency_key||key; suspendedId=Number(recovered.suspended_id)||null;
    cart.forEach(item=>{
      item.discountMode=(item.discount_mode||item.discountMode)==='percent'?'percent':'money';
      item.discountPercent=number(item.discount_percent??item.discountPercent??0);
      clampDiscount(item);
    });
    document.querySelector('#pos-customer').value=recovered.customer_id||''; document.querySelector('#pos-notes').value=recovered.notes||''; render();
  }
  function payload(payments=[]){
    cart.forEach((_,n)=>readRow(n));
    const items=cart.map(i=>({product_id:i.product_id,description:i.description,price:i.price,quantity:i.quantity,discount:round2(i.discount),discount_mode:i.discountMode==='percent'?'percent':'money',discount_percent:i.discountMode==='percent'?round2(i.discountPercent||0):0,barcode:i.barcode||''}));
    return {idempotency_key:key,items,payments,customer_id:document.querySelector('#pos-customer').value,notes:document.querySelector('#pos-notes').value,suspended_id:suspendedId};
  }
  function suspend(){
    if(busy||!cart.length)return;
    busy=true; document.querySelector('#pos-payload').value=JSON.stringify(payload());
    form.action='/pos/suspend'; form.submit();
  }
  document.querySelector('#pos-suspend').addEventListener('click',suspend);
  document.addEventListener('keydown',e=>{
    if(!document.querySelector('#pos-app'))return;
    if(e.key==='F2'||e.key==='Escape'){e.preventDefault();focusPicker();}
    if(e.key==='F4'){e.preventDefault();suspend();}
    if(e.key==='F9'){e.preventDefault();form.requestSubmit();}
  },posSignal);
  form.addEventListener('submit',e=>{
    if(busy||!cart.length){e.preventDefault();return;}
    const payments=[...document.querySelectorAll('.payment-row')].map(row=>({method_id:Number(row.dataset.id),amount:number(row.querySelector('.payment-amount').value),reference:row.querySelector('.payment-reference').value,confirmed:row.querySelector('.payment-confirm').checked})).filter(p=>p.amount>0);
    if(!payments.length){e.preventDefault();alert('Informe o pagamento.');return;}
    const invalid=[...document.querySelectorAll('.payment-row')].some(row=>number(row.querySelector('.payment-amount').value)>0&&row.dataset.type!=='cash'&&row.dataset.confirm==='1'&&!row.querySelector('.payment-confirm').checked);
    if(invalid){e.preventDefault();alert('Confirme operacionalmente os pagamentos não-dinheiro.');return;}
    busy=true;document.querySelector('#pos-submit').disabled=true;
    document.querySelector('#pos-payload').value=JSON.stringify(payload(payments));
  });
})();
