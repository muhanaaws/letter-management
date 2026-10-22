<?php

namespace App\Services;

use App\Enums\ApprovalRole;
use App\Models\LecturerPosition;
use App\Models\LetterTemplate;
use App\Models\StudentLecturer;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\View\View;

class LetterPreviewService
{
    public function __construct(
        protected TemplateRendererService $renderer,
        protected LetterGeneratorService $letterGenerator,
        protected PdfGenerationService $pdfService
    ) {}

    /**
     * Render Admin Template Preview (displays raw placeholders as pill badges).
     *
     * @param LetterTemplate $template
     * @return View
     */
    public function renderAdminPreview(LetterTemplate $template): View
    {
        $bodyHtml = $template->body_content ?? '<div class="doc-title-main">SURAT DOKUMEN AKADEMIK</div>';
        $title = $template->name ?? 'Preview Template Surat';

        return $this->renderer->renderAdminPreview($bodyHtml, $title);
    }

    /**
     * Render Lecturer or Student actual submission HTML view with real submission data.
     *
     * @param Submission $submission
     * @param array<string, mixed> $extraContext
     * @return View
     */
    public function renderSubmissionView(Submission $submission, array $extraContext = [], bool $isIframeMode = false): View
    {
        $submission->loadMissing([
            'letterType.activeTemplate',
            'student.user',
            'generatedLetter.signers',
            'logs.user',
            'logs.approvalFlowStep',
            'groupMembers.student.user',
        ]);

        $contextData = $this->buildSubmissionContext($submission, $extraContext);

        $activeTemplate = $submission->letterType?->activeTemplate;
        $bodyTemplateHtml = $activeTemplate?->body_content ?? '<div class="doc-title-main">SURAT DOKUMEN AKADEMIK</div>';

        $generatedLetter = $submission->generatedLetter;

        if ($generatedLetter) {
            $this->letterGenerator->syncSignerRecords($generatedLetter, $submission);
            $generatedLetter->unsetRelation('signers');
        }

        // Use stored signers with per-signer QR when issued, otherwise use mapped preview signers.
        if ($generatedLetter && $generatedLetter->signers->isNotEmpty()) {
            $qrService = app(\App\Services\QrCodeService::class);
            $signersData = $this->letterGenerator->buildSignerViewData($generatedLetter, $qrService);
            $qrToken = $generatedLetter->qr_token;
        } else {
            $rawSigners = $this->letterGenerator->extractSignersContext($submission);
            $templateCode = $submission->letterType && !empty($submission->letterType->code)
                ? strtoupper(trim($submission->letterType->code))
                : 'SK';
            $signersWithPos = $this->letterGenerator->assignSignerPositions($rawSigners, $templateCode);
            $signersData = collect($signersWithPos)->map(function ($s) {
                return (object) $s;
            });
            $qrToken = $generatedLetter?->qr_token ?? ('PREVIEW-SR-' . $submission->id);
        }

        $embeddedCss = null;
        if ($isIframeMode || !empty($extraContext['is_iframe'])) {
            $embeddedCss = 'body { background: #ffffff !important; padding: 0 !important; overflow: hidden !important; } .sheet-wrap { box-shadow: none !important; margin: 0 auto !important; max-width: 100% !important; }';
        }

        return $this->renderer->renderFullDocumentView(
            $bodyTemplateHtml,
            $contextData,
            [],
            $submission->letterType?->name ?? 'Surat Resmi AMIKOM',
            $signersData,
            $qrToken,
            $embeddedCss
        );
    }


    /**
     * Build context placeholder array for a submission.
     *
     * @param Submission $submission
     * @param array<string, mixed> $extraContext
     * @return array<string, mixed>
     */
    protected function buildSubmissionContext(Submission $submission, array $extraContext = []): array
    {
        $student = $submission->student;
        $add = is_array($submission->additional_data) ? $submission->additional_data : [];

        $groupData = $this->buildGroupMembersData($submission, $student);
        $lecturers = $this->resolveLecturers($student);

        $letterCode = $submission->letterType && !empty($submission->letterType->code)
            ? strtoupper(trim($submission->letterType->code))
            : 'SK';
        $romanMonth = $this->getRomanMonth((int) date('n'));
        $year = date('Y');
        $previewNumber = sprintf('%s/PRATINJAU-%d/D3TI/AMIKOM/%s/%s', $letterCode, $submission->id, $romanMonth, $year);

        $academicAdvisor = $lecturers['academicAdvisor'];
        $supervisor = $lecturers['supervisor'];
        $kaprodi = $lecturers['kaprodi'];
        $dekan = $lecturers['dekan'];

        return array_merge([
            'is_admin_preview' => false,

            // Letter & Student Information
            'letter_number' => $submission->generatedLetter?->letter_number ?? $previewNumber,
            'student_name' => $student?->user?->name ?? '-',
            'student_number' => $student?->student_number ?? '-',
            'study_program' => $student?->department ?? 'D3 Teknik Informatika',
            'semester' => (string) ($add['semester'] ?? $student?->semester ?? '5'),
            'gpa' => number_format((float)($add['gpa'] ?? $student?->gpa ?? 0), 2),
            'total_credits' => ($add['total_credits'] ?? $student?->total_credits ?? '0') . ' SKS',
            'purpose' => $submission->purpose ?? '-',

            // Group & Thesis Information
            'group_name' => $submission->group_name ?? ($add['group_name'] ?? '-'),
            'group_leader_name' => $student?->user?->name ?? '-',
            'group_leader_number' => $student?->student_number ?? '-',
            'group_members' => $groupData['tableHtml'],
            'group_members_list' => $groupData['textList'],
            'thesis_title' => $add['thesis_title'] ?? $submission->thesis_title ?? '-',

            // Company / Institution Information
            'company_name' => $add['company_name'] ?? $submission->company_name ?? '-',
            'company_address' => $add['company_address'] ?? $submission->company_address ?? '-',
            'start_date' => $this->formatIndonesianDate($add['start_date'] ?? $submission->start_date ?? null),
            'end_date' => $this->formatIndonesianDate($add['end_date'] ?? $submission->end_date ?? null),
            'academic_year' => $add['academic_year'] ?? '2025/2026',
            'submission_date' => $this->formatIndonesianDate($submission->submitted_at ?? null),
            'print_date' => $this->formatIndonesianDate(now()),
            'approval_date' => $this->formatIndonesianDate($submission->logs()->latest()->first()?->created_at ?? now()),

            // Dosen & Pejabat Information
            'academic_advisor_name' => $academicAdvisor?->user?->name ?? 'Dr. Ahmad Wijaya',
            'academic_advisor_nip' => $academicAdvisor?->employee_number ?? $academicAdvisor?->national_lecturer_number ?? $academicAdvisor?->user?->nip ?? '19870001',
            'head_of_program_name' => $kaprodi?->user?->name ?? 'Dr. Barka Satya, M.Kom',
            'head_of_program_nip' => $kaprodi?->employee_number ?? $kaprodi?->national_lecturer_number ?? $kaprodi?->user?->nip ?? '190302126',
            'supervisor_name' => $supervisor?->user?->name ?? $academicAdvisor?->user?->name ?? 'Ferry Wahyu Wibowo, S.Kom., M.Cs.',
            'supervisor_nip' => $supervisor?->employee_number ?? $supervisor?->national_lecturer_number ?? $supervisor?->user?->nip ?? $academicAdvisor?->employee_number ?? '190302088',
            'dean_name' => $dekan?->user?->name ?? 'Hanif Al Fatta, M.Kom, Ph.D',
            'dean_nip' => $dekan?->employee_number ?? $dekan?->national_lecturer_number ?? $dekan?->user?->nip ?? '190302001',
        ], $extraContext);
    }

    /**
     * Build group members HTML table and text list.
     *
     * @param Submission $submission
     * @param \App\Models\Student|null $student
     * @return array{tableHtml: string, textList: string}
     */
    protected function buildGroupMembersData(Submission $submission, $student): array
    {
        $membersList = collect();
        if ($student) {
            $membersList->push($student);
        }
        foreach ($submission->groupMembers as $gm) {
            if ($gm->student && $gm->student->id !== $student?->id) {
                $membersList->push($gm->student);
            }
        }

        $groupMembersTableHtml = '';
        $groupMembersTextList = '';

        if ($membersList->isNotEmpty()) {
            $groupMembersTableHtml = '<table class="info-table" style="margin-top: 6pt; margin-bottom: 10pt; width: 100%; border-collapse: collapse;">'
                . '<thead><tr style="background-color: #f3f4f6; font-weight: bold; text-align: left;">'
                . '<th style="width: 30pt; padding: 4pt; text-align: center; border: 1px solid #d1d5db;">No</th>'
                . '<th style="width: 100pt; padding: 4pt; border: 1px solid #d1d5db;">NIM</th>'
                . '<th style="padding: 4pt; border: 1px solid #d1d5db;">Nama Mahasiswa</th>'
                . '<th style="width: 120pt; padding: 4pt; border: 1px solid #d1d5db;">Program Studi</th>'
                . '</tr></thead><tbody>';

            foreach ($membersList as $idx => $m) {
                $num = $idx + 1;
                $nim = e($m->student_number ?? '-');
                $name = e($m->user?->name ?? '-');
                $prodi = e($m->department ?? 'D3 Teknik Informatika');

                $groupMembersTableHtml .= "<tr>"
                    . "<td style=\"text-align: center; padding: 4pt; border: 1px solid #d1d5db;\">{$num}</td>"
                    . "<td style=\"padding: 4pt; border: 1px solid #d1d5db;\">{$nim}</td>"
                    . "<td style=\"padding: 4pt; border: 1px solid #d1d5db;\">{$name}</td>"
                    . "<td style=\"padding: 4pt; border: 1px solid #d1d5db;\">{$prodi}</td>"
                    . "</tr>";
            }
            $groupMembersTableHtml .= '</tbody></table>';

            $groupMembersTextList = $membersList->map(function ($m, $i) {
                return ($i + 1) . '. ' . ($m->user?->name ?? '-') . ' (' . ($m->student_number ?? '-') . ')';
            })->implode(', ');
        }

        return [
            'tableHtml' => $groupMembersTableHtml,
            'textList' => $groupMembersTextList,
        ];
    }

    /**
     * Resolve related Lecturers (Academic Advisor, Supervisor, Kaprodi, Dekan) for student.
     *
     * @param \App\Models\Student|null $student
     * @return array<string, mixed>
     */
    protected function resolveLecturers($student): array
    {
        $studentLecturers = collect();
        if ($student?->id) {
            $studentLecturers = StudentLecturer::query()
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->with('lecturer.user')
                ->get();
        }

        $positions = LecturerPosition::query()
            ->where('is_active', true)
            ->with('lecturer.user')
            ->get();

        $academicAdvisor = $studentLecturers->firstWhere('lecturer_role', ApprovalRole::ACADEMIC_ADVISOR->value)?->lecturer;

        $supervisor = $studentLecturers->first(function ($sl) {
            $role = is_object($sl->lecturer_role) ? $sl->lecturer_role->value : (string) $sl->lecturer_role;
            return in_array($role, [
                ApprovalRole::THESIS_SUPERVISOR->value,
                ApprovalRole::INTERNSHIP_SUPERVISOR->value,
                'THESIS_SUPERVISOR',
                'INTERNSHIP_SUPERVISOR',
            ], true);
        })?->lecturer;

        $kaprodi = $positions->firstWhere('position', ApprovalRole::HEAD_OF_STUDY_PROGRAM->value)?->lecturer;

        $dekan = $positions->first(function ($pos) {
            $p = is_object($pos->position) ? $pos->position->value : (string) $pos->position;
            return in_array($p, ['DEAN', 'DEKAN', 'dekan', ApprovalRole::HEAD_OF_STUDY_PROGRAM->value], true);
        })?->lecturer;

        return [
            'academicAdvisor' => $academicAdvisor,
            'supervisor' => $supervisor,
            'kaprodi' => $kaprodi,
            'dekan' => $dekan,
        ];
    }

    /**
     * Render and download PDF directly for a submission.
     */
    public function downloadSubmissionPdf(Submission $submission)
    {
        $view = $this->renderSubmissionView($submission);
        return $this->pdfService->downloadPdf($submission, $view->render());
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
                $day = $date->format('j');
                $month = $months[(int)$date->format('n')];
                $year = $date->format('Y');
                return "{$day} {$month} {$year}";
            }
        } catch (\Throwable $e) {
            return (string) $value;
        }

        return (string) $value;
    }

    /**
     * Get Roman numeral representation of month integer (1 - 12).
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
