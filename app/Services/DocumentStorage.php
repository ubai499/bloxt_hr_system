<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentStorage
{
    public function create(array $data, User $actor, ?UploadedFile $attachment = null, string $directory = 'documents'): Document
    {
        $path = null;
        try {
            if ($attachment) {
                $path = $attachment->store($directory, 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['attachment' => 'The file could not be saved. Please try again.']);
                }
            }

            return DB::transaction(function () use ($data, $actor, $attachment, $path) {
                $employee = isset($data['employee_id']) ? User::findOrFail($data['employee_id']) : null;
                $document = Document::create([
                    ...$data, 'employee_name' => $employee?->name,
                    'uploaded_by' => $actor->id, 'uploader_name' => $actor->name,
                    'upload_date' => today(), 'status' => 'Valid', 'archive_status' => 'Active', 'version' => 1,
                    'file_path' => $path,
                    'file_name' => $attachment ? mb_substr(basename(str_replace('\\', '/', $attachment->getClientOriginalName())), 0, 255) : null,
                    'file_mime' => $attachment?->getMimeType(), 'file_size' => $attachment?->getSize(),
                ]);
                DB::table('document_activities')->insert([
                    'document_id' => $document->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name,
                    'action' => 'Document created', 'metadata' => json_encode($document->toArray(), JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);

                return $document;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
