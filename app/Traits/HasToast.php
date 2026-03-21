<?php

namespace App\Traits;

trait HasToast
{
    /**
     * Send a success notification.
     * Works for both same-page updates and redirects.
     */
    public function toastSuccess(string $message): void
    {
        session()->flash('success', $message);
        $this->dispatch('notify', message: $message, type: 'success');
    }

    /**
     * Send an error notification.
     */
    public function toastError(string $message): void
    {
        session()->flash('error', $message);
        $this->dispatch('notify', message: $message, type: 'error');
    }
}
