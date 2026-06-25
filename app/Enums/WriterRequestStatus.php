<?php

declare(strict_types=1);

namespace App\Enums;

enum WriterRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('writer_request.status.'.$this->value);
    }
}
