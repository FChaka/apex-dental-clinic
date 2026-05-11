<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\LeaveRequest;
use App\Models\Tenant\StaffMember;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestDecided
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  'approved'|'rejected'  $decision
     */
    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $decision,
        public StaffMember $decidedBy,
    ) {}
}
