<?php

namespace App\Http\Controllers\Welcome\LegalWebController;

use App\Http\Controllers\User\LegalWebController\LegalWebController as UserLegalWebController;

class LegalWebController extends UserLegalWebController
{
    protected string $viewPrefix = 'Welcome';
}
