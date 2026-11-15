/* HPM shared menu category behaviour */
(()=>{'use strict';
const categories=[
 ['Pallet Making Machines','pallet-making-machines'],
 ['Wood Cutting, Processing & Finishing Machines','wood-cutting-processing-finishing-machines'],
 ['Wood Treatment & Marking Equipment','wood-treatment-marking-equipment'],
 ['Material Handling Equipment','material-handling-equipment']
];
const current=(location.hash||'').slice(1);
document.querySelectorAll('.nav-area .mainmenu > li').forEach(li=>{
 const a=li.querySelector(':scope > a');
 if(!a || !/products\.php(?:$|[?#])/.test(a.getAttribute('href')||'')) return;
 li.classList.add('has-dropdown','has-menu-child-item');
 const ul=document.createElement('ul');ul.className='submenu parent-nav';
 categories.forEach(([name,slug])=>{const item=document.createElement('li'),link=document.createElement('a');link.href='products.php#category-'+slug;link.textContent=name;if(location.pathname.endsWith('products.php')&&current==='category-'+slug)link.classList.add('active');item.append(link);ul.append(item)});
 li.append(ul);
});
const mobile=document.querySelector('#side-bar .mobile-menu');
if(mobile){
 const nav=mobile.querySelector('nav');
 const brand=document.createElement('a');brand.className='hpm-mobile-brand';brand.href='index.html';brand.innerHTML='<img src="assets/images/hpm-logo.svg" alt="Hari Pallet Machinery">';
 nav?.prepend(brand);
 mobile.querySelectorAll('li.has-droupdown').forEach(li=>{
   const link=li.querySelector(':scope > a.main'),submenu=li.querySelector(':scope > .submenu');
   if(!link||!submenu)return;
   const btn=document.createElement('button');btn.type='button';btn.className='hpm-submenu-toggle';btn.setAttribute('aria-label','Toggle '+link.textContent.trim());btn.setAttribute('aria-expanded','false');btn.innerHTML='<i class="fa-solid fa-plus" aria-hidden="true"></i>';
   li.append(btn);
   btn.addEventListener('click',()=>{const open=li.classList.toggle('hpm-submenu-open');btn.setAttribute('aria-expanded',String(open));btn.innerHTML=open?'<i class="fa-solid fa-minus" aria-hidden="true"></i>':'<i class="fa-solid fa-plus" aria-hidden="true"></i>';submenu.style.display=open?'block':'none'});
   submenu.style.display='none';
 });
 const productLi=[...mobile.querySelectorAll('li.has-droupdown')].find(li=>/products/i.test(li.querySelector(':scope > a')?.textContent||''));
 if(productLi){const ul=productLi.querySelector(':scope > .submenu');ul.innerHTML='';categories.forEach(([name,slug])=>{const li=document.createElement('li'),a=document.createElement('a');a.className='mobile-menu-link';a.href='products.php#category-'+slug;a.textContent=name;if(location.pathname.endsWith('products.php')&&current==='category-'+slug)a.classList.add('active');li.append(a);ul.append(li)});}
}
})();
