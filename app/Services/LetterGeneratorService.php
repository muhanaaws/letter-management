<?php

namespace App\Services;

use App\Models\GeneratedLetter;
use App\Models\GeneratedLetterSigner;
use App\Models\Submission;
use Illuminate\Support\Str;

class LetterGeneratorService
{
    public function __construct(
        protected DocumentNamingService $namingService
    ) {}

    /** Generate or retrieve the official letter and its per-signer verification records. */
    public function generateLetterForSubmission(Submission $submission): GeneratedLetter
    {
        $submission->loadMissing([
            'letterType',
            'student.user',
            'logs' => function ($q) {
                $q->with(['user', 'approvalFlowStep'])->latest();
            },
        ]);

        $existingLetter = GeneratedLetter::where('submission_id', $submission->id)->first();
        if ($existingLetter && !empty($existingLetter->letter_number)) {
            $this->syncSignerRecords($existingLetter, $submission);
            return $existingLetter;
        }

        $letterCode = $submission->letterType && !empty($submission->letterType->code)
            ? strtoupper(trim($submission->letterType->code))
            : 'SK';

        $now = now();
        $year = $now->format('Y');
        $month = (int) $now->format('n');
        $romanMonth = $this->getRomanMonth($month);

        // Compute monthly sequence count for letters generated in this month and year
        $monthlySequence = GeneratedLetter::where('submission_id', '!=', $submission->id)
            ->where(function ($query) use ($year, $month) {
                $query->where(function ($q) use ($year, $month) {
                    $q->whereYear('generated_at', $year)
                      ->whereMonth('generated_at', $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereNull('generated_at')
                      ->whereYear('created_at', $year)
                      ->whereMonth('created_at', $month);
                });
            })
            ->count() + 1;

        $letterNumber = sprintf('%s/%d/D3TI/AMIKOM/%s/%s', $letterCode, $monthlySequence, $romanMonth, $year);
        $qrToken = sprintf('VERIFY-%s-%04d-%s', $letterCode, $submission->id, strtoupper(Str::random(8)));
        $filePath = $this->namingService->generateFilePath($submission);

        $generatedLetter = GeneratedLetter::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'letter_number' => $letterNumber,
                'file_path' => $filePath,
                'qr_token' => $qrToken,
                'generated_at' => $now,
            ]
        );

        // Create or sync a per-signer record with its own verification token.
        $this->syncSignerRecords($generatedLetter, $submission);

        return $generatedLetter;
    }

    /** Create or sync signer records for each approver with a unique verification token. */
    public function syncSignerRecords(GeneratedLetter $generatedLetter, Submission $submission): void
    {
        $rawSigners = $this->extractSignersContext($submission);

        $templateCode = $submission->letterType && !empty($submission->letterType->code)
            ? strtoupper(trim($submission->letterType->code))
            : 'SK';

        $rawSigners = $this->assignSignerPositions($rawSigners, $templateCode);

        // Skip when existing records are complete, including approver_nip.
        $existingCount = $generatedLetter->signers()->count();
        $hasCompleteData = $existingCount === count($rawSigners) 
            && $generatedLetter->signers()->whereNotNull('approver_nip')->count() === count($rawSigners);

        if ($hasCompleteData) {
            return;
        }

        // Otherwise, delete and re-sync incomplete or mismatched records.
        $generatedLetter->signers()->delete();

        foreach ($rawSigners as $signer) {
            $generatedLetter->signers()->create([
                'user_id'             => $signer['user_id'] ?? null,
                'approver_name'       => $signer['name'],
                'approver_role'       => $signer['title'] ?? $signer['role'] ?? null,
                'approver_nip'        => $signer['nip'] ?? null,
                'approver_department' => $signer['department'] ?? 'Prodi D3 Teknik Informatika',
                'qr_position'         => $signer['qr_position'],
                'qr_row'              => $signer['qr_row'],
                'verification_token'  => Str::random(64),
                'approved_at'         => $signer['approved_at'] ?? null,
            ]);
        }
    }

    /**
     * Backward-compatible alias for syncSignerRecords.
     */
    public function createSignerRecords(GeneratedLetter $generatedLetter, Submission $submission): void
    {
        $this->syncSignerRecords($generatedLetter, $submission);
    }

    /** Extract structured signer data from approval logs or flow steps for single, multi, and preview modes. */
    public function extractSignersContext(Submission $submission): array
    {
        $submission->loadMissing([
            'logs.user', 
            'logs.approvalFlowStep', 
            'student.user',
            'letterType.approvalFlow.steps',
        ]);

        $studentUserId = $submission->student?->user_id;

        // Filter valid lecturer approval logs (ignore initial student creation log)
        $approvedLogs = $submission->logs
            ->filter(function ($l) use ($studentUserId) {
                $statusStr = strtolower(is_object($l->status) ? $l->status->value : (string) $l->status);
                if ($statusStr !== 'approved') {
                    return false;
                }
                if (is_null($l->approval_flow_step_id)) {
                    return false;
                }
                if ($studentUserId && $l->user_id === $studentUserId) {
                    return false;
                }
                return true;
            })
            ->sortBy('created_at')
            ->values();

        // Preview mode (no approved approval logs yet).
        if ($approvedLogs->isEmpty()) {
            $flowSteps = $submission->letterType?->approvalFlow?->steps?->sortBy('step_order');
            if ($flowSteps && $flowSteps->isNotEmpty()) {
                $previewLecturers = $this->resolvePreviewLecturers($submission->student);
                $signers = [];
                foreach ($flowSteps as $step) {
                    $role = $step->approval_role;
                    $roleVal = is_object($role) ? $role->value : (string) $role;
                    $roleTitle = method_exists($role, 'label') ? $role->label() : ($step->name ?? 'Pejabat Berwenang');
                    $lecturer = $previewLecturers[$roleVal] ?? null;

                    $signers[] = [
                        'user_id'     => $lecturer?->user_id,
                        'title'       => $roleTitle,
                        'department'  => 'Prodi D3 Teknik Informatika',
                        'name'        => $lecturer?->user?->name ?? 'Dr. Nama Dosen, M.Kom',
                        'nip'         => $lecturer?->user?->nip ?? $lecturer?->user?->username ?? '190302126',
                        'city'        => 'Yogyakarta',
                        'date'        => $this->formatIndonesianDate(now()),
                        'approved_at' => null,
                    ];
                }
                return $signers;
            }

            return [
                [
                    'user_id'     => null,
                    'title'       => 'Ketua Program Studi',
                    'department'  => 'Prodi D3 Teknik Informatika',
                    'name'        => 'Dr. Barka Satya, M.Kom',
                    'nip'         => '190302126',
                    'city'        => 'Yogyakarta',
                    'date'        => $this->formatIndonesianDate(now()),
                    'approved_at' => null,
                ],
            ];
        }

        // Deduplicate by approval step so one lecturer signing multiple steps gets separate slots.
        $uniqueStepLogs = collect();
        foreach ($approvedLogs as $log) {
            $key = $log->approval_flow_step_id ?? ('log_' . $log->id);
            $uniqueStepLogs->put($key, $log);
        }

        $sortedLogs = $uniqueStepLogs->values()->sortBy(function ($log) {
            return $log->approvalFlowStep?->step_order ?? 999;
        })->values();

        $signers = [];
        foreach ($sortedLogs as $log) {
            $title = 'Pejabat Berwenang';
            if ($log->approvalFlowStep) {
                $title = $log->approvalFlowStep->name;
                if (method_exists($log->approvalFlowStep->approval_role, 'label')) {
                    $title = $log->approvalFlowStep->approval_role->label();
                }
            }

            $studentDept = $submission->student?->department ?? 'Prodi D3 Teknik Informatika';
            if (!empty($studentDept) && !str_starts_with(strtolower($studentDept), 'prodi')) {
                $studentDept = 'Prodi ' . $studentDept;
            }

            $signers[] = [
                'user_id'     => $log->user_id,
                'title'       => $title,
                'department'  => $studentDept,
                'name'        => $log->user ? $log->user->name : 'Sistem Kampus',
                'nip'         => $log->user ? ($log->user->nip ?? $log->user->username ?? '-') : '-',
                'city'        => 'Yogyakarta',
                'date'        => $this->formatIndonesianDate($log->created_at ?? now()),
                'approved_at' => $log->created_at,
            ];
        }

        return $signers;
    }

    /** Resolve related lecturers for preview mode when no approval logs exist yet. */
    protected function resolvePreviewLecturers($student): array
    {
        $studentLecturers = collect();
        if ($student?->id) {
            $studentLecturers = \App\Models\StudentLecturer::query()
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->with('lecturer.user')
                ->get();
        }

        $positions = \App\Models\LecturerPosition::query()
            ->where('is_active', true)
            ->with('lecturer.user')
            ->get();

        $academicAdvisor = $studentLecturers->firstWhere('lecturer_role', \App\Enums\ApprovalRole::ACADEMIC_ADVISOR->value)?->lecturer;

        $supervisor = $studentLecturers->first(function ($sl) {
            $role = is_object($sl->lecturer_role) ? $sl->lecturer_role->value : (string) $sl->lecturer_role;
            return in_array($role, [
                \App\Enums\ApprovalRole::THESIS_SUPERVISOR->value,
                \App\Enums\ApprovalRole::INTERNSHIP_SUPERVISOR->value,
                'THESIS_SUPERVISOR',
                'INTERNSHIP_SUPERVISOR',
            ], true);
        })?->lecturer;

        $kaprodi = $positions->firstWhere('position', \App\Enums\ApprovalRole::HEAD_OF_STUDY_PROGRAM->value)?->lecturer;

        return [
            \App\Enums\ApprovalRole::ACADEMIC_ADVISOR->value       => $academicAdvisor,
            \App\Enums\ApprovalRole::HEAD_OF_STUDY_PROGRAM->value  => $kaprodi,
            \App\Enums\ApprovalRole::INTERNSHIP_SUPERVISOR->value  => $supervisor,
            \App\Enums\ApprovalRole::THESIS_SUPERVISOR->value      => $supervisor,
        ];
    }

    /** Assign qr_position and qr_row to each signer based on the letter type layout config. */
    public function assignSignerPositions(array $signers, string $templateCode): array
    {
        $layout = config("letter_signature.layouts.{$templateCode}");

        if (!$layout) {
            $signerCount = count($signers);
            $defaultConfig = config('letter_signature.default');
            if (is_array($defaultConfig) && isset($defaultConfig[$signerCount])) {
                $layout = $defaultConfig[$signerCount];
            } elseif (is_array($defaultConfig) && isset($defaultConfig[0])) {
                $layout = $defaultConfig;
            } else {
                $layout = match($signerCount) {
                    2 => [['left', 'right']],
                    3 => [['left', 'center', 'right']],
                    default => [['right']],
                };
            }
        }

        // Build the list of position slots from the layout config.
        $slots = [];
        foreach ($layout as $rowIndex => $rowPositions) {
            foreach ($rowPositions as $position) {
                if (!in_array($position, ['left', 'center', 'right'], true)) {
                    continue;
                }
                $slots[] = [
                    'row'      => $rowIndex,
                    'position' => $position,
                ];
            }
        }

        foreach ($signers as $index => &$signer) {
            if (isset($slots[$index])) {
                $slot = $slots[$index];
            } else {
                // Extra signers beyond the config get additional rows.
                $extraIndex        = $index - count($slots);
                $fallbackPositions = ['left', 'center', 'right'];
                $slot = [
                    'row'      => count($layout) + intdiv($extraIndex, 3),
                    'position' => $fallbackPositions[$extraIndex % 3],
                ];
            }

            $signer['qr_position'] = $slot['position'];
            $signer['qr_row']      = $slot['row'];
        }
        unset($signer);

        return $signers;
    }

    /** Build per-signer view data including generated QR data URIs. */
    public function buildSignerViewData(GeneratedLetter $generatedLetter, QrCodeService $qrCodeService): \Illuminate\Support\Collection
    {
        return $generatedLetter->signers()
            ->with('user')
            ->orderBy('qr_row')
            ->orderBy('id')
            ->get()
            ->map(function ($signer) use ($qrCodeService) {
                $url = route('public.verify.approver', ['token' => $signer->verification_token]);
                $signer->qr_data_uri = $qrCodeService->generateDataUri($url);
                $signer->nip = $signer->approver_nip ?? $signer->user?->nip ?? $signer->user?->username ?? '-';
                $signer->department = $signer->approver_department ?? 'Prodi D3 Teknik Informatika';
                return $signer;
            });
    }

    /**
     * Format a date string or DateTime object into Indonesian long date format (e.g., 14 Agustus 2026).
     */
    public function formatIndonesianDate($value): string
    {
        if (empty($value)) {
            return '-';
        }

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        try {
            $date = null;
            if ($value instanceof \DateTimeInterface) {
                $date = \Carbon\Carbon::instance($value);
            } elseif (is_string($value)) {
                $date = \Carbon\Carbon::parse($value);
            }

            if ($date) {
                $day   = $date->format('j');
                $month = $months[(int)$date->format('n')];
                $year  = $date->format('Y');
                return "{$day} {$month} {$year}";
            }
        } catch (\Throwable $e) {
            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * Convert integer month to Roman numeral string.
     */
    protected function getRomanMonth(int $month): string
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];

        return $map[$month] ?? 'I';
    }
}
