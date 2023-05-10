<?php

declare(strict_types=1);

require('/functions.php');

define('GITHUB_ACCESS_TOKEN', getenv('INPUT_GITHUB_ACCESS_TOKEN'));
define('GITHUB_REPOSITORY', getenv('GITHUB_REPOSITORY'));
define('GITHUB_OWNER_NAME', explode('/', GITHUB_REPOSITORY)[0]);
define('GITHUB_REPOSITORY_NAME', explode('/', GITHUB_REPOSITORY)[1]);
define('MINIMUM_ELAPSED_HOURS', getenv('INPUT_MINIMUM_ELAPSED_HOURS'));

$reminder_data = getReminderData(getGitHubClient(GITHUB_ACCESS_TOKEN),
                                 GITHUB_OWNER_NAME,
                                 GITHUB_REPOSITORY_NAME,
                                 MINIMUM_ELAPSED_HOURS);

// TODO: Send reminders via email/Slack message
var_dump($reminder_data);