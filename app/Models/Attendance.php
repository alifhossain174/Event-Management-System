<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Attendance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'clocked_in_at' => 'immutable_datetime', 'clocked_out_at' => 'immutable_datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(StaffAssignment::class, 'staff_assignment_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id')->withTrashed();
    }
}
