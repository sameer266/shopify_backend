<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css'])
    <style>body { font-family: 'Inter', system-ui, sans-serif; }</style>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white border border-gray-200 p-10">
            <div class="text-center mb-10">
                <div class="inline-flex items-center justify-center w-12 h-12 border-2 border-black text-black mb-6">
                    <i class="fas fa-lock text-sm"></i>
                </div>
                <h1 class="text-2xl font-light text-black tracking-tight mb-2">Admin Dashboard</h1>
                <p class="text-gray-500 text-sm">Sign in to your account</p>
            </div>

            @if (session('error'))
                <div class="mb-6 p-4 border border-gray-300 bg-gray-50 text-gray-700 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-6 p-4 border border-gray-300 bg-gray-50 text-gray-700 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf
                <div>
                    <label for="email" class="block text-xs uppercase tracking-wider text-gray-500 mb-2">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                           class="w-full px-4 py-3 border border-gray-300 focus:border-black focus:ring-1 focus:ring-black text-gray-900 text-sm"
                           placeholder="admin@example.com">
                </div>
                <div>
                    <label for="password" class="block text-xs uppercase tracking-wider text-gray-500 mb-2">Password</label>
                    <input type="password" name="password" id="password" required
                           class="w-full px-4 py-3 border border-gray-300 focus:border-black focus:ring-1 focus:ring-black text-gray-900 text-sm"
                           placeholder="••••••••">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="border-gray-300 text-black focus:ring-black">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                </div>
                <button type="submit" class="w-full py-3 px-4 border border-black bg-black text-white text-sm font-medium hover:bg-gray-800 transition">
                    Sign in
                </button>
            </form>
        </div>
        <p class="text-center text-gray-400 text-xs mt-8 uppercase tracking-wider">Admin Portal</p>
    </div>
</body>
</html>