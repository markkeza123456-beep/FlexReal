(function(){
 const sidebar=document.querySelector('.sidebar');
 if(!sidebar || document.querySelector('.menu-btn, [data-sidebar-toggle], .sidebar-toggle'))return;
 document.body.classList.add('flh-has-mobile-menu');
 if(!sidebar.id)sidebar.id='flh-mobile-sidebar';
 const button=document.createElement('button');
 button.className='flh-mobile-menu';
 button.type='button';
 button.setAttribute('aria-label','เปิดเมนูนำทาง');
 button.setAttribute('aria-controls',sidebar.id);
 button.setAttribute('aria-expanded','false');
 button.textContent='☰';
 const dashboardHeader=document.querySelector('.dashboard-container .main-content .header');
 if(dashboardHeader){document.body.classList.add('flh-student-dashboard');button.setAttribute('aria-label','เปิดเมนูแดชบอร์ด');}
 document.body.appendChild(button);
 const backdrop=document.createElement('div');
 backdrop.className='flh-sidebar-backdrop';
 document.body.appendChild(backdrop);
 const close=()=>{document.body.classList.remove('flh-menu-open');button.setAttribute('aria-expanded','false')};
 button.addEventListener('click',()=>{document.body.classList.toggle('flh-menu-open');button.setAttribute('aria-expanded',document.body.classList.contains('flh-menu-open'))});
 backdrop.addEventListener('click',close);
 sidebar.querySelectorAll('a, button, .menu-item, .nav-item, .parent-profile, #btn-settings, [data-page], [data-view]').forEach(item=>item.addEventListener('click',close));
 document.addEventListener('keydown',event=>{if(event.key==='Escape')close()});
})();
