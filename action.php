<?php

declare(strict_types=1);

date_default_timezone_set('America/Chicago');

require('/functions.php');

define('GITHUB_ACCESS_TOKEN', getenv('INPUT_GITHUB_ACCESS_TOKEN'));
define('GITHUB_REPOSITORY', getenv('GITHUB_REPOSITORY'));
define('GITHUB_OWNER_NAME', explode('/', GITHUB_REPOSITORY)[0]);
define('GITHUB_REPOSITORY_NAME', explode('/', GITHUB_REPOSITORY)[1]);
define('HOURS_UNTIL_REMINDER', intval(getenv('INPUT_HOURS_UNTIL_REMINDER')));
define('SMTP_USERNAME', getenv('INPUT_SMTP_USERNAME'));
define('SMTP_PASSWORD', getenv('INPUT_SMTP_PASSWORD'));

$reminders = getRemindersData(getGitHubClient(GITHUB_ACCESS_TOKEN),
                              GITHUB_OWNER_NAME,
                              GITHUB_REPOSITORY_NAME,
                              HOURS_UNTIL_REMINDER);

sendEmails($reminders, SMTP_USERNAME, SMTP_PASSWORD);

// TODO: Send reminders via Slack message