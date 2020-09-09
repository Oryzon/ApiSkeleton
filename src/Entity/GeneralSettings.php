<?php

namespace App\Entity;

class GeneralSettings {
    const TITLE = "ApiSkeleton";

    const TOKEN_EXPIRATION_MOMENT = 604800; // 7 days
    const TOKEN_ASKABLE_EVERY = 1200; // 20 minutes

    const ACCOUNT_ACTIVATION_NEEDED = true;
    const DEFAULT_PASSWORD_LENGTH = 8;

    // Mail default config
    const DEFAULT_MAIL_FROM = 'no-reply@apiskeleton.localhost';
    const DEFAULT_MAIL_TEMPLATE_FOLDER = 'default';

    // Constant for available role
    const AVAILABLE_ROLE = ["ROLE_USER", "ROLE_ADMIN"];
}