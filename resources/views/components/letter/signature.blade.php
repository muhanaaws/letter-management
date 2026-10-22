@props([
    'signers'       => null,   // GeneratedLetterSigner collection (issued mode) or array/collection (preview mode)
    'qrToken'       => 'DEMO-TOKEN-2026',
    'qrUrl'         => null,
    'city'          => 'Yogyakarta',
    'date'          => null,
    'title'         => 'Ketua Program Studi',
    'department'    => 'Prodi D3 Teknik Informatika',
    'name'          => 'Dr. Barka Satya, M.Kom',
    'nip'           => '190302126',
    'align'         => 'right',
    'isPlaceholder' => false,
])

@php
    $qrService = app(\App\Services\QrCodeService::class);

    // Standard Indonesian date format (e.g. 22 October 2026)
    $formatDateIndo = function ($dt) use ($date) {
        if (!empty($dt)) {
            try {
                return \Carbon\Carbon::parse($dt)->locale('id')->isoFormat('D MMMM Y');
            } catch (\Throwable $e) {
                return (string) $dt;
            }
        }
        if (!empty($date)) {
            try {
                return \Carbon\Carbon::parse($date)->locale('id')->isoFormat('D MMMM Y');
            } catch (\Throwable $e) {
                return $date;
            }
        }
        return \Carbon\Carbon::now()->locale('id')->isoFormat('D MMMM Y');
    };

    // Detect Head of Study Program roles, the only ones allowed to show the study program name.
    $isKaprodi = function ($role) {
        if (empty($role)) {
            return false;
        }

        $raw = strtolower(trim((string) $role));
        $normalized = str_replace(['_', '-'], ' ', $raw);

        return str_contains($normalized, 'ketua program studi')
            || str_contains($normalized, 'kepala program studi')
            || str_contains($normalized, 'kaprodi')
            || str_contains($normalized, 'head of study program')
            || str_contains($raw, 'head_of_study_program');
    };

    // Format NIP/NIK uniformly
    $formatNip = function ($nip) {
        if (empty($nip) || $nip === '-') {
            return null;
        }
        $nip = trim((string) $nip);
        if (preg_match('/^(NIK|NIDN|NIP)\b/i', $nip)) {
            return $nip;
        }
        return 'NIK. ' . $nip;
    };

    // Read a value from an array or object (model/stdClass)
    $read = function ($signer, array $keys, $default = null) {
        foreach ($keys as $key) {
            if (is_array($signer)) {
                if (array_key_exists($key, $signer) && $signer[$key] !== null && $signer[$key] !== '') {
                    return $signer[$key];
                }
            } elseif (is_object($signer) && isset($signer->{$key}) && $signer->{$key} !== null && $signer->{$key} !== '') {
                return $signer->{$key};
            }
        }
        return $default;
    };

    $isPreviewToken = str_contains((string) $qrToken, 'PREVIEW') || (bool) $isPlaceholder;
    $qrCaption = $isPreviewToken
        ? 'Pratinjau: QR Verifikasi Aktif Setelah Disetujui'
        : 'Imbas QR untuk verifikasi keabsahan';

    $legacyTargetUrl = $qrUrl ?? ($qrToken ? route('verify.letter', $qrToken) : url('/verify/DEMO-TOKEN-2026'));

    // Normalize all data modes into one uniform structure
    $rawSigners = [];
    if ($signers instanceof \Illuminate\Support\Collection) {
        $rawSigners = $signers->all();
    } elseif (is_array($signers)) {
        $rawSigners = $signers;
    }

    if (empty($rawSigners)) {
        $rawSigners = [[
            'city'       => $city,
            'date'       => $date,
            'title'      => $title,
            'department' => $department,
            'name'       => $name,
            'nip'        => $nip,
        ]];
    }

    $signerCount = count($rawSigners);
    $defaultDepartment = $department;
    $defaultPosition = in_array($align, ['left', 'center', 'right'], true) ? $align : 'right';
    $normalizedSigners = collect();

    foreach ($rawSigners as $index => $signer) {
        $position = $read($signer, ['qr_position']);
        $row      = (int) ($read($signer, ['qr_row'], 0) ?? 0);

        if (empty($position)) {
            if ($signerCount === 1) {
                $position = $defaultPosition;
                $row      = 0;
            } elseif ($signerCount === 2) {
                $position = $index === 0 ? 'left' : 'right';
                $row      = 0;
            } else {
                $position = ['left', 'center', 'right'][$index % 3];
                $row      = intdiv($index, 3);
            }
        }

        $role       = $read($signer, ['approver_role', 'title', 'role'], 'Pejabat Berwenang');
        $name       = $read($signer, ['approver_name', 'name'], 'Dr. Nama Dosen, M.Kom');
        $nip        = $read($signer, ['approver_nip', 'nip']);
        $signerDepartment = $read($signer, ['approver_department', 'department'], $defaultDepartment);
        $cityName   = $read($signer, ['city'], $city ?? 'Yogyakarta');
        $dateValue  = $read($signer, ['approved_at', 'date']);

        if ($nip === null && is_object($signer)) {
            $nip = $signer->user?->nip ?? $signer->user?->username;
        }

        $token = $read($signer, ['verification_token']);
        $url   = !empty($token)
            ? route('public.verify.approver', ['token' => $token])
            : $legacyTargetUrl;

        $qrSvg = $qrService->generateSvg($url, 160);

        $normalizedSigners->push([
            'row'        => $row,
            'position'   => in_array($position, ['left', 'center', 'right'], true) ? $position : 'right',
            'name'       => $name,
            'role'       => $role,
            'nip'        => $nip,
            'department' => $signerDepartment,
            'city'       => $cityName,
            'date'       => $formatDateIndo($dateValue),
            'url'        => $url,
            'qr'         => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'is_kaprodi' => $isKaprodi($role),
        ]);
    }

    $positionRank = ['left' => 0, 'center' => 1, 'right' => 2];
    $rows = $normalizedSigners->groupBy('row')->sortKeys();
@endphp

<!-- ===== SIGNATURE BLOCK ===== -->
@if($isPlaceholder)
    <!-- ADMIN PREVIEW MODE (SAMPLE DATA) -->
    <div class="sign-block text-right" style="margin-top: 16pt; width: 220pt;">
        <div style="margin-bottom: 12pt;">Yogyakarta, 11 September 2025</div>
        <div style="font-weight: bold;">Ketua Program Studi</div>
        <div style="margin-bottom: 8pt;">Prodi D3 Teknik Informatika</div>

        <!-- Placeholder QR code box -->
        <div style="margin-top: 10pt; margin-bottom: 4pt; display: inline-block; text-align: center;">
            <div style="width: 52pt; height: 52pt; border: 1.5pt dashed #94a3b8; background: #f8fafc; border-radius: 4pt; margin: 0 auto; padding: 2pt; text-align: center;">
                <span style="font-size: 6.5pt; font-weight: bold; color: #475569; font-family: sans-serif; display: block; margin-top: 14pt;">[ QR CODE ]</span>
            </div>
            <div style="font-size: 6.5pt; color: #64748b; margin-top: 2pt; font-family: Arial, sans-serif; font-weight: bold;">
                Otomatis di-generate sistem
            </div>
        </div>

        <div style="font-weight: bold; margin-top: 6pt; white-space: normal; word-break: break-word;">Dr. Barka Satya, M.Kom</div>
        <div>NIK. 190302126</div>
    </div>

@else
    @foreach ($rows as $rowSigners)
        @php
            $rowSigners = $rowSigners
                ->sortBy(fn ($signer) => $positionRank[$signer['position']] ?? 9)
                ->values();

            $columnCount = max($rowSigners->count(), 1);
            $columnWidth = 100 / $columnCount;
        @endphp

        <table style="width: 100%; border-collapse: collapse; margin-top: 16pt; page-break-inside: avoid;">
            <tr>
                @foreach ($rowSigners as $signer)
                    @php
                        $alignment = match ($signer['position']) {
                            'left'   => 'left',
                            'center' => 'center',
                            default  => 'right',
                        };

                        $topLine = match ($signer['position']) {
                            'left'   => 'Menyetujui,',
                            'center' => 'Mengetahui,',
                            default  => trim($signer['city'] . ', ' . $signer['date']),
                        };
                    @endphp

                    <td width="{{ $columnWidth }}%" valign="top" style="text-align: {{ $alignment }}; padding: 0 6pt; line-height: 1.3;">
                        <div class="sign-block" style="display: inline-block; text-align: {{ $alignment }}; width: 100%; max-width: 210pt; margin-top: 0;">

                            {{-- Date / salutation line --}}
                            <div style="margin-bottom: 12pt;">{{ $topLine }}</div>

                            {{-- Signer position --}}
                            <div style="font-weight: bold;">{{ $signer['role'] }}</div>

                            {{-- Show study program only for Kaprodi; other columns use an invisible spacer to keep QR aligned --}}
                            @if($signer['is_kaprodi'] && !empty($signer['department']))
                                <div style="margin-bottom: 8pt;">{{ $signer['department'] }}</div>
                            @else
                                <div style="visibility: hidden; margin-bottom: 8pt;" aria-hidden="true">&nbsp;</div>
                            @endif

                            {{-- Verification QR code --}}
                            <div style="margin-top: 12pt; margin-bottom: 4pt; display: inline-block; text-align: center;">
                                <a href="{{ $signer['url'] }}" target="_blank" title="Verifikasi persetujuan {{ $signer['name'] }}" style="text-decoration: none; display: inline-block; border: 0; outline: none;">
                                    <div class="qr-code-img" style="display: inline-block; width: 52pt; height: 52pt; padding: 2pt; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4pt; box-sizing: border-box; text-align: center;">
                                        <img src="{{ $signer['qr'] }}" alt="QR Verifikasi {{ $signer['name'] }}" width="64" height="64" style="width: 48pt; height: 48pt; display: block; margin: 0 auto; border: 0;">
                                    </div>
                                </a>
                                <div style="font-size: 6.5pt; color: #64748b; margin-top: 2pt; font-family: Arial, sans-serif; font-weight: bold;">
                                    {{ $qrCaption }}
                                </div>
                            </div>

                            {{-- Signer name & NIP --}}
                            <div style="font-weight: bold; margin-top: 6pt; white-space: normal; word-break: break-word;">{{ $signer['name'] }}</div>
                            @if($formattedNip = $formatNip($signer['nip']))
                                <div>{{ $formattedNip }}</div>
                            @endif
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endforeach
@endif
