<?php

declare(strict_types=1);

require('/functions.php');

define('GITHUB_ACCESS_TOKEN', getenv('INPUT_GITHUB_ACCESS_TOKEN'));
define('GITHUB_REPOSITORY', getenv('GITHUB_REPOSITORY'));

$github_repository_name_parts = explode('/', GITHUB_REPOSITORY);

define('GITHUB_OWNER_NAME', $github_repository_name_parts[0]);
define('GITHUB_REPOSITORY_NAME', $github_repository_name_parts[1]);

$reminder_data = getReminderData(getGitHubClient(GITHUB_ACCESS_TOKEN),
                                 GITHUB_OWNER_NAME,
                                 GITHUB_REPOSITORY_NAME);

// TODO: Send reminders via email/Slack message