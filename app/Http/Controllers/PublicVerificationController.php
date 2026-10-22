<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\GeneratedLetter;
use App\Models\GeneratedLetterSigner;
use Illuminate\Http\Request;


class PublicVerificationController extends Controller
{
    /**
     * Handle public letter verification via QR Code token.
     *
     * @param string $token
     * @return \Illuminate\Contracts\View\View
     */
    public function verify(string $token)
    {
        $generatedLetter = GeneratedLetter::with([
            'submission.student.user',
            'submission.letterType',
            'submission.groupMembers.student.user',
            'submission.logs' => function ($query) {
                $query->with(['user', 'approvalFlowStep'])->latest();
            },
        ])
        ->where('qr_token', $token)
        ->orWhere('letter_number', $token)
        ->first();

        if (!$generatedLetter || !$generatedLetter->submission) {
            return view('verify.show', [
                'isValid' => false,
                'isPending' => false,
                'token' => $token,
                'message' => 'QR Code atau Token Verifikasi tidak ditemukan dalam database sistem.',
            ]);
        }

        $submission = $generatedLetter->submission;

        // Verify that submission is actually approved
        $isApproved = $submission->status === SubmissionStatus::APPROVED 
            || $submission->status === SubmissionStatus::GENERATED;

        if (!$isApproved) {
            return view('verify.show', [
                'isValid' => false,
                'isPending' => true,
                'token' => $token,
                'message' => 'Dokumen surat ini masih dalam proses pengajuan dan belum mendapatkan persetujuan akhir.',
            ]);
        }

        // Parse Student & Group Data safely
        $isGroup = (bool)$submission->letterType->allow_group_submission || $submission->groupMembers->count() > 0;
        $student = $submission->student;
        $studentUser = $student?->user;
        
        $studentName = $studentUser?->name ?? 'Mahasiswa';
        $studentNim = $student?->student_number ?? '-';

        $groupData = null;
        if ($isGroup) {
            $groupMembers = $submission->groupMembers;
            $membersList = [];

            foreach ($groupMembers as $idx => $gm) {
                $mStudent = $gm->student;
                $membersList[] = [
                    'no' => $idx + 1,
                    'name' => $mStudent?->user?->name ?? '-',
                    'nim' => $mStudent?->student_number ?? '-',
                    'is_leader' => $gm->student_id == $submission->student_id,
                ];
            }

            // Extract title or fallback group title
            $groupTitle = 'Tim / Kelompok Mahasiswa';
            if (preg_match('/judul\s*["“]([^"”]+)["”]/i', $submission->purpose ?? '', $matches)) {
                $groupTitle = $matches[1];
            }

            $groupData = [
                'title' => $groupTitle,
                'leader_name' => $studentName,
                'leader_nim' => $studentNim,
                'members' => $membersList,
            ];
        }

        // Parse Approval Steps / Signature Logs (Filtering non-lecturers & duplicate users)
        $approvedLogs = $submission->logs
            ->filter(function ($log) use ($submission) {
                $statusStr = strtolower(is_object($log->status) ? $log->status->value : (string)$log->status);
                if ($statusStr !== 'approved' || is_null($log->approval_flow_step_id)) {
                    return false;
                }
                if ($submission->student && $log->user_id === $submission->student->user_id) {
                    return false;
                }
                return true;
            })
            ->sortBy('created_at')
            ->values();

        $uniqueLogs = collect();
        foreach ($approvedLogs as $l) {
            $uniqueLogs->put($l->user_id ?? ('log_' . $l->id), $l);
        }

        $approvalLogs = $uniqueLogs->map(function ($log) {
            $stepName = 'Pihak Berwenang';
            if ($log->approvalFlowStep) {
                $stepName = $log->approvalFlowStep->name;
                if (method_exists($log->approvalFlowStep->approval_role, 'label')) {
                    $stepName = $log->approvalFlowStep->approval_role->label();
                }
            }

            return [
                'role_name' => $stepName,
                'approver_name' => $log->user ? $log->user->name : 'Sistem Kampus',
                'approver_nip' => $log->user ? ($log->user->nip ?? $log->user->username ?? '-') : '-',
                'approved_at' => $log->created_at,
            ];
        })->values();

        return view('verify.show', [
            'isValid' => true,
            'token' => $token,
            'generatedLetter' => $generatedLetter,
            'submission' => $submission,
            'letterNumber' => $generatedLetter->letter_number,
            'letterTypeName' => $submission->letterType->name,
            'issuedDate' => $generatedLetter->generated_at ?? $submission->updated_at,
            'student' => $student,
            'studentName' => $studentName,
            'studentNim' => $studentNim,
            'isGroup' => $isGroup,
            'groupData' => $groupData,
            'approvalLogs' => $approvalLogs,
        ]);
    }

    /** Handle per-approver verification via a unique signer token. */
    public function verifyApprover(string $token)
    {
        $signer = GeneratedLetterSigner::with([
            'generatedLetter.submission.letterType',
            'generatedLetter.submission.student.user',
        ])
            ->where('verification_token', $token)
            ->first();

        if (!$signer) {
            return view('verify.approver', [
                'isValid'  => false,
                'token'    => $token,
                'message'  => 'Token verifikasi penyetuju tidak ditemukan dalam sistem.',
                'signer'   => null,
            ]);
        }

        $generatedLetter = $signer->generatedLetter;
        $submission      = $generatedLetter?->submission;

        // Ensure the letter is approved before showing verification.
        $isApproved = $submission && (
            $submission->status === SubmissionStatus::APPROVED ||
            $submission->status === SubmissionStatus::GENERATED
        );

        if (!$isApproved) {
            return view('verify.approver', [
                'isValid'  => false,
                'token'    => $token,
                'message'  => 'Surat ini belum mendapatkan persetujuan akhir.',
                'signer'   => $signer,
            ]);
        }

        return view('verify.approver', [
            'isValid'        => true,
            'token'          => $token,
            'signer'         => $signer,
            'generatedLetter' => $generatedLetter,
            'letterNumber'   => $generatedLetter->letter_number,
            'letterTypeName' => $submission->letterType?->name ?? '-',
            'studentName'    => $submission->student?->user?->name ?? '-',
            'studentNim'     => $submission->student?->student_number ?? '-',
        ]);
    }
}

