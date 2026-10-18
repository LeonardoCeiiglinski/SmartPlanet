(() => {
  window.__posAbort && window.__posAbort.abort();
  const app=document.querySelector('#pos-app'); if(!app)return;
  const posAbort=new AbortController(); window.__posAbort=posAbort; const posSignal={signal:posAbort.signal};
  const search=document.querySelector('#pos-search'), results=document.querySelector('#pos-results'), cartBody=document.querySelector('#pos-cart'), form=document.querySelector('#pos-form');
  let cart=[], busy=false, lastScan={value:'',at:0}, key=crypto.randomUUID(), suspendedId=null;
  const number=v=>{let s=String(v||'').trim(); if(!s)return 0; if(s.includes(',')) s=s.replace(/\./g,'').replace(',','.'); return Number(s)||0;}, money=v=>v.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
  const total=()=>cart.reduce((sum,i)=>sum+i.quantity*i.price-i.discount,0);
  function render(){
    cartBody.innerHTML=cart.map((i,n)=>`<tr><td>${escapeHtml(i.description)}</td><td><input class="cart-qty" data-n="${n}" value="${i.quantity}" placeholder="Ex.: 1"></td><td>${money(i.price)}</td><td><input class="cart-discount" data-n="${n}" value="${i.discount.toFixed(2).replace('.',',')}" placeholder="Ex.: 10,00" ${app.dataset.discount==='1'?'':'disabled'}></td><td class="num">${money(i.quantity*i.price-i.discount)}</td><td><button type="button" class="cart-remove" data-n="${n}">×</button></td></tr>`).join('');
    document.querySelector('#pos-total').textContent=money(total());
  }
  function add(p,barcode=''){const found=cart.find(i=>i.product_id===Number(p.id));if(found)found.quantity+=1;else cart.push({product_id:Number(p.id),description:p.description,price:number(p.price),quantity:1,discount:0,barcode});render();}
  const escapeHtml=s=>{const d=document.createElement('div');d.textContent=s;return d.innerHTML;};
  async function lookup(){
    const q=search.value.trim(); search.value=''; if(!q||busy)return;
    const now=Date.now(); if(lastScan.value===q&&now-lastScan.at<500)return; lastScan={value:q,at:now}; busy=true;
    try{const r=await fetch('/api/products/search?q='+encodeURIComponent(q),{headers:{Accept:'application/json'}});const data=await r.json();
      if(data.items.length===1)add(data.items[0],q);else results.innerHTML=data.items.map((p,n)=>`<button type="button" data-result="${n}">${escapeHtml(p.internal_code+' · '+p.description)} · ${money(number(p.price))}</button>`).join('')||'<span class="muted">Nenhum produto encontrado.</span>';
      results._items=data.items;
    }catch(e){results.textContent='Falha na busca.'}finally{busy=false;search.focus();}
  }
  search.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();lookup();}});
  results.addEventListener('click',e=>{const b=e.target.closest('[data-result]');if(b){add(results._items[Number(b.dataset.result)]);results.innerHTML='';search.focus();}});
  cartBody.addEventListener('change',e=>{const n=Number(e.target.dataset.n);if(e.target.classList.contains('cart-qty'))cart[n].quantity=Math.max(.001,number(e.target.value));if(e.target.classList.contains('cart-discount'))cart[n].discount=Math.max(0,number(e.target.value));render();search.focus();});
  cartBody.addEventListener('click',e=>{const b=e.target.closest('.cart-remove');if(b){cart.splice(Number(b.dataset.n),1);render();search.focus();}});
  document.addEventListener('click',e=>{if(!document.querySelector('#pos-app'))return;if(!e.target.matches('input,select,button,textarea'))search.focus();},posSignal);
  const recoveredNode=document.querySelector('#pos-recovered');
  if(recoveredNode){
    const recovered=JSON.parse(recoveredNode.textContent);
    cart=Array.isArray(recovered.items)?recovered.items:[]; key=recovered.idempotency_key||key; suspendedId=Number(recovered.suspended_id)||null;
    document.querySelector('#pos-customer').value=recovered.customer_id||''; document.querySelector('#pos-notes').value=recovered.notes||''; render();
    setTimeout(()=>search.focus(),0);
  }
  function payload(payments=[]){return {idempotency_key:key,items:cart,payments,customer_id:document.querySelector('#pos-customer').value,notes:document.querySelector('#pos-notes').value,suspended_id:suspendedId};}
  function suspend(){
    if(busy||!cart.length)return;
    busy=true; document.querySelector('#pos-payload').value=JSON.stringify(payload());
    form.action='/pos/suspend'; form.submit();
  }
  document.querySelector('#pos-suspend').addEventListener('click',suspend);
  document.addEventListener('keydown',e=>{
    if(!document.querySelector('#pos-app'))return;
    if(e.key==='F2'||e.key==='Escape'){e.preventDefault();search.focus();}
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
  search.focus();
})();
