const { Octokit } = require("@octokit/rest");

const { GITHUB_TOKEN, GITHUB_OWNER, GITHUB_REPOSITORY } = process.env;

const octokit = new Octokit({ auth: `${GITHUB_TOKEN}` });

const getPullRequests = async () => {
  const RESPONSE = await octokit.paginate(
    octokit.rest.pulls.list,
    {
      owner: `${GITHUB_OWNER}`,
      repo: `${GITHUB_REPOSITORY}`,
    }
  );

  return await RESPONSE;
}

const getTimelineActivity = async (issueNumber) => {
  const TIMELINE_ACTIVITY = await octokit.paginate(
    octokit.rest.issues.listEventsForTimeline,
    {
      owner: `${GITHUB_OWNER}`,
      repo: `${GITHUB_REPOSITORY}`,
      issue_number: `${issueNumber}`,
    }
  );

  return await TIMELINE_ACTIVITY;
}

(async () => {
  const PULL_REQUESTS = await getPullRequests();

  for (const PULL_REQUEST of PULL_REQUESTS) {
    let REQUESTED_REVIEWERS = PULL_REQUEST.requested_reviewers;

    const TIMELINE_ACTIVITY = (await getTimelineActivity(PULL_REQUEST.number))
      .filter(timelineActivity => timelineActivity.event === 'review_requested');

    REQUESTED_REVIEWERS.forEach(requestedReviewer => {
      const REQUESTED_REVIEWER_LOGIN = requestedReviewer.login;;
      const TIMESTAMP = TIMELINE_ACTIVITY
        .filter(timelineActivity => {
          return timelineActivity.requested_reviewer.login === REQUESTED_REVIEWER_LOGIN;
        })[0].created_at

      // TODO - send a notification for login/timestamp
    });
  }
})();