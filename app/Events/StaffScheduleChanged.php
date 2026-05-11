<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\StaffMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffScheduleChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public StaffMember $affectedStaff,
        public StaffMember $changedBy,
    ) {}
}
