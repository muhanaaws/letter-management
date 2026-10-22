@props([
    'title' => 'Surat Resmi - Universitas AMIKOM Yogyakarta',
    'signers' => null,
    'qrToken' => 'DEMO-TOKEN-2026',
    'signatureProps' => null,
    'isPlaceholder' => false,
    'includePrintScript' => true,
    'customCss' => null,
    'enableToggle' => false,
])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        {!! file_get_contents(public_path('letter/css/letter-style.css')) !!}
    </style>
    @if($customCss)
        <style>
            {!! $customCss !!}
        </style>
    @endif
</head>
<body>
    @if($enableToggle)
        <!-- Toggle Button (Sticky Top-Right) -->
        <div id="preview-mode-toggle" class="preview-toggle">
            <span class="preview-toggle-title">Mode Preview:</span>
            <div class="preview-toggle-group">
                <button 
                    type="button"
                    id="toggle-switch"
                    class="preview-switch"
                    role="switch"
                    aria-checked="true"
                    aria-label="Toggle Sample Data Preview">
                    <span class="preview-switch-indicator" id="toggle-indicator"></span>
                </button>
                <span class="preview-toggle-label" id="toggle-label-sample">Sample Data</span>
            </div>
        </div>
    @endif
    <div class="sheet-wrap">
        <div class="page">
            <!-- Kop Surat Component -->
            <x-letter.header />

            <!-- Content Body from DB -->
            <div class="doc-content">
                {{ $slot }}

                @if(isset($signature))
                    {{ $signature }}
                @elseif(!empty($signers) && ((is_array($signers) && count($signers) > 0) || ($signers instanceof \Illuminate\Support\Collection && $signers->isNotEmpty())))
                    <x-letter.signature :signers="$signers" :qrToken="$qrToken" :isPlaceholder="$isPlaceholder" />
                @elseif(is_array($signatureProps))
                    <x-letter.signature 
                        :city="$signatureProps['city'] ?? 'Yogyakarta'" 
                        :date="$signatureProps['date'] ?? null" 
                        :title="$signatureProps['title'] ?? 'Ketua Program Studi'" 
                        :department="$signatureProps['department'] ?? 'Prodi D3 Teknik Informatika'" 
                        :name="$signatureProps['name'] ?? 'Dr. Barka Satya, M.Kom'" 
                        :nip="$signatureProps['nip'] ?? '190302126'" 
                        :qrToken="$qrToken"
                        :isPlaceholder="$isPlaceholder"
                    />
                @else
                    <x-letter.signature :qrToken="$qrToken" :isPlaceholder="$isPlaceholder" />
                @endif
            </div>

            <!-- Footer Component -->
            <x-letter.footer />
        </div>
    </div>

    @if($includePrintScript)
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('print') === 'true') {
                    setTimeout(() => {
                        window.print();
                    }, 500);
                }
            });
        </script>
    @endif

    @if($enableToggle)
        <style>
            /* ===== Toggle Button Container ===== */
            .preview-toggle {
                position: fixed;
                top: 24px;
                right: 24px;
                z-index: 50;
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                border: 1px solid #e5e7eb;
                padding: 16px;
                display: flex;
                align-items: center;
                gap: 12px;
                font-family: Arial, sans-serif;
                animation: fadeInDown 0.3s ease-out;
            }

            .preview-toggle-title {
                font-size: 14px;
                font-weight: 600;
                color: #374151;
            }

            .preview-toggle-group {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .preview-toggle-label {
                font-size: 12px;
                font-weight: 500;
                color: #6b7280;
            }

            /* ===== Toggle Switch ===== */
            .preview-switch {
                position: relative;
                display: inline-flex;
                height: 24px;
                width: 44px;
                align-items: center;
                border-radius: 9999px;
                background-color: #431E6D;
                border: none;
                cursor: pointer;
                transition: background-color 0.2s;
                outline: none;
                padding: 0;
                margin: 0;
            }

            .preview-switch:focus {
                box-shadow: 0 0 0 2px #431E6D, 0 0 0 4px #ffffff;
            }

            .preview-switch[aria-checked="false"] {
                background-color: #9ca3af;
            }

            .preview-switch-indicator {
                display: inline-block;
                height: 16px;
                width: 16px;
                border-radius: 9999px;
                background-color: #ffffff;
                transition: transform 0.2s;
                transform: translateX(24px);
            }

            .preview-switch[aria-checked="false"] .preview-switch-indicator {
                transform: translateX(4px);
            }

            /* ===== Body Version Visibility ===== */
            .body-version { transition: none; }
            .body-version.active { display: block; }
            .body-version.hidden { display: none; }

            /* ===== Raw Mode Placeholder Pill (Blue) ===== */
            #body-raw .placeholder-pill {
                background-color: #dbeafe;
                border: 1px solid #3b82f6;
                color: #1e40af;
                padding: 0.125rem 0.375rem;
                border-radius: 0.25rem;
                font-family: 'Courier New', monospace;
                font-size: 0.875em;
                font-weight: 600;
                white-space: nowrap;
                display: inline-block;
            }

            @keyframes fadeInDown {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            @media (max-width: 640px) {
                .preview-toggle { top: 16px; right: 16px; padding: 12px; }
            }

            @media print {
                .preview-toggle { display: none !important; }
            }
        </style>

        <script>
            (function() {
                const toggleSwitch = document.getElementById('toggle-switch');
                const toggleIndicator = document.getElementById('toggle-indicator');
                const toggleLabelSample = document.getElementById('toggle-label-sample');
                const bodySample = document.getElementById('body-sample');
                const bodyRaw = document.getElementById('body-raw');

                if (!toggleSwitch || !bodySample || !bodyRaw) return;

                let isSampleMode = true;

                function applyPreviewMode(sampleMode) {
                    isSampleMode = sampleMode;
                    toggleSwitch.setAttribute('aria-checked', isSampleMode);

                    if (isSampleMode) {
                        toggleIndicator.style.transform = 'translateX(24px)';
                        toggleSwitch.style.backgroundColor = '#431E6D';
                        if (toggleLabelSample) {
                            toggleLabelSample.style.color = '#374151';
                            toggleLabelSample.style.fontWeight = '600';
                        }
                        bodySample.classList.remove('hidden');
                        bodySample.classList.add('active');
                        bodyRaw.classList.remove('active');
                        bodyRaw.classList.add('hidden');
                    } else {
                        toggleIndicator.style.transform = 'translateX(4px)';
                        toggleSwitch.style.backgroundColor = '#9ca3af';
                        if (toggleLabelSample) {
                            toggleLabelSample.style.color = '#9ca3af';
                            toggleLabelSample.style.fontWeight = '500';
                        }
                        bodyRaw.classList.remove('hidden');
                        bodyRaw.classList.add('active');
                        bodySample.classList.remove('active');
                        bodySample.classList.add('hidden');
                    }

                    try {
                        localStorage.setItem('adminPreviewMode', isSampleMode ? 'sample' : 'raw');
                    } catch (e) {}
                }

                let initialMode = true;
                try {
                    const savedMode = localStorage.getItem('adminPreviewMode');
                    if (savedMode === 'raw') {
                        initialMode = false;
                    }
                } catch (e) {}

                applyPreviewMode(initialMode);

                toggleSwitch.addEventListener('click', function() {
                    applyPreviewMode(!isSampleMode);
                });

                toggleSwitch.addEventListener('keydown', function(e) {
                    if (e.key === ' ' || e.key === 'Enter') {
                        e.preventDefault();
                        applyPreviewMode(!isSampleMode);
                    }
                });
            })();
        </script>
    @endif
</body>
</html>
