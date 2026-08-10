<?php

namespace App\Billing\Models;

use Database\Factories\Billing\CustomReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(CustomReceiptFactory::class)]
class Receipt extends Model
{
    use HasFactory;
}
