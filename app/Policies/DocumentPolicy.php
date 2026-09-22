<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentAccessService;

final class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $user->hasPermission('documents.view')
            && app(DocumentAccessService::class)->canView($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('documents.create');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->hasPermission('documents.update') && $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasPermission('documents.delete') && $this->view($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        return $user->hasPermission('documents.download') && $this->view($user, $document);
    }
}
