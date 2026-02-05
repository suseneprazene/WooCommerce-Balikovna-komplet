<?php

namespace Balikovna\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningHour extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "opening_hours";

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function day()
    {
        return $this->belongsTo(Day::class);
    }
}