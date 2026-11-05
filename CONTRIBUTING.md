# Contributing to UAMSWP-Content-Syndication

<!-- uams-claude-skills:begin -->
## Branches and environments

Every change starts as an issue, is built on a branch linked to that issue, and reaches production through a pull request to `master`. This section covers that path and what each pull request's test plan has to cover.

| Branch | Role | Where its test plan runs |
| --- | --- | --- |
| `master` | Default branch, production | The local environment, before the pull request merges |

## From issue to merge

1. **File or pick up an issue.** The work is described there, with acceptance criteria.
2. **Create a branch from `master`, linked to the issue.** Use the issue's **Create a branch** link, or:

   ```sh
   gh issue develop <number> --base master --name <number>-<short-slug> --checkout
   ```

   The link shows the branch, and later its pull requests, on the issue.
3. **Open a pull request from the branch to `master`.** It carries the full description: what changed, why, and how it was verified. Its test plan is run on the local environment, and its boxes are checked before review.

## Test plans

Every pull request ends with a `## Test plan` checklist. Its first line names the environment it was run on, and every item is something a reviewer can check there:

- **Pull request to `master`:** "Run on the local environment." Items are things you checked yourself there: a page, a role, a save, a command. Check a box only for a check you actually ran.

The [pull request template](.github/pull_request_template.md) fills in the skeleton when a pull request is opened on GitHub.

## Agents

Coding agents follow the same path. Their instructions are in [`AGENTS.md`](AGENTS.md) and the shared skills and rules under `.claude/`, which are synced from `UAMS-Web/uams-claude-skills`.
<!-- uams-claude-skills:end -->

## Running the tests

The suite is Pest 5 with WordPress functions mocked by Brain Monkey, so it needs PHP 8.4 and Composer and nothing else: no database and no WordPress install. The Composer dependencies are development-only and are not part of the plugin download.

```sh
composer install
composer test
```

Run it before opening a pull request. Test files live in `tests/Unit` and are named `*Test.php`; a file named otherwise is not picked up.

## Running the code-quality checks

Rector, PHPStan and Pint run from the same `composer install`, in this order, and each must pass before a pull request:

```sh
composer test:refactor
composer stan
composer test:pint
```

`composer refactor` and `composer pint` apply what the first and third report. PHPStan runs at the level `phpstan.neon.dist` names (level 0 today) and rises one level per change, in the same pull request as the fixes for that level. `includes/_unused` is left out of all three tools because nothing loads it.
