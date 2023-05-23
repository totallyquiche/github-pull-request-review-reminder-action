<?php

declare(strict_types=1);

use \Github\Client;
use \Github\AuthMethod;

require('/vendor/autoload.php');

/**
 * Instantiate and authenticate a GitHub API client.
 */
function getGitHubClient(string $github_access_token) : Client {
    $client = new Client();
    $client->authenticate($github_access_token, null, AuthMethod::ACCESS_TOKEN);

    return $client;
}

/**
 * Fetch all open Pull Requests via the GitHub API.
 */
function getPullRequests(Client $client,
                         string $github_owner_name,
                         string $github_repository_name) : array {
    return $client->api('pull_request')
                  ->all($github_owner_name,
                        $github_repository_name,
                        array('state' => 'open'));
}

/**
 * Fetch all Review Requests for a given Pull Request number.
 */
function getReviewRequestLogins(Client $client,
                                string $github_owner_name,
                                string $github_repository_name,
                                int $pull_request_number) : array {
    $review_requests = $client->api('pull_request')
                              ->reviewRequests()
                              ->all($github_owner_name,
                                    $github_repository_name,
                                    $pull_request_number);

    $review_request_logins = [];

    foreach ($review_requests['users'] as $review_request_user) {
        $review_request_login = $review_request_user['login'];

        if (!in_array($review_request_login, $review_request_logins)) {
            $review_request_logins[] = $review_request_login;
        }
    }

    return $review_request_logins;
}

/**
 * Fetch all Review Request Timeline Activities for a given Pull Request Number.
 * Only returns Activities to which one of the current Requested Reviewers on
 * the Pull Request is related.
 */
function getTimelineActivities(Client $client,
                               string $github_owner_name,
                               string $github_repository_name,
                               int $pull_request_number,
                               int $hours_until_reminder) : array {
    $activities = $client->api('issue')
                  ->timeline()
                  ->all($github_owner_name,
                        $github_repository_name,
                        $pull_request_number);

    $activities = array_filter($activities, function ($activity) {
        return $activity['event'] === 'review_requested';
    });

    usort($activities, function ($a, $b) {
        return new DateTime($a['created_at']) < new DateTime($b['created_at']);
    });

    $requested_reviewer_logins = [];

    $activities = array_filter(
        $activities,
        function ($activity) use ($hours_until_reminder, &$requested_reviewer_logins) {
            $start_date_time = new DateTime('-' . $hours_until_reminder . ' hours');
            $activity_created_at = new DateTime($activity['created_at']);

            $include_activity = $activity_created_at->getTimestamp() > $start_date_time->getTimestamp();
            $include_activity = !in_array($activity['requested_reviewer']['login'],
                                          $requested_reviewer_logins) &&
                                $include_activity;

            return $include_activity;
        }
    );

    return $activities;
}

/**
 * Get an array of data intended for use in sending the PR review reminder.
 */
function getRemindersData(Client $client,
                          string $github_owner_name,
                          string $github_repository_name,
                          int    $hours_until_reminder) : array {
    $reminders = [];
    $pull_requests = getPullRequests($client,
                                     $github_owner_name,
                                     $github_repository_name);

    foreach ($pull_requests as $pull_request) {
        $pull_request_number = $pull_request['number'];

        $review_request_logins = getReviewRequestLogins($client,
                                                        $github_owner_name,
                                                        $github_repository_name,
                                                        $pull_request_number);

        if (empty($review_request_logins)) {
            continue;
        }

        foreach ($review_request_logins as $review_request_login) {
            if (!isset($reminders[$review_request_login])) {
                $reminders[$review_request_login] = [];
            }
        }

        $activities = getTimelineActivities($client,
                                            $github_owner_name,
                                            $github_repository_name,
                                            $pull_request_number,
                                            $hours_until_reminder);

        foreach ($activities as $activity) {
            $login = $activity['requested_reviewer']['login'];

            $reminders[$login][] = [
                'link'                => $pull_request['html_url'],
                'review_requested_at' => $activity['created_at'],
            ];
        }
    }

    return $reminders;
}

/**
 * Find the email address associated with the given GitHub login.
 */
function getEmailAddressFromGitHubLogin(string $login) : string
{
    $user_data = json_decode(
        file_get_contents('/user-data.json'),
        true
    );

    foreach ($user_data['users'] as $user) {
        if ($user['githubLogin'] === $login) {
            return $user['emailAddress'];
        }
    }

    return json_decode($user_data, true)[$login]['emailAddress'];
}

/**
 * Send emails via SMTP.
 */
function sendEmails(array $reminder_data,
                    string $smtp_username,
                    string $smtp_password) : void
{
    $smtp_transport = new Swift_SmtpTransport('smtp.mailgun.org', 587);
    $smtp_transport->setUsername($smtp_username);
    $smtp_transport->setPassword($smtp_password);

    $mailer = new Swift_Mailer($smtp_transport);

    foreach ($reminder_data as $login => $reminders) {
        $pull_request_links_html = '<ul>';

        foreach ($reminders as $reminder) {
            $requested_date_time = new DateTime($reminder['review_requested_at']);
            $timestamp = $requested_date_time->format('F jS at h:ma'); // Ex. May 22nd at 9:56am
            $link_text = $reminder['link'] . ' (review requested on ' . $timestamp . ')';
            $pull_request_links_html .= '<li>' . $link_text . '</li>';
        }

        $pull_request_links_html .= '</ul>';

        $message_body = <<<HTML
Please take some time to revisit the following Pull Requests, which are awaiting
your review:
<br/><br/>
$pull_request_links_html
HTML;

        $to_address = getEmailAddressFromGitHubLogin($login);

        $message = (new \Swift_Message())
            ->setSubject('Pull Requests awaiting your review')
            ->setTo([$to_address => $login])
            ->setFrom(['sophia.jenkins@mg.wellspring.engineering' => 'Sophia Jenkins'])
            ->setBody($message_body, 'text/html');

        $mailer->send($message);
    }
}