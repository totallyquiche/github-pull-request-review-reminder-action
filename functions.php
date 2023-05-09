<?php

declare(strict_types=1);

use \Github\Client;
use \Github\AuthMethod;

require('/vendor/autoload.php');

function getGitHubClient(string $github_access_token) : Client {
    $client = new Client();
    $client->authenticate($github_access_token, null, AuthMethod::ACCESS_TOKEN);

    return $client;
}

function getPullRequests(Client $client,
                         string $github_owner_name,
                         string $github_repository_name) : array {
    return $client->api('pull_request')
                  ->all($github_owner_name,
                        $github_repository_name,
                        array('state' => 'open'));
}

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

function getTimelineActivities(Client $client,
                               string $github_owner_name,
                               string $github_repository_name,
                               int $pull_request_number) : array {
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
        function ($activity) use (&$requested_reviewer_logins) {
            return !in_array($activity['requested_reviewer']['login'],
                             $requested_reviewer_logins);
        }
    );

    return $activities;
}

function getNotifications(Client $client,
                          $github_owner_name,
                          $github_repository_name) : array {
    $notifications = [];
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

        $notifications[$pull_request_number] = [];

        $activities = getTimelineActivities($client,
                                            $github_owner_name,
                                            $github_repository_name,
                                            $pull_request_number);

        foreach ($activities as $activity) {
            $notifications[$pull_request_number]['link'] = $pull_request['html_url'];
            $notifications[$pull_request_number]['login'] = $activity['requested_reviewer']['login'];
            $notifications[$pull_request_number]['created_at'] = $activity['created_at'];
        }
    }

    return $notifications;
}