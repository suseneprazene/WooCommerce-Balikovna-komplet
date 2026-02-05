<?php

namespace Balikovna\Models;

use Illuminate\Database\Eloquent\Model;

class Day extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = "days";

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}