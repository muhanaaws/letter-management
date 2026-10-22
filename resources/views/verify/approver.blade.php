<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Persetujuan - Universitas AMIKOM Yogyakarta</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen font-['Plus_Jakarta_Sans',sans-serif] text-gray-800 antialiased flex flex-col justify-between">

    <!-- Header Navbar -->
    <header class="bg-white border-b border-gray-200 shadow-xs">
        <div class="max-w-3xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ url('/letter/images/logo-amikom.png') }}" alt="Logo AMIKOM" class="h-10 w-auto object-contain">
                <div class="border-l border-gray-300 pl-3">
                    <h1 class="text-sm font-bold text-gray-900 leading-tight">Universitas AMIKOM Yogyakarta</h1>
                    <p class="text-xs text-gray-500">Sistem Verifikasi Persetujuan Surat</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-100">
                E-Verification System
            </span>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-3xl mx-auto px-4 py-8 w-full flex-1">

        @if($isValid && $signer)
            <!-- VALID VERIFICATION -->
            <div class="bg-white rounded-2xl shadow-sm border border-emerald-200 overflow-hidden">
                <!-- Status Banner -->
                <div class="bg-emerald-500 px-6 py-4 flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Persetujuan Terverifikasi</h2>
                        <p class="text-emerald-100 text-sm">Persetujuan ini terdaftar dan sah dalam sistem</p>
                    </div>
                </div>

                <!-- Approver Detail -->
                <div class="px-6 py-5 space-y-4">
                    <h3 class="text-base font-semibold text-gray-700 border-b border-gray-100 pb-2">
                        Data Penyetuju
                    </h3>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Nama Penyetuju</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $signer->approver_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Jabatan / Peran</dt>
                            <dd class="mt-1 text-sm text-gray-800">{{ $signer->approver_role ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Waktu Persetujuan</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                {{ $signer->approved_at?->format('d-m-Y H:i:s') ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Terdaftar dalam sistem
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Letter Detail -->
                <div class="px-6 py-5 bg-gray-50 border-t border-gray-100 space-y-4">
                    <h3 class="text-base font-semibold text-gray-700 border-b border-gray-100 pb-2">
                        Data Surat
                    </h3>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Nomor Surat</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-900">{{ $letterNumber ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Jenis Surat</dt>
                            <dd class="mt-1 text-sm text-gray-800">{{ $letterTypeName ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Nama Mahasiswa</dt>
                            <dd class="mt-1 text-sm text-gray-800">{{ $studentName ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">NIM</dt>
                            <dd class="mt-1 text-sm text-gray-800">{{ $studentNim ?? '-' }}</dd>
                        </div>
                        @if($generatedLetter?->generated_at)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tanggal Terbit</dt>
                            <dd class="mt-1 text-sm text-gray-800">
                                {{ $generatedLetter->generated_at->format('d-m-Y H:i') }}
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>

                <!-- Token Display -->
                <div class="px-6 py-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400">
                        Token Verifikasi:
                        <span class="font-mono text-gray-500 break-all">{{ $token }}</span>
                    </p>
                </div>
            </div>

        @else
            <!-- VERIFICATION FAILED -->
            <div class="bg-white rounded-2xl shadow-sm border border-red-200 overflow-hidden">
                <div class="bg-red-500 px-6 py-4 flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Verifikasi Gagal</h2>
                        <p class="text-red-100 text-sm">Token tidak valid atau surat belum disetujui</p>
                    </div>
                </div>

                <div class="px-6 py-5">
                    <p class="text-sm text-gray-600">
                        {{ $message ?? 'Token verifikasi penyetuju tidak ditemukan atau tidak valid.' }}
                    </p>

                    @if($signer)
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-xs text-yellow-800">
                                Penyetuju: <span class="font-semibold">{{ $signer->approver_name }}</span>
                                — Surat belum mendapatkan persetujuan akhir.
                            </p>
                        </div>
                    @endif

                    <p class="text-xs text-gray-400 mt-4 font-mono break-all">Token: {{ $token }}</p>
                </div>
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-3xl mx-auto px-4 py-4 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} Universitas AMIKOM Yogyakarta — Sistem Verifikasi Persetujuan Surat
        </div>
    </footer>
</body>
</html>
