(function(){
 const sidebar=document.querySelector('.sidebar');
 if(!sidebar)return;
 const button=document.createElement('button');
 button.className='flh-mobile-menu';
 button.type='button';
 button.setAttribute('aria-label','เปิดเมนู');
 button.setAttribute('aria-expanded','false');
 button.textContent='☰';
 document.body.appendChild(button);
 const backdrop=document.createElement('div');
 backdrop.className='flh-sidebar-backdrop';
 document.body.appendChild(backdrop);
 const close=()=>{document.body.classList.remove('flh-menu-open');button.setAttribute('aria-expanded','false')};
 button.addEventListener('click',()=>{document.body.classList.toggle('flh-menu-open');button.setAttribute('aria-expanded',document.body.classList.contains('flh-menu-open'))});
 backdrop.addEventListener('click',close);
 sidebar.querySelectorAll('a').forEach(a=>a.addEventListener('click',close));
})();
