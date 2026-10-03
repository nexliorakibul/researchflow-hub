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
    'api_contact_email' => trim(getenv('RFH_API_CONTACT_EMAIL') ?: ''),
    'openalex_api_key' => trim(getenv('RFH_OPENALEX_API_KEY') ?: ''),
    'semantic_scholar_api_key' => trim(getenv('RFH_SEMANTIC_SCHOLAR_API_KEY') ?: ''),
    'orcid_client_id' => trim(getenv('RFH_ORCID_CLIENT_ID') ?: ''),
    'orcid_client_secret' => trim(getenv('RFH_ORCID_CLIENT_SECRET') ?: ''),
    'orcid_redirect_uri' => trim(getenv('RFH_ORCID_REDIRECT_URI') ?: ''),
    'scholarly_api_timeout' => max(2, min(15, (int) (getenv('RFH_API_TIMEOUT') ?: 8))),
];
