<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoryBalance extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $dates = ['created_at'];

    protected $table = 'history_balance';

    protected $guarded = ['id'];
}
