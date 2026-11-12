<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — DMS CRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 500: '#f59e0b', 600: '#d97706' } }
                }
            }
        }
    </script>
</head>
<body class="min-h-full font-sans antialiased text-slate-100 flex items-center justify-center p-4 sm:p-6 lg:p-8 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-800 via-slate-900 to-black">

    <div class="w-full max-w-md my-auto">
        <!-- Top Agency Branding -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-amber-500 text-white font-extrabold text-2xl shadow-lg shadow-amber-500/30 mb-2">
                D
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">DMS <span class="text-amber-500">CRM</span></h1>
            <p class="text-xs font-semibold text-slate-400">Digital Marketing Agency Operations Suite v2.0</p>
        </div>

        <!-- Authentication Card -->
        <div class="bg-slate-800/80 backdrop-blur-xl border border-slate-700/80 rounded-3xl p-6 sm:p-8 shadow-2xl">
            <div class="flex items-center justify-between mb-1">
                <h2 class="text-xl font-extrabold text-white">Welcome back</h2>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                    v2.0 Secure
                </span>
            </div>
            <p class="text-xs text-slate-400 mb-6">Sign in to access your role dashboard</p>

            @if($errors->any())
            <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-semibold flex items-center gap-2">
                <i class="fa fa-circle-exclamation text-rose-400"></i>
                <span>{{ $errors->first() }}</span>
            </div>
            @endif

            <form id="loginForm" action="{{ route('login.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="username" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 text-sm">
                            <i class="fa fa-user"></i>
                        </span>
                        <input type="text" name="username" id="username" value="{{ old('username') }}" required autofocus placeholder="Enter your username"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-900/90 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all font-medium">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 text-sm">
                            <i class="fa fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="password" required placeholder="••••••••"
                               class="w-full pl-10 pr-10 py-2.5 bg-slate-900/90 border border-slate-700 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-all font-medium">
                        <button type="button" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition-colors">
                            <i id="toggleEyeIcon" class="fa fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center text-xs text-slate-400 font-medium cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded border-slate-700 bg-slate-900 text-amber-500 focus:ring-amber-500 mr-2"> Remember me
                    </label>
                    <span class="text-[11px] text-slate-500 font-medium">Internal Agency System</span>
                </div>

                <button type="submit" id="submitBtn" class="w-full py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-extrabold text-sm rounded-xl shadow-lg shadow-amber-500/25 transition-all flex items-center justify-center gap-2">
                    <span>Sign In to Dashboard</span>
                    <i class="fa fa-arrow-right text-xs"></i>
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-slate-700/60 text-[11px] text-slate-500 text-center">
                Authorized agency personnel only. Contact your administrator for account access.
            </div>
        </div>
    </div>

    <script>
    function togglePasswordVisibility() {
        const pInput = document.getElementById('password');
        const icon = document.getElementById('toggleEyeIcon');
        if (pInput.type === 'password') {
            pInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            pInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    </script>
</body>
</html>
