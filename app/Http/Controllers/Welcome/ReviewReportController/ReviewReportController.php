<?php

namespace App\Http\Controllers\Welcome\ReviewReportController;

use App\Http\Controllers\User\ReviewReportController\ReviewReportController as UserReviewReportController;

class ReviewReportController extends UserReviewReportController
{
    protected string $panel = 'welcome';
}
