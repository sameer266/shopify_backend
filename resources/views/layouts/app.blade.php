<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') – {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    {{--  Jquery  --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>


    {{--   Datatables --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

{{-- Sweet alert --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-50 font-sans antialiased min-h-screen" x-data="{ sidebarOpen: false }">
    @include('layouts.navbar')
    <div class="flex">
        @include('layouts.sidebar')
        <main class="flex-1 lg:ml-64 pt-16 min-h-screen">
            <div class="p-8 max-w-7xl mx-auto">
                @yield('content')
            </div>
        </main>
    </div>
    @stack('scripts')
    @if(session('success'))
    <script>
        Swal.fire({ 
            icon: 'success', 
            title: @json(session('success')), 
            toast: true, 
            position: 'top-end', 
            showConfirmButton: false, 
            timer: 3000,
            background: '#ffffff',
            color: '#000000',
            iconColor: '#000000'
        });
    </script>
    @endif
    @if(session('error'))
    <script>
        Swal.fire({ 
            icon: 'error', 
            title: @json(session('error')), 
            toast: true, 
            position: 'top-end', 
            showConfirmButton: false, 
            timer: 4000,
            background: '#ffffff',
            color: '#000000',
            iconColor: '#000000'
        });
    </script>
    @endif
</body>
</html>