<?php

return [
    // Never enable this on production casually. Code changes belong in a
    // reviewed CI/CD pipeline, not in a web request.
    'allow_code_generation' => (bool) env('DEVELOPER_STUDIO_ALLOW_CODE_GENERATION', false),
];
