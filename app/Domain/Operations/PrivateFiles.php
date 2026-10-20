<?php

namespace App\Domain\Operations;

use App\Domain\CoreHR\EmployeeVisibility;
use App\Domain\Identity\Audit;
use App\Domain\Workflow\WorkflowAccess;
use App\Models\User;
use App\Models\WorkflowRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PrivateFiles
{
    public function upload(User $actor, int $requestId, UploadedFile $file, string $key): array
    {
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,application/pdf']])->validate();
        $mime = $file->getMimeType();
        if (str_starts_with($mime, 'image/')) {
            $size = @getimagesize($file->getRealPath());
            abort_unless($size && $size[0] > 0 && $size[1] > 0 && $size[0] <= 8000 && $size[1] <= 8000 && $size[0] * $size[1] <= 20000000, 422, 'Dimensi foto tidak valid.');
            $decoded = @imagecreatefromstring(file_get_contents($file->getRealPath()));
            abort_unless($decoded !== false, 422, 'Foto tidak dapat dibaca.');
            imagedestroy($decoded);
        } else {
            $stream = fopen($file->getRealPath(), 'rb');
            $prefix = fread($stream, 5);
            fclose($stream);
            abort_unless($prefix === '%PDF-', 422);
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $stored = null;
        try {
            return Mutation::run($actor, 'attachment.upload', $key, ['request_id' => $requestId, 'sha256' => $hash], function ($u) use ($requestId) {
                $request = WorkflowRequest::findOrFail($requestId);
                abort_unless($this->canWrite($u, $request), 404);
                abort_unless(in_array($request->status, ['draft', 'in_review', 'blocked']), 409);
            }, function ($u) use ($requestId, $file, $mime, $hash, &$stored) {
                $request = WorkflowRequest::findOrFail($requestId);
                $classification = in_array($request->kind, ['leave', 'leave_cancel']) ? 'medical' : (in_array($request->kind, ['payroll', 'reimbursement']) ? 'payroll' : 'general');
                $stored ??= 'evidence/'.Str::uuid().($mime === 'application/pdf' ? '.pdf' : ($mime === 'image/png' ? '.png' : '.jpg'));
                abort_unless(Storage::disk('local')->put($stored, file_get_contents($file->getRealPath())), 503, 'Penyimpanan berkas belum tersedia.');
                $id = DB::table('private_attachments')->insertGetId(['uploaded_by' => $u->id, 'subject_type' => 'workflow_request', 'subject_id' => $requestId, 'classification' => $classification, 'path' => $stored, 'mime' => $mime, 'size' => $file->getSize(), 'sha256' => $hash, 'created_at' => now()]);
                Audit::record($u->id, 'attachment.uploaded', 'private_attachment', $id, ['subject_type' => 'workflow_request', 'subject_id' => $requestId, 'classification' => $classification]);

                return ['id' => $id, 'mime' => $mime, 'size' => $file->getSize()];
            });
        } catch (\Throwable $e) {
            if ($stored) {
                Storage::disk('local')->delete($stored);
            } throw $e;
        }
    }

    public function download(User $actor, int $id)
    {
        $file = DB::table('private_attachments')->where('id', $id)->firstOrFail();
        abort_unless($file->subject_type === 'workflow_request', 404);
        $request = WorkflowRequest::findOrFail($file->subject_id);
        abort_unless(WorkflowAccess::visible($actor, $request), 404);
        if ($file->classification === 'medical') {
            abort_unless($this->canWrite($actor, $request), 404);
        }
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        Audit::record($actor->id, 'attachment.downloaded', 'private_attachment', $file->id, ['classification' => $file->classification]);

        return Storage::disk('local')->download($file->path, 'evidence-'.$file->id.'.'.match ($file->mime) {
            'image/png' => 'png','image/jpeg' => 'jpg',default => 'pdf'
        }, ['Content-Type' => $file->mime, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function canWrite(User $actor, WorkflowRequest $request): bool
    {
        if ($request->submitted_by === $actor->id || ($actor->employee_id && $actor->employee_id === $request->employee_id)) {
            return true;
        }

        return in_array($request->kind, ['leave', 'leave_cancel', 'correction', 'overtime', 'swap', 'contract', 'letter', 'career', 'onboarding', 'offboarding']) && $request->employee_id && $actor->grants()->whereIn('role', ['hr_admin', 'hr_officer'])->exists() && EmployeeVisibility::query($actor, today()->toDateString())->whereKey($request->employee_id)->exists();
    }
}
