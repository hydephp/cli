# Contributing

Contributions are **welcome** and will be fully **credited**.

Please read and understand the contribution guide before creating an issue or pull request. This document is a living standard that may be updated when needed.

## Resources and guidance

This repository contains the code for the standalone HydePHP executable project. If you want to contribute to a feature within HydePHP itself,
please visit the main HydePHP development repository https://github.com/hydephp/develop.

If you want to contribute to something specially related to the HydePHP CLI, you're in the right place!

## Development setup

1. Fork the repository on GitHub
2. Clone your fork to your local machine
3. Install dependencies with `composer install`

You can run a live version of the executable by running `php hyde <command>` in the project root to test your changes.

## Testing

Please add tests for any new features or bug fixes. Tests are run using PestPHP.

```bash
vendor/bin/pest
```

## Releases

Releases are handled by the maintainers of the project according to the following workflow:
1. The `create-release.yml` GitHub Actions workflow is triggered by workflow dispatch, where the maintainer specifies the SemVer level.
2. The workflow updates the application version constant and compiles the application. It then creates a new branch and creates a pull request.
3. The maintainer reviews the pull request and merges it into the protected stable branch when ready.
4. The `publish-release.yml` GitHub Actions workflow is triggered by the merge, which creates a new release on GitHub with the compiled application attached. It also syncs the changes to the main branch.
