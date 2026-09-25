<?php

declare(strict_types=1);

return [
    'name' => 'ResearchFlow Hub',
    'environment' => getenv('RFH_APP_ENV') ?: 'development',
    'base_url' => rtrim(getenv('RFH_BASE_URL') ?: '', '/'),
    'timezone' => getenv('RFH_TIMEZONE') ?: 'UTC',
    'session_name' => 'researchflow_session',
    'session_path' => getenv('RFH_SESSION_PATH') ?: '',
    'trust_proxy' => filter_var(getenv('RFH_TRUST_PROXY') ?: false, FILTER_VALIDATE_BOOLEAN),
];
