 <div class="main-sidebar sidebar-style-2">
     <aside id="sidebar-wrapper">
         <div class="sidebar-brand">
             <a href="index.html">Findash</a>
         </div>
         <div class="sidebar-brand sidebar-brand-sm">
             <a href="index.html">Fd</a>
         </div>
         <ul class="sidebar-menu">
             <li class="menu-header">Dashboard</li>
             <li class="dropdown active">
                 <a href="{{ route('dashboard') }}" class="nav-link"><i
                         class="fas fa-fire"></i><span>Dashboard</span></a>

             </li>
             <li class="menu-header">Transactions</li>
             <li class="dropdown">
                 <a href="{{ route('transactions.index') }}" class="nav-link"><i class="fas fa-columns"></i>
                     <span>Transactions</span></a>
             </li>
             <li class="menu-header">Analitics</li>
             <li class="dropdown">
                 <a href="{{ route('analytics') }}" class="nav-link"><i class="fas fa-columns"></i>
                     <span>Analitics</span></a>
             </li>
         </ul>
     </aside>
 </div>
