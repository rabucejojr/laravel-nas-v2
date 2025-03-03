<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    //
    protected $table = 'documents';

    protected $fillable = ['id', 'tracking_number', 'filename', 'title', 'subject', 'status', 'date_uploaded', 'deadline'];
}
