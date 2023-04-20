const core = require('@actions/core');

const { GITHUB_TOKEN, GITHUB_REPOSITORY, GITHUB_API_URL } = process.env;
const PULL_REQUESTS_URL = `${GITHUB_API_URL}/repos/${GITHUB_REPOSITORY}/pulls`;

async function getPullRequests() {
  return fetch(PULL_REQUESTS_URL, {
    headers: {
      Authorization: `token ${GITHUB_TOKEN}`,
    },
  })
}

core.info(await getPullRequests());