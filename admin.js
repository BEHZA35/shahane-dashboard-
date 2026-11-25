/* Shahaneh Admin — modular UI layer
 * Demo data only. Live WooCommerce API must be served through a protected backend.
 */
(() => {
  const demo = {
    products: [
      {id:1042,name:"ست سه‌تکه سویشرت کارترز",sku:"SH-1042",category:"لباس نوزادی",price:5550000,stock:12,status:"موجود"},
      {id:1041,name:"شلوار پسرانه کارگو کرم",sku:"SH-1041",category:"پوشاک پسرانه",price:1280000,stock:7,status:"موجود"},
      {id:1040,name:"پیراهن دخترانه مجلسی",sku:"SH-1040",category:"پوشاک دخترانه",price:2100000,stock:3,status:"کم‌موجود"},
      {id:1039,name:"کفش اسپرت کودک",sku:"SH-1039",category:"کفش کودک",price:1750000,stock:0,status:"ناموجود"}
    ],
    orders: [
      {id:10824,customer:"مریم احمدی",date:"۲۵ آبان",total:5550000,status:"تکمیل‌شده"},
      {id:10823,customer:"سارا محمدی",date:"۲۵ آبان",total:1280000,status:"در حال پردازش"},
      {id:10822,customer:"نگار رضایی",date:"۲۴ آبان",total:2100000,status:"تکمیل‌شده"},
      {id:10821,customer:"الهام کریمی",date:"۲۴ آبان",total:1750000,status:"در انتظار پرداخت"}
    ],
    customers: [
      {name:"مریم احمدی",orders:8,spent:18400000,joined:"۱۴۰۵/۰۸/۰۲"},
      {name:"سارا محمدی",orders:3,spent:6250000,joined:"۱۴۰۵/۰۸/۱۲"},
      {name:"نگار رضایی",orders:5,spent:11300000,joined:"۱۴۰۵/۰۷/۲۸"}
    ]
  };
  const fmt = n => new Intl.NumberFormat("fa-IR").format(n);
  const money = n => fmt(n) + " تومان";
  const $ = (s,root=document) => root.querySelector(s);
  const pageHost = document.createElement("div");
  pageHost.id = "moduleView";
  pageHost.hidden = true;
  const topbar = $(".topbar");
  topbar.insertAdjacentElement("afterend", pageHost);
  const baseSections = [...document.querySelectorAll("main > .section-head, main > .stats, main > .content-grid, main > .bottom-grid")];
  const pages = ["داشبورد","سفارش‌ها","محصولات","دسته‌بندی‌ها","مشتریان","تخفیف‌ها","انبار","حسابداری","گزارش‌ها","بازاریابی","سئو","تنظیمات"];
  const icons = ["▦","▤","◈","▧","♙","٪","▣","◫","▥","◉","⌕","⚙"];
  const nav = document.querySelector(".sidebar .nav");
  if (nav) {
    const add = pages.slice(6).map((p,i)=>{const b=document.createElement("button");b.dataset.page=p;b.innerHTML='<span class="ico">'+icons[i+6]+'</span>'+p;return b;});
    add.forEach(b=>nav.appendChild(b));
  }
  function table(headers, rows) {
    return '<div class="table-wrap"><table><thead><tr>'+headers.map(h=>'<th>'+h+'</th>').join("")+'</tr></thead><tbody>'+rows.join("")+'</tbody></table></div>';
  }
  function panel(title, body, action="") {
    return '<section class="panel" style="margin-bottom:16px"><div class="panel-title"><h3>'+title+'</h3>'+action+'</div>'+body+'</section>';
  }
  function toolbar(label, id="createItem") {
    return '<button class="toolbar-btn" id="'+id+'">＋ '+label+'</button>';
  }
  function status(s) {
    const cls=s==="تکمیل‌شده"||s==="موجود"?"":" "+(s==="ناموجود"?"cancel":"wait");
    return '<span class="status'+cls+'">'+s+'</span>';
  }
  function renderProducts() {
    const rows=demo.products.map(p=>'<tr><td><div class="product"><div class="product-pic">🛍️</div><div><b>'+p.name+'</b><small>'+p.sku+'</small></div></div></td><td>'+p.category+'</td><td>'+money(p.price)+'</td><td>'+fmt(p.stock)+'</td><td>'+status(p.status)+'</td></tr>');
    return panel("فهرست محصولات",table(["محصول","دسته‌بندی","قیمت","موجودی","وضعیت"],rows),toolbar("افزودن محصول"))+
      '<p class="muted">اطلاعات این صفحه نمونه نمایشی است؛ هنوز از ووکامرس دریافت نمی‌شود.</p>';
  }
  function renderOrders() {
    const rows=demo.orders.map(o=>'<tr><td>#'+fmt(o.id)+'</td><td>'+o.customer+'</td><td>'+o.date+'</td><td>'+money(o.total)+'</td><td>'+status(o.status)+'</td><td><button class="toolbar-btn" data-order="'+o.id+'">جزئیات</button></td></tr>');
    return panel("مدیریت سفارش‌ها",table(["شماره","مشتری","تاریخ","مبلغ","وضعیت","عملیات"],rows))+
      '<p class="muted">برای تغییر وضعیت یا مشاهده جزئیات واقعی، ابتدا باید دسترسی امن ووکامرس فعال شود.</p>';
  }
  function renderCustomers() {
    const rows=demo.customers.map(c=>'<tr><td>'+c.name+'</td><td>'+fmt(c.orders)+'</td><td>'+money(c.spent)+'</td><td>'+c.joined+'</td></tr>');
    return panel("مشتریان فروشگاه",table(["نام مشتری","تعداد سفارش","مجموع خرید","عضویت"],rows));
  }
  function renderInventory() {
    const low=demo.products.filter(p=>p.stock<=3);
    return panel("هشدار موجودی",'<div class="orders-summary">'+low.map(p=>'<div class="summary-row"><div class="summary-icon">!</div><div><b>'+p.name+'</b><small>'+p.sku+'</small></div><strong>'+fmt(p.stock)+' عدد</strong></div>').join("")+'</div>')+
      panel("موجودی همه محصولات",table(["محصول","شناسه کالا","موجودی","وضعیت"],demo.products.map(p=>'<tr><td>'+p.name+'</td><td>'+p.sku+'</td><td>'+fmt(p.stock)+'</td><td>'+status(p.status)+'</td></tr>')));
  }
  function renderAccounting() {
    return '<div class="stats"><div class="stat"><div class="stat-top">فروش ثبت‌شده</div><strong>۲۴۸,۶۰۰,۰۰۰</strong><div class="muted">تومان · نمونه</div></div><div class="stat"><div class="stat-top">هزینه‌ها</div><strong>—</strong><div class="muted">نیازمند ثبت هزینه</div></div><div class="stat"><div class="stat-top">سود خالص</div><strong>—</strong><div class="muted">پس از ثبت بهای تمام‌شده</div></div><div class="stat"><div class="stat-top">تسویه‌ها</div><strong>—</strong><div class="muted">نیازمند اتصال درگاه</div></div></div>'+
    panel("دفتر مالی",'<p class="muted">برای محاسبه سود واقعی باید بهای تمام‌شده کالا، هزینه ارسال، کارمزد درگاه و مرجوعی‌ها ثبت شوند. از نمایش سود حدسی خودداری می‌کنیم.</p>');
  }
  function renderMarketing() {
    return panel("مرکز بازاریابی",'<div class="quick"><button data-integration="Metricool"><i>◉</i><b>Metricool</b><small>تقویم و تحلیل شبکه‌های اجتماعی</small></button><button data-integration="Canva"><i>✦</i><b>Canva</b><small>طراحی بنر و محتوای تبلیغاتی</small></button><button data-integration="Instagram"><i>◎</i><b>اینستاگرام شاهانه</b><small>برنامه‌ریزی محتوای فروش</small></button><button data-integration="Coupons"><i>٪</i><b>کمپین تخفیف</b><small>مدیریت پیشنهادهای فروش</small></button></div>');
  }
  function renderSEO() {
    return panel("مرکز سئو و دیده‌شدن",'<div class="quick"><button data-integration="OpenSEO"><i>⌕</i><b>OpenSEO</b><small>تحلیل کلمات کلیدی و رقبا</small></button><button data-integration="Search Console"><i>▥</i><b>Search Console</b><small>کلیک، نمایش و رتبه صفحات</small></button><button data-integration="Indexing"><i>↗</i><b>بررسی ایندکس</b><small>وضعیت صفحات محصول</small></button><button data-integration="Schema"><i>◇</i><b>داده ساختاریافته</b><small>محصول و امتیازها</small></button></div>');
  }
  function renderSettings() {
    return panel("تنظیمات و اتصال‌ها",'<div class="orders-summary"><div class="summary-row"><div class="summary-icon">W</div><div><b>WordPress / WooCommerce</b><small>اتصال زنده هنوز تأیید نشده است</small></div>'+status("نیازمند اتصال")+'</div><div class="summary-row"><div class="summary-icon">G</div><div><b>GitHub</b><small>مخزن کد داشبورد</small></div>'+status("متصل")+'</div><div class="summary-row"><div class="summary-icon">F</div><div><b>Figma</b><small>فایل طراحی رابط</small></div>'+status("طراحی آماده")+'</div></div>');
  }
  function render(page) {
    const renderers={"محصولات":renderProducts,"سفارش‌ها":renderOrders,"مشتریان":renderCustomers,"انبار":renderInventory,"حسابداری":renderAccounting,"بازاریابی":renderMarketing,"سئو":renderSEO,"تنظیمات":renderSettings};
    pageHost.innerHTML=(renderers[page]||(()=>panel(page,'<p class="muted">ساختار این بخش آماده توسعه است. پس از اتصال منبع داده، اطلاعات زنده در این قسمت نمایش داده می‌شود.</p>')))();
    pageHost.hidden=false;
    baseSections.forEach(s=>s.hidden=true);
    $(".welcome h1").textContent=page==="داشبورد"?"داشبورد مدیریت":page;
    $(".welcome p").textContent=page==="داشبورد"?"نمای کلی فروشگاه شاهانه":"بخش "+page+" · حالت پیش‌نمایش تا زمان اتصال داده‌های واقعی";
    if(page==="داشبورد"){pageHost.hidden=true;baseSections.forEach(s=>s.hidden=false);}
    const create=$("#createItem");
    if(create) create.addEventListener("click",()=>notify("فرم ثبت "+(page==="محصولات"?"محصول":"مورد جدید")+" پس از اتصال امن ووکامرس فعال می‌شود"));
    pageHost.querySelectorAll("[data-integration]").forEach(b=>b.addEventListener("click",()=>notify("اتصال "+b.dataset.integration+" هنوز در این پنل پیکربندی نشده است")));
    pageHost.querySelectorAll("[data-order]").forEach(b=>b.addEventListener("click",()=>notify("جزئیات سفارش #"+b.dataset.order+" در اتصال زنده قابل مشاهده خواهد بود")));
  }
  document.querySelectorAll(".sidebar .nav button").forEach(b=>{
    const clone=b.cloneNode(true); b.replaceWith(clone);
    clone.addEventListener("click",()=>{
      document.querySelectorAll(".sidebar .nav button").forEach(x=>x.classList.remove("active"));
      clone.classList.add("active");render(clone.dataset.page||clone.textContent.trim());
    });
  });
  const search=$("#search");
  if(search) search.addEventListener("input",()=>{
    const q=search.value.trim().toLocaleLowerCase("fa");
    const visible=pageHost.querySelectorAll("tbody tr");
    visible.forEach(row=>row.hidden=!!q&&!row.textContent.toLocaleLowerCase("fa").includes(q));
  });
  const note=document.createElement("div");
  note.style.cssText="position:fixed;top:0;right:0;left:0;z-index:20;text-align:center;padding:5px 10px;background:#fff3d9;color:#855f20;font:11px Vazirmatn,sans-serif;border-bottom:1px solid #f1dfb9";
  note.textContent="حالت پیش‌نمایش: داده‌های نمایشی هستند و به فروشگاه زنده متصل نیستند.";
  document.body.appendChild(note);
})();
