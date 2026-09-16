<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class CompanyService
{
    public function __construct(private readonly AuditService $audit) {}

    public function current(): Company
    {
        return Company::query()->firstOrCreate(['id' => 1], ['name' => config('app.name')]);
    }

    public function update(Company $company, array $data, User $actor): Company
    {
        $logo = Arr::pull($data, 'logo');
        $newPath = null;

        try {
            if ($logo instanceof UploadedFile) {
                $newPath = $logo->store('branding', 'public');
                $data += [
                    'logo_disk' => 'public',
                    'logo_path' => $newPath,
                    'logo_original_name' => $logo->getClientOriginalName(),
                    'logo_mime_type' => $logo->getMimeType(),
                    'logo_size' => $logo->getSize(),
                ];
            }

            return DB::transaction(function () use ($company, $data, $actor, $newPath) {
                $before = $this->snapshot($company);
                $oldDisk = $company->logo_disk;
                $oldPath = $company->logo_path;

                $company->update($data);
                $this->audit->record('company.updated', $company, $before, $this->snapshot($company), $actor);

                if ($newPath && $oldDisk && $oldPath) {
                    DB::afterCommit(fn () => Storage::disk($oldDisk)->delete($oldPath));
                }

                return $company->fresh();
            });
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }

            throw $exception;
        }
    }

    private function snapshot(Company $company): array
    {
        return $company->only([
            'name', 'email', 'phone', 'website', 'address_line_1', 'address_line_2',
            'city', 'state', 'postal_code', 'country_code', 'logo_original_name',
            'logo_mime_type', 'logo_size',
        ]);
    }
}
