 <nav class="navbar navbar-expand-lg main-navbar">
     <form class="form-inline mr-auto">
         <ul class="navbar-nav mr-3">
             <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg"><i class="fas fa-bars"></i></a></li>
             <li><a href="#" data-toggle="search" class="nav-link nav-link-lg d-sm-none"><i
                         class="fas fa-search"></i></a></li>
         </ul>
         <div class="search-element">

         </div>
     </form>
     <ul class="navbar-nav navbar-right">
         <li class="dropdown dropdown-list-toggle"><a href="#" data-toggle="dropdown"
                 class="nav-link notification-toggle nav-link-lg beep"><i class="far fa-bell"></i></a>
             <div class="dropdown-menu dropdown-list dropdown-menu-right">
                 <div class="dropdown-header">Notifications
                     <div class="float-right">
                         <a href="#">Mark All As Read</a>
                     </div>
                 </div>
                 <div class="dropdown-list-content dropdown-list-icons">
                     <a href="#" class="dropdown-item dropdown-item-unread">
                         <div class="dropdown-item-icon bg-primary text-white">
                             <i class="fas fa-code"></i>
                         </div>
                         <div class="dropdown-item-desc">
                             Template update is available now!
                             <div class="time text-primary">2 Min Ago</div>
                         </div>
                     </a>
                     <a href="#" class="dropdown-item">
                         <div class="dropdown-item-icon bg-info text-white">
                             <i class="far fa-user"></i>
                         </div>
                         <div class="dropdown-item-desc">
                             <b>You</b> and <b>Dedik Sugiharto</b> are now friends
                             <div class="time">10 Hours Ago</div>
                         </div>
                     </a>
                     <a href="#" class="dropdown-item">
                         <div class="dropdown-item-icon bg-success text-white">
                             <i class="fas fa-check"></i>
                         </div>
                         <div class="dropdown-item-desc">
                             <b>Kusnaedi</b> has moved task <b>Fix bug header</b> to <b>Done</b>
                             <div class="time">12 Hours Ago</div>
                         </div>
                     </a>
                     <a href="#" class="dropdown-item">
                         <div class="dropdown-item-icon bg-danger text-white">
                             <i class="fas fa-exclamation-triangle"></i>
                         </div>
                         <div class="dropdown-item-desc">
                             Low disk space. Let's clean it!
                             <div class="time">17 Hours Ago</div>
                         </div>
                     </a>
                     <a href="#" class="dropdown-item">
                         <div class="dropdown-item-icon bg-info text-white">
                             <i class="fas fa-bell"></i>
                         </div>
                         <div class="dropdown-item-desc">
                             Welcome to Stisla template!
                             <div class="time">Yesterday</div>
                         </div>
                     </a>
                 </div>
                 <div class="dropdown-footer text-center">
                     <a href="#">View All <i class="fas fa-chevron-right"></i></a>
                 </div>
             </div>
         </li>
         <li class="dropdown">
             <a href="#" data-toggle="dropdown"
                 class="d-flex justify-content-end align-items-center nav-link dropdown-toggle nav-link-lg nav-link-user">
                 @if (Auth::user()->image)
                     <img alt="{{ Auth::user()->name }}" src="{{ Auth::user()->image }}" width="30"
                         class="rounded-circle mr-1"
                         onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7F9CF5&background=EBF4FF'">
                 @else
                     <img alt="{{ Auth::user()->name }}"
                         src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&color=7F9CF5&background=EBF4FF"
                         width="30" class="rounded-circle mr-1">
                 @endif
                 <div class="d-sm-none d-lg-inline-block">Hi, {{ Auth::user()->name }}</div>
             </a>
             <div class="dropdown-menu dropdown-menu-right">
                 <div class="dropdown-divider"></div>
                 <form method="POST" action="{{ route('logout') }}" class="dropdown-item has-icon text-danger">
                     @csrf
                     <x-dropdown-link :href="route('logout')"
                         onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                         {{ __('Log Out') }}
                     </x-dropdown-link>
                 </form>

             </div>
         </li>
     </ul>
 </nav>
