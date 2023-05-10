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
                               int $minimum_elapsed_hours) : array {
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
        function ($activity) use ($minimum_elapsed_hours, &$requested_reviewer_logins) {
            $start_date_time = new DateTime('-' . $minimum_elapsed_hours . ' hours');
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
function getReminderData(Client $client,
                         string $github_owner_name,
                         string $github_repository_name,
                         int    $minimum_elapsed_hours) : array {
    $reminders = [];
    $pull_requests = getPullRequests($client,
                                     $github_owner_name,
                                     $github_repository_name);

    foreach ($pull_requests as $pull_request) {
        $pull_request_number = $pull_request['number'];

        $review_requests = getReviewRequestLogins($client,
                                                  $github_owner_name,
                                                  $github_repository_name,
                                                  $pull_request_number);

        if (empty($review_requests)) {
            continue;
        }

        $reminders[$pull_request_number] = [];

        $activities = getTimelineActivities($client,
                                            $github_owner_name,
                                            $github_repository_name,
                                            $pull_request_number,
                                            $minimum_elapsed_hours);

        foreach ($activities as $activity) {
            $reminders[$pull_request_number]['link'] = $pull_request['html_url'];
            $reminders[$pull_request_number]['login'] = $activity['requested_reviewer']['login'];
            $reminders[$pull_request_number]['created_at'] = $activity['created_at'];
        }
    }

    return $reminders;
}